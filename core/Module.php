<?php
namespace Nudlle\Core;

abstract class Module {

  const CFG_FILE = 'conf.ini';
  const CFG_UNKNOWN = '_unknown';
  const CFG_DISABLE = 'disable_module';
  const CLASSES_DIR = 'classes';
  const TABLES_DIR = 'tables';
  const CSS_DIR = 'css';
  const IMAGES_DIR = 'images';
  const JS_DIR = 'js';
  const WIDGETS_DIR = 'widgets';
  const TEMPLATES_DIR = 'templates';

  protected $db = null;
  protected static $dependencies = [];
  private static $cfg = [];
  private static $has_cfg = [];
  protected static $cfg_pattern = [];

  public function __construct(\Nudlle\Module\Database\Wrapper $db = null) {
    $this->db = $db;
  }

  public static function test_dependencies() {
    try {
      if (self::get_cfg(self::CFG_DISABLE)) return false;
    } catch (\Nudlle\Exception\Undefined $e) {}

    foreach (static::$dependencies as $module) {
      if (!\Nudlle\has_module($module)) return false;
    }

    return true;
  }

  private static function process_cfg_value($input, $req, $type, $key) {
    $value = null;

    if ($type == 'i') {
      if (preg_match('/^[-+]?(?:0x[a-f0-9]+|[0-9]+)$/i', $input)) {
        $value = intval($input, 0);
      }
    } elseif ($type == 'f') {
      if (is_numeric($input)) $value = floatval($input);
    } elseif ($type == 'b') {
      if ($input == '1' || $input == '0' || $input === '') {
        $value = $input == '1' ? true : false;
      }
    } elseif (!$req || strlen($input)) {
      $value = $input;
    }

    if ($value !== null) {
      return $value;
    } else {
      $msg = "Invalid config value, ";
      switch ($type) {
        case "i": $msg .= 'integer'; break;
        case "f": $msg .= 'float'; break;
        case "b": $msg .= 'boolean'; break;
        default:  $msg .= 'string'; break;
      }
      $msg .= " expected: module = ".static::get_name().", key = '$key'";
      throw new \Nudlle\Exception\App($msg);
    }
  }

  private static function process_cfg(&$data, &$target, &$pattern, $prefix = []) {
    $s_prefix = implode('.', $prefix);
    if (count($prefix)) $s_prefix .= '.';

    $allow_unknown = false;
    $default_type = null;
    if (array_key_exists(self::CFG_UNKNOWN, $pattern)) {
      $allow_unknown = true;
      $default_type = isset($pattern[self::CFG_UNKNOWN]) ? strtolower($pattern[self::CFG_UNKNOWN]) : null;
      if ($default_type !== null && $default_type != 'i' && $default_type != 'f' && $default_type != 'b') {
        $msg = 'Invalid default type for undeclared config item: module = '.static::get_name();
        $msg .= ", key = '$s_prefix', type = '$default_type'";
        throw new \Nudlle\Exception\App($msg);
      }
    }

    foreach ($pattern as $key => &$spec) {
      if ($key == self::CFG_UNKNOWN) continue;
      if (is_array($spec)) {
        $target[$key] = [];
        self::process_cfg($data[$key], $target[$key], $spec, array_merge($prefix, [$key]));
        continue;
      }

      if (!preg_match('/^([01])([ifb])?(?:\|(.+))?$/i', $spec, $m)) {
        $msg = 'Invalid config item specification: module = '.static::get_name();
        $msg .= ", key = '$s_prefix$key', specification = '$spec'";
        throw new \Nudlle\Exception\App($msg);
      }
      $req = $m[1] == '1';
      $type = isset($m[2]) ? strtolower($m[2]) : null;
      if (isset($m[3]) && !isset($data[$key])) $data[$key] = $m[3];
      unset($m);

      if (isset($data[$key])) {
        $target[$key] = self::process_cfg_value($data[$key], $req, $type, "$s_prefix$key");
      } elseif ($req) {
        $msg = 'Required config item is missing: module = '.static::get_name();
        $msg .= ", key = '$s_prefix$key'";
        throw new \Nudlle\Exception\App($msg);
      }
    }

    foreach ($data as $key => $value) {
      if (array_key_exists($key, $pattern)) continue;

      if ($allow_unknown) {
        $target[$key] = self::process_cfg_value($value, true, $default_type, "$s_prefix$key");
      } else {
        $msg = 'Unknown config item: module = '.static::get_name();
        $msg .= ", key = '$s_prefix$key'";
        throw new \Nudlle\Exception\App($msg);
      }
    }
  }

