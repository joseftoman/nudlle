<?php
namespace Nudlle\Core;

class Request {

  const HISTORY_DOMAIN = 'history';
  const FORMS_DOMAIN = 'forms_pool';

  const HISTORY_SIZE = 40;

  // When true, the incoming request's PID is not checked.
  // For non-standard situations only (e.g. a database transfer).
  const CHECK_ID = 1;

  /***************************/
  /* Input parameter indices */
  /***************************/

  const INDEX_MODULE = '_module';
  const INDEX_OPERATION = '_operation';
  const INDEX_WIDGET = '_widget';
  const INDEX_ID = '_rid';
  const INDEX_SAVE_ID = '_saverid';
  const INDEX_AJAX = '_ajax';
  const INDEX_FILE = '_file';
  const INDEX_PING = '_ping';
  const INDEX_REDIRECT = '_redirect';
  const INDEX_I18N = '_i18n';

  /****************************/
  /* Output parameter indices */
  /****************************/

  const INDEX_STATUS = '_status';
  const INDEX_ERRORS = '_errors';
  const INDEX_NOAUTH = '_noauth';
  const INDEX_FRAGMENT = '_fragment';

  private $id;
  private $module = null;
  private $operation = null;
  private $widget = null;
  private $save_id = false;
  private $ajax = false;
  private $file = false;
  private $internal = false;
  private $params = [];
  private $data = [];
  private $errors = [];
  private $used_widgets = [];
  private $info = [];
  private $ping = false;
  private $prev_id = null;
  private $redirect = null;
  private $post_overflow = null;

  public function __construct($blank = true) {
    if ($blank) {
      $this->internal = true;
    } else {
      $is_404 = false;
      $this->post_overflow = self::detect_post_overflow();

      // The GET arguments overwrites the POST arguments.
      foreach ([ $_POST, $_GET ] as $method) {
        foreach ($method as $key => $value) {
          if ($key == '_') {
            // Timestamp added by jQuery
            continue;
          }

          Format::deep_trim($value);

          switch ($key) {
            case self::INDEX_MODULE: $this->module = $value; break;
            case self::INDEX_OPERATION: $this->operation = $value; break;
            case self::INDEX_WIDGET: $this->widget = $value; break;
            case self::INDEX_ID: $this->prev_id = $value; break;
            case self::INDEX_SAVE_ID: $this->save_id = $value ? true : false; break;
            case self::INDEX_AJAX: $this->ajax = $value ? true : false; break;
            case self::INDEX_FILE: $this->file = $value ? true : false; break;
            case self::INDEX_PING: $this->ping = $value ? true : false; break;
            case self::INDEX_REDIRECT: $this->redirect = $value; break;

            case self::INDEX_I18N:
              if (\Nudlle\has_module('I18n')) {
                try {
                  \Nudlle\Module\I18n::set_locale(array_shift($input));
                } catch (\Nudlle\Exception\WrongData $e) {
                  $is_404 = true;
                }
              } else {
                $is_404 = true;
              }
              break;

            default: $this->params[$key] = $value;
          }
        }
      }

      if ($is_404) {
        $this->module = 'Baseview';
        $this->operation = ContentModule::O_404;
        return;
      }

      if (($this->ajax && $this->ping) || $this->is_redirected()) {
        return;
      } elseif ($this->operation == ContentModule::O_BACK) {
        // We need to go 2 requests back. With only one the exact same page would
        // be rendered.
        try {
          $this->back();
          $this->back();
        } catch (\Nudlle\Exception\Undefined $e) {}
      } elseif (is_null($this->operation) || is_null($this->module) || ($this->ajax && self::CHECK_ID)) {
        if (is_null($this->prev_id)) {
          $this->reset();
        } else {
          try {
            if ($this->widget && $this->ajax) {
              $this->validate($this->prev_id, $this->widget);
              $this->widget = explode('_', $this->widget, 2);
              $wid = count($this->widget) == 2 ? $this->widget[1] : null;
              $this->widget = explode('-', $this->widget[0], 3);
              if (\Nudlle\has_module($this->widget[1])) {
                $this->module = $this->widget[1];
                $this->operation = ContentModule::O_WIDGET;
                $this->widget = [ $this->widget[2], $wid ];
              } else {
                throw new \Nudlle\Exception\App("Unknown module '".$this->widget[1]."'.");
              }
            } elseif (is_null($this->operation) || is_null($this->module)) {
              $params = $this->params;
              $this->restore($this->prev_id);
              $this->params = array_merge($this->params, $params);
            } else {
              $this->validate($this->prev_id);
            }
          } catch (\Nudlle\Exception\Undefined $e) {
            if ($this->ajax) {
              $this->module = 'Baseview';
              $this->operation = ContentModule::O_NOAUTH;
              $this->noauth();
            } else {
              $this->reset();
            }
          }
        }
      }

      if (\Nudlle\has_module('Session')) {
        if (\Nudlle\Module\Session::is_set(self::FORMS_DOMAIN)) {
          $pool = Helper::deep_array_merge(
            \Nudlle\Module\Session::get(self::FORMS_DOMAIN),
            $this->params
          );
          \Nudlle\Module\Session::set(self::FORMS_DOMAIN, $pool);
        } else {
          \Nudlle\Module\Session::set(self::FORMS_DOMAIN, $this->params);
        }
      }
    }

    $this->id = uniqid('', true);
    $this->data[self::INDEX_STATUS] = 1;
  }

