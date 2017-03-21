<?php
namespace Nudlle\Module;

abstract class Js extends \Nudlle\Core\Module implements \Nudlle\Iface\Js {

  protected static $cfg_pattern = [
    'default_locale' => '1|en_GB',
    'use_cdn' => '1b',
    'skip_jquery' => '1b|0',
    'skip_bootstrap' => '1b|0',
    'skip_moment' => '1b|0',
  ];

  // Order is important
  // When value = 1, library needs localization
  private static $available_libs = [
    'jquery' => 0,
    'bootstrap' => 0,
    'core' => 1,
    'moment' => 0,
    'forms' => 1,
  ];
  private static $required_libs = [];

  public static function require_lib($lib_name) {
    if (!array_key_exists($lib_name, self::$available_libs)) {
      throw new \Nudlle\Exception\App("Library '$lib_name' does not exist.");
    }

    if ($lib_name == 'forms') {
      self::require_lib('core');
      self::require_lib('moment');
    }
    if ($lib_name == 'core') {
      self::require_lib('bootstrap');
    }
    if ($lib_name == 'bootstrap') {
      self::require_lib('jquery');
    }

    self::$required_libs[$lib_name] = 1;
  }

  public static function get_required_libs() {
    $order = [];
    foreach (self::$available_libs as $name => $i18n) {
      if (array_key_exists($name, self::$required_libs)) {
        $order[] = [ $name, $i18n ];
      }
    }
    return $order;
  }

}

?>
