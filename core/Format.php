<?php
namespace Nudlle\Core;

// TODO: remove hardcoded Czech and use some proper i18n

abstract class Format {

  const MYSQL = 0;
  const NUMERIC = 1;
  const TEXT = 2;
  const SMART = 3;

  private static $diacritics = [
    'Š'=>'S', 'š'=>'s', 'Đ'=>'D', 'đ'=>'d', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C',
    'č'=>'c', 'Ć'=>'C', 'ć'=>'c', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A',
    'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E',
    'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O',
    'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U',
    'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'S', 'à'=>'a', 'á'=>'a',
    'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e',
    'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i',
    'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o',
    'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ü'=>'u',
    'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r', 'Ď'=>'D', 'ď'=>'d', 'Ě'=>'E', 'ě'=>'e',
    'Ĺ'=>'L', 'ĺ'=>'l', 'Ľ'=>'L', 'ľ'=>'l', 'Ň'=>'N', 'ň'=>'n', 'Ř'=>'R',
    'ř'=>'r', 'Ť'=>'T', 'ť'=>'t', 'Ů'=>'U', 'ů'=>'u', 'Ł'=>'L', 'ł'=>'l'
  ];

  private static $size_units = [ 'B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB' ];

  public static function remove_diacritics($text) {
    return strtr($text, self::$diacritics);
  }

  public static function filter_alphanum($text) {
    return preg_replace('/[^a-zA-Z0-9-]/', '', $text);
  }