  protected function reset() {
    $this->module = $this->operation = $this->widget = $this->id = $this->prev_id = null;
    $this->params = $this->data = $this->errors = $this->info = [];
    $this->save_id = $this->ajax = $this->file = $this->ping = $this->internal = false;
  }

  public function urldecode() {
    foreach ($this->params as &$value) {
      $value = rawurldecode($value);
    }
  }

  public function get_module() {
    return $this->module;
  }

  public function set_module($module) {
    $this->module = $module;
  }

  public function get_operation() {
    return $this->operation;
  }

  public function set_operation($operation) {
    $this->operation = $operation;
  }

  public function get_widget() {
    return $this->widget;
  }

  public function get($key = null) {
    if (is_null($key)) {
      return $this->params;
    }

    if (!array_key_exists($key, $this->params)) {
      throw new \Nudlle\Exception\Undefined('Unknown request parameter: '.$key);
    }
    return $this->params[$key];
  }

  public function set($key, $value = null) {
    $this->params[$key] = $value;
  }

  public function remove($key) {
    if (array_key_exists($key, $this->params)) {
      unset($this->params[$key]);
    }
  }

  public function get_value($key = null) {
    if (is_null($key)) {
      return $this->data;
    }

    if (!array_key_exists($key, $this->data)) {
      throw new \Nudlle\Exception\Undefined('Unknown request data index: '.$key);
    }
    return $this->data[$key];
  }

  public function set_value($key, $value) {
    $this->data[$key] = $value;
  }

  public function remove_value($key) {
    if (array_key_exists($key, $this->data)) {
      unset($this->data[$key]);
    }
  }

  public function has_value($key) {
    return array_key_exists($key, $this->data);
  }

  public function is_set($key) {
    return array_key_exists($key, $this->params);
  }

  public function store() {
    if (!\Nudlle\has_module('Session')) return;

    $data = [];
    $data['module'] = $this->module;
    $data['operation'] = $this->operation;
    $data['params'] = $this->params;
    $data['id'] = $this->id;
    $data['used_widgets'] = $this->used_widgets;
    if (!is_null($this->prev_id)) {
      $data['prev_id'] = $this->prev_id;
    } else {
      $r = clone $this;
      try {
        $r->restore();
        $data['prev_id'] = $r->get_id();
        unset($r);
      } catch (\Nudlle\Exception\Undefined $e) {
        $data['prev_id'] = null;
      }
    }

    try {
      $history = \Nudlle\Module\Session::get(self::HISTORY_DOMAIN);
    } catch (\Nudlle\Exception\Undefined $e) {
      $history = [];
    }

    $history[] = $data;
    if (count($history) > self::HISTORY_SIZE) {
      array_shift($history);
    }
    \Nudlle\Module\Session::set(self::HISTORY_DOMAIN, $history);
  }

  /**
   * To be used with ajax requests: Incoming ajax request A is referring
   * ($this->prev_id) an older non-ajax request N. We shift request N to the
   * first place in the history stack, so that pages that are "alive" keep
   * working even when there is some traffic in (possibly) other browser tab.
   *
   * @return void
   */
  public function shift() {
    if (!\Nudlle\has_module('Session')) return;
    if (!$this->prev_id) return;
    if (!\Nudlle\Module\Session::is_set(self::HISTORY_DOMAIN)) return;

    $history = \Nudlle\Module\Session::get(self::HISTORY_DOMAIN);
    $item = end($history);
    // Don't do anything if the referred request is already the last one.
    if ($item['id'] == $this->prev_id) return;
    $item = prev($history);

    while ($item !== false && $item['id'] != $this->prev_id) {
      $item = prev($history);
    }
    if ($item === false) return;
    $index = key($history);

    $referred = $history[$index];
    unset($history[$index]);
    $history[] = $referred;
    \Nudlle\Module\Session::set(self::HISTORY_DOMAIN, $history);
  }

