<?php
namespace Nudlle\Module;
use Nudlle\Module\Session;

abstract class I18n extends \Nudlle\Core\Module implements \Nudlle\Iface\I18n {

  const DOMAIN = 'current_locale';
  const COOKIE = 'i18n';
  const EXPIRE = 15552000; # 180 * 86400 -> appox. 6 months
  const TR_FILE = 'i18n.ini';

  private static $translations = [];
  private static $to_locale = null;
  private static $from_locale = null;
  private static $soft = null;

  protected static $cfg_pattern = [
    'default' => 1,
    'available' => [
      self::CFG_UNKNOWN => null,
    ],
    'mapping' => [
      self::CFG_UNKNOWN => null,
    ],
  ];

  private static function to_locale($value) {
    if (self::$to_locale === null) {
      self::$to_locale = [];
      try {
        $mapping = self::get_cfg('mapping');

        foreach ($mapping as $locale => $alias) {
          try {
            self::get_cfg('available.'.$locale);
          } catch (\Nudlle\Exception\Undefined $e) {
            throw new \Nudlle\Exception\App("Wrong locale mapping - '$locale' is not an available locale.");
          }
          self::$to_locale[$alias] = $locale;
        }
      } catch (\Nudlle\Exception\Undefined $e) {}
    }

    if (array_key_exists($value, self::$to_locale)) {
      return self::$to_locale[$value];
    } else {
      return $value;
    }
  }

  private static function from_locale($value) {
    if (self::$from_locale === null) {
      self::$from_locale = [];
      try {
        $mapping = self::get_cfg('mapping');

        foreach ($mapping as $locale => $alias) {
          try {
            self::get_cfg('available.'.$locale);
          } catch (\Nudlle\Exception\Undefined $e) {
            throw new \Nudlle\Exception\App("Wrong locale mapping - '$locale' is not an available locale.");
          }
          self::$from_locale[$locale] = $alias;
        }
      } catch (\Nudlle\Exception\Undefined $e) {}
    }

    if (array_key_exists($value, self::$from_locale)) {
      return self::$from_locale[$value];
    } else {
      return $value;
    }
  }

  public static function get_language() {
    $locale = self::get_locale(false);
    return substr($locale, 0, strpos($locale, '_'));
  }

  public static function get_locale($use_mapping = true) {
    if (self::$soft !== null) {
      return $use_mapping ? self::from_locale(self::$soft) : self::$soft;
    }

    $is_set = 0;
    $locale = null;

    try {
      $locale = Session::get(self::DOMAIN);
      $is_set = 1;
    } catch (\Nudlle\Exception\Undefined $e) {
      if (array_key_exists(self::COOKIE, $_COOKIE)) {
        $locale = $_COOKIE[self::COOKIE];
      }
    }

    $available = self::get_cfg('available');

    if (isset($locale) && !array_key_exists($locale, $available)) {
      $locale = null;
    }

    if (!isset($locale)) {
      $lang_map = [];
      foreach (array_keys($available) as $item) {
        $lang = substr($item, 0, strpos($item, '_'));
        if (!array_key_exists($lang, $lang_map)) $lang_map[$lang] = $item;
      }

      $accepted = self::get_accepted_locales();
      foreach ($accepted as $item) {
        if (strpos($item, '_') !== false) {
          if (array_key_exists($item, $available)) {
            $locale = $item;
            break;
          }
        } else {
          if (array_key_exists($item, $lang_map)) {
            $locale = $lang_map[$item];
            break;
          }
        }
      }
    }

    if (!isset($locale)) {
      $locale = self::get_cfg('default');
      if (!array_key_exists($locale, $available)) {
        throw new \Nudlle\Exception\App('Invalid configuration of module I18n - default locale is not available.');
      }
    }

    if (!$is_set) {
      # Locale has not been properly set and must have been acquired by other
      # means (cookie, headers, default). This (most probably) means that this
      # is the first initial request and no locale identification is present in
      # the requested URI (ie. http://yourdomain.com). This is correct, but the
      # user should be redirected to a corresponding address with the locale
      # identification included (ie. http://yourdomain.com/en_GB/home). Throwing
      # an exception should be a signal to do so.
      # Reason - SEO.
      self::set_locale($locale);
      throw new \Nudlle\Exception\Undefined();
    } else {
      if ($use_mapping) $locale = self::from_locale($locale);
      return $locale;
    }
  }

