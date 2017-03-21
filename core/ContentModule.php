<?php
namespace Nudlle\Core;

abstract class ContentModule extends Module {

  const FILE_CHUNK_SIZE = 1048576; // 1 MiB
  const FILE_TYPE = '_filetype';
  const FILE_NAME = '_filename';
  const FILE_SOURCE = '_filesource';

  // Special system operations
  const O_LOGIN = '_login';
  const O_LOGOUT = '_logout';
  const O_ERROR = '_error';
  const O_ERROR_FILE = '_error_file';
  const O_404 = '_404';
  const O_NOAUTH = '_noauth';
  const O_BACK = '_back';
  const O_PING = '_ping';
  const O_WIDGET = 'widget';

  // General reusable operations
  const O_HOMEPAGE = 'home';
  const O_SUMMARY = 'summary';
  const O_CREATE = 'create';
  const O_UPDATE = 'update';
  const O_DELETE = 'delete';
  const O_DETAIL = 'detail';
  const O_TOGGLE = 'toggle';
  const O_MOVE = 'move';

  const DEFAULT_WIDGET = 'Screen';

  // Key: operation
  // Value: widget name
  //   a) scalar: body widget name
  //   b) array: [ screen widget name, body widget name, INNER ]
  //
  // When screen widget is not set, self::DEFAULT_WIDGET of Baseview module is
  // used.
  //
  // When widget is missing in the map, the widget can be used directly without
  // running any operation (only for O_WIDGET operation).
  //
  // Widget location:
  //   a) Current module widget's dir: base name (no path)
  //   b) Anywhere else: full path
  protected $widget_map = [];

  protected $request;

  protected static $rewrite_main_operation = self::O_HOMEPAGE;

  public static function load($class_name) {
    try {
      parent::load($class_name);
    } catch (\Nudlle\Exception\Load $e) {
      $path = explode('\\', $class_name);
      if (count($path) == 2 && $path[0] == 'Widget') {
        $file_name = static::get_widgets_path().'/'.$path[1].'.php';
      } else {
        throw new \Nudlle\Exception\Load($class_name);
      }

      if (file_exists($file_name)) {
        require_once $file_name;
      } else {
        throw new \Nudlle\Exception\Load($class_name);
      }
    }
  }

  final public function run_operation(Request $request) {
    // TODO: Authorization
    $this->request = $request;

    if (\Nudlle\has_module('I18n')) {
      try {
        \Nudlle\Module\I18n::get_locale();
      } catch (\Nudlle\Exception\Undefined $e) {
        $this->redirect($request->get_module(), $request->get_operation(), $request->get());
        return;
      }
    }

    $this->run_model();
    $this->run_view();
  }

  protected function run_model() {
    if (!\Nudlle\has_module('Database')) return;

    // TODO: edit?
    switch ($this->request->get_operation()) {
      case self::O_DELETE:
        $manager = \Nudlle\Module\Database::get_manager(self::get_table(), $this->db);
        $filter = $manager->derive_filter()->extend([ 'id', '=', $this->request->get('id') ]);
        $manager->delete($filter);
        break;

      case self::O_TOGGLE:
        $table = self::get_table();
        $column = $this->request->get('column');
        if (!$table->has_column($column)) {
          throw new \Nudlle\Exception\Model("Unknown column '$column'.");
        }
        if ($table->get_type($column) !== \Nudlle\Module\Database::BOOL) {
          throw new \Nudlle\Exception\Model("Only a bool-typed column can be toggled.");
        }

        $record = \Nudlle\Module\Database::get_record($table, $this->request, $this->db);
        $new_value = $record->get($column) ? false : true;
        $record->set($column, $new_value);
        $record->save();
        $this->request->set_value($column, $new_value);
        break;

      case self::O_MOVE:
        $record = \Nudlle\Module\Database::get_record(self::get_table(), $this->request, $this->db);
        $record->move($this->request->get('amount'));
        break;
    }
  }

  private function run_view() {
    if ($this->request->is_redirected()) return;

    if ($this->request->is_ajax()) {
      if ($this->request->get_operation() == self::O_WIDGET) {
        $this->update_widget();
      }
      $this->write_json();
    } elseif ($this->request->is_file()) {
      $this->write_file();
    } else {
      $this->write_markup();
    }
  }