  protected function search_history($find_only, $id = null, $widget = null) {
    if (($find_only && is_null($id)) || (!is_null($widget) && !$find_only)) {
      throw new \Nudlle\Exception\App('Wrong call of method \Nudlle\Core\Request::search_history()');
    }

    $history = \Nudlle\Module\Session::get(self::HISTORY_DOMAIN);
    if (!is_array($history)) {
      $history = [];
    }

    // The array is iterated from its end (a stack)
    $item = end($history);
    while ($item !== false && (
      (!is_null($id) && $item['id'] != $id)
      || (!is_null($widget) && !array_key_exists($widget, $item['used_widgets']))
    )) {
      $item = prev($history);
    }
    if ($item === false) {
      throw new \Nudlle\Exception\Undefined();
    }

    if (!$find_only) {
      $this->module = $item['module'];
      $this->operation = $item['operation'];
      $this->params = $item['params'];
      $this->id = $item['id'];
      //$this->used_widgets = $item['used_widgets']; // Useless
      $this->prev_id = $item['prev_id'];
    }
  }

  public function restore($id = null) {
    if (!\Nudlle\has_module('Session')) return;

    if (is_null($id)) {
      $id = $this->prev_id;
    }
    $this->search_history(false, $id);
  }

  public function validate($id, $widget = null) {
    if (!\Nudlle\has_module('Session')) return;
    $this->search_history(true, $id, $widget);
  }

  public function copy_data(Request $target) {
    foreach ($this->data as $key => $value) {
      $target->set_value($key, $value);
    }
  }

  public function get_id() {
    return $this->id;
  }

  public function regenerate_id() {
    $this->id = uniqid('', true);
  }

  public function to_json() {
    if ($this->save_id) {
      $this->set_value(self::INDEX_ID, $this->id);
    }
    $this->set_value(self::INDEX_ERRORS, $this->errors);
    $json = json_encode($this->data);
    $this->remove_value(self::INDEX_ERRORS);
    $this->remove_value(self::INDEX_ID);
    return $json;
  }

  public function add_error($text = null) {
    if (!is_null($text)) {
      $this->errors[] = $text;
    }
    $this->data[self::INDEX_STATUS] = 0;
  }

  public function get_errors() {
    if (count($this->errors) > 0) {
      return $this->errors;
    } else {
      throw new \Nudlle\Exception\Undefined();
    }
  }

  public function add_info($text) {
    $this->info[] = $text;
  }

  public function get_info() {
    if (count($this->info) > 0) {
      return $this->info;
    } else {
      throw new \Nudlle\Exception\Undefined();
    }
  }

  public function set_widgets($widget_map) {
    $this->used_widgets = $widget_map;
  }

  public function is_ajax() {
    return $this->ajax;
  }

  public function is_file() {
    return $this->file;
  }

  public function no_file() {
    $this->file = false;
  }

  public function is_ping() {
    return $this->ping;
  }

  public function is_internal() {
    return $this->internal;
  }

  public function is_required() {
    return $this->save_id;
  }

  public function back() {
    do {
      $this->restore($this->prev_id);
    } while ($this->operation == ContentModule::O_NOAUTH);
  }

  public function noauth() {
    $this->data[self::INDEX_NOAUTH] = 1;
    $this->save_id = true;
  }

  public function set_redirect($url) {
    $this->redirect = $url;
  }

  public function get_redirect() {
    if (is_null($this->redirect)) throw new \Nudlle\Exception\Undefined();
    return $this->redirect;
  }

  public function is_redirected() {
    return !is_null($this->redirect);
  }

  public function is_post_overflow() {
    return $this->post_overflow;
  }

  private static function detect_post_overflow() {
    $limit = ini_get('post_max_size');
    if (!preg_match('/^([0-9]+)(.)?$/', $limit, $m)) return;
    $mul = ($m[1] == 'M' ? 1048576 : ($m[1] == 'K' ? 1024 : ($m[1] == 'G' ? 1073741824 : 1)));
    return $_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && $_SERVER['CONTENT_LENGTH'] > $mul * (int)$m[0];
  }

}

?>