  public static function set_locale($locale, $soft = false) {
    $locale = self::to_locale($locale);

    try {
      self::get_cfg("available.$locale");
    } catch (\Nudlle\Exception\Undefined $e) {
      throw new \Nudlle\Exception\WrongData("Invalid locale '$locale'");
    }

    try {
      $last = Session::get(self::DOMAIN);
    } catch (\Nudlle\Exception\Undefined $e) {
      $last = null;
    }

    if ($last != $locale || $soft) {
      if ($soft) {
        if ($last === null) {
          throw new \Nudlle\Exception\App('Soft locale change is not possible when no locale has been set yet.');
        }
        self::$soft = $locale;
      } else {
        self::$soft = null;
        Session::set(self::DOMAIN, $locale);
        setcookie(self::COOKIE, $locale, time() + self::EXPIRE, '/');
      }
      setlocale(LC_COLLATE, $locale);
      setlocale(LC_CTYPE, $locale);
      setlocale(LC_MONETARY, $locale);
      setlocale(LC_TIME, $locale);
    } elseif (!$soft && self::$soft !== null) {
      self::reset_locale();
    }
  }

  public static function reset_locale() {
    if (self::$soft === null) {
      throw new \Nudlle\Exception\App('Locale can not be reset if it has not been soft-set previously.');
    }

    self::$soft = null;
    $locale = Session::get(self::DOMAIN);
    setlocale(LC_COLLATE, $locale);
    setlocale(LC_CTYPE, $locale);
    setlocale(LC_MONETARY, $locale);
    setlocale(LC_TIME, $locale);
  }

  protected static function get_accepted_locales() {
    if (!array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER)) {
      return [];
    }

    $items = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    $parsed = [];
    foreach ($items as $item) {
      # example: 'en', 'en-GB', 'en;q=0.7', 'en-GB;q=0.7'
      if (preg_match('/^\s*([a-zA-Z]+(?:-[a-zA-Z]+)?)\s*(?:;\s*q\s*=\s*([0-9\.]+)\s*)?$/', $item, $m)) {
        $q = isset($m[2]) ? $m[2] : 1;
        if ($q > 0) {
          $parsed[] = [ $m[1], $q ];
        }
      }
    }

    usort($parsed, function($a, $b) {
      if ($a[1] < $b[1]) {
        return 1;
      } elseif ($a[1] > $b[1]) {
        return -1;
      } else {
        return strcmp($a[0], $b[0]);
      }
    });

    $index = [];
    $result = [];
    foreach ($parsed as $item) {
      if (array_key_exists($item[0], $index)) continue;
      $index[$item[0]] = 1;
      $result[] = $item[0];
    }

    return $result;
  }

  private static function load_translation($module) {
    if (isset(self::$translations[$module])) {
      return;
    }

    $f = call_user_func('\\Nudlle\\Module\\'.$module.'::get_path').'/'.self::TR_FILE;
    if (file_exists($f) && is_file($f)) {
      $data = parse_ini_file($f, true);
      if ($data === false) {
        throw new \Nudlle\Exception\Cfg($f);
      }
      self::$translations[$module] = [];

      foreach ($data as $locale => $labels) {
        try {
          self::get_cfg('available.'.$locale);
        } catch (\Nudlle\Exception\Undefined $e) {
          throw new \Nudlle\Exception\App("'$locale' is not an available locale - in file '$f'.");
        }

        foreach ($labels as $label => $tr) {
          self::$translations[$module][$locale][$label] = $tr;
        }
      }
    } else {
      throw new \Nudlle\Exception\App("Module translations file '$f' is missing.");
    }
  }

  public static function translate($label) {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
    $module = null;
    foreach ($trace as $item) {
      if (preg_match('@/'.\Nudlle\MODULES_PATH.'/([^/]+)/@', $item['file'], $m)) {
        if (\Nudlle\has_module($m[1])) $module = $m[1];
      }
    }
    if (!$module) {
      throw new \Nudlle\Exception\App('Can not identify the source module for loading the translation configuration file.');
    }

    self::load_translation($module);
    $locale = self::get_locale(false);
    if (!array_key_exists($locale, self::$translations[$module])) {
      throw new \Nudlle\Exception\Undefined("Locale '$locale' is missing in translatons for module '$module'.");
    }
    if (!array_key_exists($label, self::$translations[$module][$locale])) {
      throw new \Nudlle\Exception\Undefined("Label '$label' for locale '$locale' is missing in translatons for module '$module'.");
    }
    return self::$translations[$module][$locale][$label];
  }

  public static function get_available() {
    $a = [];
    foreach (self::get_cfg('available') as $locale => $label) {
      $a[self::from_locale($locale)] = $label;
    }
    return $a;
  }

}

?>