  private function update_widget() {
    list($widget_name, $widget_id) = $this->request->get_widget();

    $widget_name = '\\Nudlle\\Module\\'.static::get_name().'\\Widget\\'.$widget_name;
    $widget = new $widget_name($this->request, $this->db);
    if (!is_null($widget_id)) {
      $widget->set_id($widget_id);
    }

    if (\Nudlle\has_module('Session') && $widget->is_wrapped()) {
      $p = clone $this->request;
      $p->back();
      $store_pid = str_replace('.', ',', $p->get_id());
      $key = Widget::DOMAIN.'.'.$widget->get_label().'.'.$store_pid;

      if (\Nudlle\Module\Session::is_set($key)) {
        $data = \Nudlle\Module\Session::get($key);
        foreach ($data as $attr => $value) {
          if (!$this->request->is_set($attr)) {
            $this->request->set($attr, $value);
          }
        }
      }
    }

    ob_start();
    $widget->render();
    $buff = ob_get_contents();
    ob_end_clean();

    $this->request->set_value(Request::INDEX_FRAGMENT, $buff);
  }

  private function write_json() {
    header('Content-type: application/json; charset='.\Nudlle\ENCODING);
    echo $this->request->to_json();
  }

  protected function write_file() {
    header('Content-Description: File Transfer');
    header('Content-Type: '.$this->request->get_value(self::FILE_TYPE));
    header('Content-Disposition: attachment; filename='.$this->request->get_value(self::FILE_NAME));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: '.filesize($this->request->get_value(self::FILE_SOURCE)));

    while (ob_get_level()) {
      ob_end_clean();
    }

    $buffer = '';
    $handle = fopen($this->request->get_value(self::FILE_SOURCE), 'rb');
    if ($handle === false) {
      return false;
    }
    while (!feof($handle)) {
      $buffer = fread($handle, self::FILE_CHUNK_SIZE);
      echo $buffer;
      flush();
    }
    fclose($handle);
  }

  protected function write_markup() {
    $operation = $this->request->get_operation();
    if (!array_key_exists($operation, $this->widget_map)) {
      throw new \Nudlle\Exception\Code404();
    }

    if (is_scalar($this->widget_map[$operation])) {
      $screen = '\\Nudlle\\Module\\Baseview\\Widget\\'.self::DEFAULT_WIDGET;
      $body = $this->widget_map[$operation];
    } else {
      $screen = $this->widget_map[$operation][0];
      $body = $this->widget_map[$operation][1];
    }

    foreach ([ 'body', 'screen' ] as $var) {
      if (${$var} && substr(${$var}, 0, 1) != '\\') {
        ${$var} = '\\Nudlle\\Module\\'.static::get_name().'\\Widget\\'.${$var};
      }
    }

    $screen_widget = new $screen($this->request, $this->db);
    if (!$screen_widget->is_screen()) {
      throw new \Nudlle\Exception\App("Widget '$screen' is not a screen widget.");
    }

    if ($body) {
      $body_widget = new $body($this->request, $this->db);
      $screen_widget->add_subwidget(Widget::BODY, $body_widget);
    }

    $level = ob_get_level();
    ob_start();
    try {
      $screen_widget->render();
      ob_end_flush();
    } catch (\Throwable $e) {
      while (ob_get_level() > $level) {
        ob_end_clean();
      }
      throw $e;
    }
  }

  public static function get_rewrite_label() {
    return strtolower(static::get_name());
  }

  public static function process_rewrite_params($params) {
    if (static::get_rewrite_label() == $params[0]) {
      array_shift($params); // module name
    }
    $operation = array_shift($params);
    $data = [];
    $i = 1;
    foreach ($params as $value) {
      $data['param'.$i++] = $value;
    }

    return [ $operation, $data ];
  }

  public static function check_auth($operation) {
    // TODO
    return true;
  }

  public static function get_link($operation, $params = null) {

    // TODO: Authorization

    if (is_null($params)) {
      $params = [];
    } elseif (!is_array($params)) {
      $params = [ $params ];
    }

    if (\Nudlle\has_module('Rewrite')) {
      if (\Nudlle\has_module('I18n')) {
        $link = \Nudlle\Module\I18n::get_locale().'/';
      } else {
        $link = '';
      }

      $link .= static::get_rewrite_label();
      if (static::$rewrite_main_operation != $operation) {
        $link .= '/'.$operation;
        foreach ($params as $value) {
          $link .= '/'.rawurlencode($value);
        }
      }
    } else {
      $link = 'index.php?'.Request::INDEX_MODULE.'='.static::get_name();
      $link .= '&amp;'.Request::INDEX_OPERATION.'='.$operation;
      if (\Nudlle\has_module('I18n')) {
        $link .= '&amp;'.Request::INDEX_I18N.'='.\Nudlle\Module\I18n::get_locale();
      }
      foreach ($params as $key => $value) {
        $link .= '&amp;'.rawurlencode($key).'='.rawurlencode($value);
      }
    }

    return $link;
  }

  protected function redirect($module = null, $operation = null, $params = null) {
    if (is_null($module) || is_null($operation)) {
      list($module, $operation) = \Nudlle\translate_default();
    }
    $link = call_user_func('\\Nudlle\\Module\\'.$module.'::get_link', $operation, $params);
    $this->request->set_redirect($link);
  }

}

?>
