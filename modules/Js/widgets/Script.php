<?php
namespace Nudlle\Module\Js\Widget;
use Nudlle\Module\Js;

class Script extends \Nudlle\Core\Widget {

  private $render = false;

  private static function find_i18n_file($lib_name, $locale) {
    $path = Js::get_js_path();
    $locales = [ $locale, Js::get_cfg('default_locale') ];

    foreach ($locales as $item) {
      if (file_exists("$path/$lib_name.$item.js")) {
        return "$lib_name.$item";
      }
    }

    $msg = "Locale definition for JS library '$lib_name' can not be found. ";
    $msg .= "Both required (".$locales[0].") and default (".$locales[1].") locales are missing.";
    throw new \Nudlle\Exception\App($msg);
  }

  protected function prepare_data() {
    $libs = Js::get_required_libs();
    if (count($libs) == 0) {
      $this->render = false;
      return;
    }

    $locale = \Nudlle\has_module('I18n') ? \Nudlle\Module\I18n::get_locale(false) : null;
    if ($locale === null) $locale = substr(\Nudlle\LOCALE, 0, strpos(\Nudlle\LOCALE, '.'));

    foreach ($libs as $item) {
      if ($item[1]) {
        $this->add_js_file('i18n');
        $this->add_js_file(self::find_i18n_file($item[0], $locale));
      }

      switch ($item[0]) {
        case 'jquery':
          if (!Js::get_cfg('skip_jquery')) {
            if (Js::get_cfg('use_cdn')) {
              $this->add_js_file('https://code.jquery.com/jquery-2.2.4.js');
            } else {
              $this->add_js_file('jquery-2.2.4.min');
            }
          }
          break;

        case 'bootstrap':
          if (!Js::get_cfg('skip_bootstrap')) {
            if (Js::get_cfg('use_cdn')) {
              $this->add_js_file('https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js');
              $this->add_css_file('https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css');
              $this->add_css_file('https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css');
            } else {
              $this->add_js_file('bootstrap-3.3.6.min');
              $this->add_css_file('bootstrap.min');
              $this->add_css_file('bootstrap-theme.min');
            }
          }
          break;

        case 'moment':
          if (!Js::get_cfg('skip_moment')) {
            if (Js::get_cfg('use_cdn')) {
              $this->add_js_file('https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.10.6/moment.min.js');
            } else {
              $this->add_js_file('moment-2.10.6.min');
            }
          }
          break;

        case 'core':
          $this->add_js_file('core');
          $this->render = true;
          break;

        case 'forms':
          $this->add_js_file('forms');
          break;
      }
    }
  }

  # Contructor of this widget is supposed to be called with an internally
  # created Request object that is filled with data meant for JS usage.
  # In other words - js_data property of a screen widget is supposed to be
  # be copied into this widget as its data property.
  protected function output() {
    if (!$this->render) return;

    $s = self::get_indent_space();
    $buff = [];

    $buff[] = '<script type="text/javascript">';
    $buff[] = $s.'(function(window){';
    $buff[] = $s.$s.'var n = {};';
    $buff[] = $s.$s.'var data = {';

    $no = count($this->data);
    $i = 1;
    foreach ($this->data as $key => $value) {
      $buff[] = $s.$s.$s."'".$key."'".': '.json_encode($value).($i++ < $no ? ',' : '');
    }

    $buff[] = $s.$s.'};';
    $buff[] = $s.$s.'n.data = function(key, value) { if (typeof(value) == "undefined") { return typeof(data[key]) != "undefined" ? data[key] : null; } else { data[key] = value; } };';
    $buff[] = $s.$s.'window.Nudlle = n;';
    $buff[] = $s.'})(window);';
    $buff[] = '</script>';

    self::write_buffer($buff);
  }

}

?>