  public static function mod_rw($text) {
    $text = self::remove_diacritics($text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = self::filter_alphanum($text);
    $text = strtolower($text);
    $text = preg_replace('/-+/', '-', $text);
    return $text;
  }

  public static function search_string($text) {
    $chars = implode('', array_keys(self::$diacritics));
    $text = preg_replace('/[^'.$chars.'a-zA-Z0-9.]/u', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = preg_replace('/^\s+/u', '', $text);
    $text = preg_replace('/\s+$/u', '', $text);
    return $text;
  }

  private static function month($month) {
    switch ($month) {
      case  1: return 'ledna';
      case  2: return 'února';
      case  3: return 'března';
      case  4: return 'dubna';
      case  5: return 'května';
      case  6: return 'června';
      case  7: return 'července';
      case  8: return 'srpna';
      case  9: return 'září';
      case 10: return 'října';
      case 11: return 'listopadu';
      case 12: return 'prosince';
    }
    return '';
  }

  public static function weekday($index, $nominative = true) {
    switch (($index - 1) % 7) {
      case 0: return $nominative ? 'Pondělí' : 'v pondělí';
      case 1: return $nominative ? 'Úterý' : 'v úterý';
      case 2: return $nominative ? 'Středa' : 've středu';
      case 3: return $nominative ? 'Čtvrtek' : 've čtvrtek';
      case 4: return $nominative ? 'Pátek' : 'v pátek';
      case 5: return $nominative ? 'Sobota' : 'v sobotu';
      case 6: return $nominative ? 'Neděle' : 'v neděli';
    }
    return '';
  }

  public static function date($input, $format = self::NUMERIC, $no_year = false, $nominative = true) {
    if (is_numeric($input)) {
      $ts = intval($input);
    } else {
      $ts = strtotime($input);
    }
    $text = \date('Y-m-d', $ts);

    if ($format == self::MYSQL) {
      return $text;
    }

    if ($no_year && $format == self::SMART && \date('Y', $ts) != \date('Y')) {
      $no_year = false;
    }

    if ($format == self::NUMERIC) {
      return $no_year ? \date('j.n.', $ts) : \date('j.n.Y', $ts);
    }

    if ($format == self::TEXT || $format == self::SMART) {
      $parts = explode('-', $text);
      $date = intval($parts[2], 10).'. '.self::month(intval($parts[1], 10));
      if (!$no_year) {
        $date .= ' '.$parts[0];
      }
    }

    if ($format == self::SMART) {
      $days = self::period_length(\date('Y-m-d'), $text);
      $direction = time() < $ts ? 1 : -1;
      if ($days == 1) {
        $day_name = $nominative ? 'Dnes' : 'dnes';
      } elseif ($days == 2) {
        $day_name = $direction < 0 ? ($nominative ? 'Včera' : 'včera') : ($nominative ? 'Zítra' : 'zítra');
      } else {
        $day_name = self::weekday(\date('N', $ts), $nominative || $days > 7);
      }

      if ($days <= 7) {
        $date = '<span title="'.$date.'">'.$day_name.'</span>';
      } else {
        $date = '<span title="'.$day_name.'">'.$date.'</span>';
      }
    }

    return $date;
  }

  public static function datetime($input, $format = self::NUMERIC, $no_year = false, $no_seconds = false) {
    if (is_numeric($input)) {
      $ts = intval($input);
    } else {
      $ts = strtotime($input);
    }
    $time = date('G:i:s', $ts);

    if ($format == self::MYSQL) {
      return \date('Y-m-d', $ts).' '.$time;
    }

    if ($no_seconds) {
      $time = substr($time, 0, strrpos($time, ':'));
    }

    $smart_time = false;
    if ($format == self::SMART) {
      $diff = time() - $ts;
      if ($diff >= 0) {
        if ($diff < 60) {
          $smart_time = 'Právě teď';
        } elseif ($diff < 120) {
          $smart_time = 'Před minutou';
        } elseif ($diff < 300) {
          $smart_time = 'Před několika minutami';
        } elseif ($diff < 1800) {
          $smart_time = 'Před '.ceil($diff / 60).' minutami';
        }
      }
    }

    if ($smart_time) {
      $date = self::date($ts, self::TEXT, $no_year);
      return '<span title="'.$date.', '.$time.'">'.$smart_time.'</span>';
    } else {
      $date = self::date($ts, $format, $no_year);
      return $date.', '.$time;
    }
  }

  public static function money($amount, $decimals = 2, $unit = true) {
    $unit = $unit ? '&#8239;Kč' : '';
    return number_format(floatval($amount), $decimals, ',', '&#8239;').$unit;
  }

  public static function period_length($date1, $date2) {
    // Format: YYYY-MM-DD
    $date1 = explode('-', $date1);
    $date2 = explode('-', $date2);

    $date1 = gregoriantojd($date1[1], $date1[2], $date1[0]);
    $date2 = gregoriantojd($date2[1], $date2[2], $date2[0]);

    return abs($date1 - $date2) + 1;
  }

  public static function phone_number($input, $plain_text = false) {
    $sep = $plain_text ? ' ' : '&#8239;';
    $len = strlen($input);

    if ($len > 9) {
      return preg_replace('/^((?:\+|00)[0-9]+)([0-9]{3})([0-9]{3})([0-9]{3})$/', "$1$sep$2$sep$3$sep$4", $input);
    } elseif ($len == 9) {
      return preg_replace('/^([0-9]{3})([0-9]{3})([0-9]{3})$/', "$1$sep$2$sep$3", $input);
    } elseif ($len == 8) {
      return preg_replace('/^([0-9]{4})([0-9]{4})$/', "$1$sep$2", $input);
    } elseif ($len == 7) {
      return preg_replace('/^([0-9]{3})([0-9]{2})([0-9]{2})$/', "$1$sep$2$sep$3", $input);
    } elseif ($len == 6) {
      return preg_replace('/^([0-9]{3})([0-9]{3})$/', "$1$sep$2", $input);
    } elseif ($len == 5) {
      return preg_replace('/^([0-9]{3})([0-9]{2})$/', "$1$sep$2", $input);
    }

    return $input;
  }

  public static function size($size) {
    $index = 0;
    while ($size > 1024) {
      $size /= 1024;
      $index++;
    }

    return round($size, 2).' '.self::$size_units[$index];
  }

  public static function digit($digit) {
    if (!is_int($digit) || $digit < 1 || $digit > 9) {
      throw new \Nudlle\Exception\App('A digit (1 - 9) was expected');
    }

    switch ($digit) {
      case 1: return 'jedna';
      case 2: return 'dvě';
      case 3: return 'tři';
      case 4: return 'čtyři';
      case 5: return 'pět';
      case 6: return 'šest';
      case 7: return 'sedm';
      case 8: return 'osm';
      case 9: return 'devět';
    }
  }

  public static function minutes($amount) {
    return floor($amount / 60).':'.sprintf("%02d", $amount % 60);
  }

  // Recursive in-place trim
  public static function deep_trim(&$value) {
    if (is_scalar($value)) {
      $value = trim($value);
    } elseif (is_array($value)) {
      foreach ($value as &$item) {
        self::deep_trim($item);
      }
    }
  }

  // Removes BOM from strings (recursive, in-place)
  public static function remove_bom(&$input) {
    if (is_array($input)) {
      foreach ($input as &$item) {
        self::remove_bom($item);
      }
    } elseif (substr($input, 0, 3) == pack("CCC", 0xef, 0xbb, 0xbf)) {
      $input = substr($input, 3);
    }
  }

}

?>