  private static function load_cfg($f = null) {
    $name = static::get_name();

    $f = static::get_path().'/'.static::CFG_FILE;
    if (file_exists($f) && is_file($f)) {
      $data = parse_ini_file($f, true);
      if ($data === false) {
        throw new \Nudlle\Exception\Cfg($f);
      }
      self::$cfg[$name] = [];
      self::process_cfg($data, self::$cfg[$name], static::$cfg_pattern);
      self::$has_cfg[$name] = true;
    } else {
      self::$has_cfg[$name] = false;
    }
  }

  final public static function get_cfg($path = null) {
    $name = static::get_name();
    if (!isset(self::$has_cfg[$name])) {
      static::$cfg_pattern[self::CFG_DISABLE] = '1b|0';
      self::load_cfg();
    }

    $message = "$name: config item '$path' does not exist";
    if (!self::$has_cfg[$name]) {
      throw new \Nudlle\Exception\Undefined($message);
    }

    $data = &self::$cfg[$name];
    if ($path === null) {
      return $data;
    }

    foreach (Helper::tokenize_path($path) as $token) {
      if (!array_key_exists($token, $data)) {
        throw new \Nudlle\Exception\Undefined($message);
      }
      $data = &$data[$token];
    }

    return $data;
  }

  final public static function get_name() {
    $class = get_called_class();
    $pos = strrpos($class, '\\');
    if ($pos !== false) {
      $class = substr($class, $pos + 1);
    }
    return $class;
  }

  final public static function get_path() {
    return \Nudlle\MODULES_PATH.'/'.static::get_name();
  }

  public static function get_classes_path() {
    return static::get_path().'/'.static::CLASSES_DIR;
  }

  public static function get_tables_path() {
    return static::get_path().'/'.static::TABLES_DIR;
  }

  public static function get_css_path() {
    return static::get_path().'/'.static::CSS_DIR;
  }

  public static function get_images_path() {
    return static::get_path().'/'.static::IMAGES_DIR;
  }

  public static function get_js_path() {
    return static::get_path().'/'.static::JS_DIR;
  }

  public static function get_widgets_path() {
    return static::get_path().'/'.static::WIDGETS_DIR;
  }

  public static function get_templates_path() {
    return static::get_path().'/'.static::TEMPLATES_DIR;
  }

  public static function load($class_name) {
    $path = explode('\\', $class_name);
    if (count($path) == 1) {
      $file_name = static::get_classes_path().'/'.$class_name.'.php';
    } elseif (count($path) == 2 && $path[0] == 'Widget') {
      $file_name = static::get_widgets_path().'/'.$path[1].'.php';
    } elseif (count($path) == 2 && $path[0] == 'Table') {
      $file_name = static::get_tables_path().'/'.$path[1].'.php';
    } else {
      throw new \Nudlle\Exception\Load($class_name);
    }

    if (file_exists($file_name)) {
      require_once $file_name;
    } else {
      throw new \Nudlle\Exception\Load($class_name);
    }
  }

  public static function get_table($table_name = null) {
    if (!\Nudlle\has_module('Database')) {
      throw new \Nudlle\Exception\App("Required module 'Database' not found.");
    }

    $module = self::get_name();
    if (is_null($table_name)) $table_name = $module;
    $table_name = '\\Nudlle\\Module\\'.$module.'\\Table\\'.$table_name;
    return new $table_name();
  }

}

?>
