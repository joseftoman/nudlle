<?php
namespace Nudlle\Module;

abstract class Rewrite extends \Nudlle\Core\Module implements \Nudlle\Iface\Rewrite {

  const DEFAULT_MODULE = 'Baseview';

  protected static $cfg_pattern = [
    'base' => 0,
  ];

  private static function list_modules() {
    $modules = [];
    if ($handle = @opendir(\Nudlle\MODULES_PATH)) {
      while (false !== ($entry = readdir($handle))) {
        if ($entry != '.' && $entry != '..' && is_dir(\Nudlle\MODULES_PATH.'/'.$entry)
            && \Nudlle\has_module($entry)
            && is_subclass_of('\\Nudlle\\Module\\'.$entry, self::MODULE_SUPERCLASS)) {
          $modules[] = $entry;
        }
      }
    }
    return $modules;
  }

  private static function get_module($param) {
    foreach (self::list_modules() as $module) {
      if (call_user_func('\\Nudlle\\Module\\'.$module.'::get_rewrite_label') == $param) {
        return $module;
      }
    }
    return false;
  }

  private static function set_404 () {
    $_GET[\Nudlle\Core\Request::INDEX_MODULE] = self::DEFAULT_MODULE;
    $_GET[\Nudlle\Core\Request::INDEX_OPERATION] = \Nudlle\Core\ContentModule::O_404;
  }

  // Must be run before \Nudlle\Core\Request is created.
  public static function translate_request() {
    if (!array_key_exists(self::PARAM, $_GET)) {
      return;
    }

    $input = $_GET[self::PARAM];
    unset($_GET[self::PARAM]);
    if ($input[0] == '/') {
      $input = substr($input, 1);
    }

    if (preg_match('/^[0-9a-f]{14}\.[0-9]{8}$/', $input)) {
      $_GET[\Nudlle\Core\Request::INDEX_ID] = $input;
      return;
    }

    $input = explode('/', $input);
    \Nudlle\Core\Format::deep_trim($input);
    $input = array_filter($input);
    if (empty($input)) {
      return;
    }

    if (\Nudlle\has_module('I18n')) {
      try {
        \Nudlle\Module\I18n::set_locale(array_shift($input));
      } catch (\Nudlle\Exception\WrongData $e) {
        self::set_404();
        return;
      }
    }

    $module = self::get_module($input[0]);
    if ($module === false) {
      $module = self::DEFAULT_MODULE;
    }

    $input = call_user_func('\\Nudlle\\Module\\'.$module.'::process_rewrite_params', $input);
    if ($input === false) {
      self::set_404();
      return;
    } else {
      $operation = $input[0];
      foreach ($input[1] as $key => $value) {
        $_GET[$key] = $value;
      }
    }

    $_GET[\Nudlle\Core\Request::INDEX_MODULE] = $module;
    $_GET[\Nudlle\Core\Request::INDEX_OPERATION] = $operation;
  }

}

?>
