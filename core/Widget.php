<?php
namespace Nudlle\Core;

abstract class Widget {

  const N = "\n";
  const INDENT_WIDTH = 2;

  const HTML4_S = 1;
  const HTML4_T = 2;
  const HTML5 = 3;
  const XHTML10_S = 4;
  const XHTML10_T = 5;
  const XHTML11 = 6;

  const DOMAIN = 'widgets';

  const BODY = 'body';
  const NO_ID = '_';

  const JS_TASKS = 'tasks';
  const JS_LOADER = 'loader';

  const SOURCE_DATA = 1;
  const SOURCE_REQUEST = 2;
  const SOURCE_SESSION = 3;

  const BASE_AUTO = '_auto';

  static private $empty_elements = [
    'area' => 1, 'base' => 1, 'basefont' => 1, 'br' => 1, 'col' => 1,
    'command' => 1, 'embed' => 1, 'frame' => 1, 'hr' => 1, 'img' => 1,
    'input' => 1, 'keygen' => 1, 'link' => 1, 'meta' => 1, 'param' => 1,
    'source' => 1, 'track' => 1, 'wbr' => 1
  ];

  // Structure: node name -> node ID ('_' when not set) -> attribute name -> value
  // Value can be either scalar or array
  private $node_attributes = [];

  private $js_files = [];
  private $js_data = [];
  private $css_files = [];
  private $ext_subwidgets = [];
  private $subwidget_map = [];
  private $radio_values = [];

  protected $request;
  protected $db;
  protected $data;

  protected $template = null;
  protected $screen = false;

  // Wrap the content into <div class="widget" id="..."></div> when true
  // Ignored when $this->screen == true
  protected $wrap = false;

  // When set to positive integer N, the widget updates itself automatically
  // every N seconds. Ignored when $this->wrap == false
  protected $refresh = false;

  // Not used unless $this->screen == true
  protected $doctype = self::HTML5;

  protected $head_data = [];
  protected $module = null;
  protected $name = null;
  protected $id = null;
  protected $base = null;

  public function __construct(Request $request, \Nudlle\Module\Database\Wrapper $db = null) {
    $this->request = $request;
    $this->data = $request->get_value();
    $this->db = $db;
    $path = explode('\\', get_class($this));
    $this->module = $path[2];
    $this->name = end($path);

    if (\Nudlle\has_module('Rewrite')) {
      try {
        $base = \Nudlle\Module\Rewrite::get_cfg('base');
        if (!array_key_exists('scheme', parse_url($base))) {
          $base = (isset($_SERVER['HTTPS']) ? 'https:' : 'http:').$base;
        }
        $this->set_base($base);
      } catch (\Nudlle\Exception\Undefined $e) {}
    }
  }

  public function __destruct() {
    if ($this->screen) {
      $this->request->set_widgets($this->subwidget_map);
    }

    // '.' is not allowed in session keys -> change it to ','
    if (\Nudlle\has_module('Session') && $this->is_wrapped()) {
      $s = new \Nudlle\Module\Session(self::DOMAIN);

      if (!$this->request->is_ajax()) {
        if ($s->dis_set($this->get_label())) {
          $p = new Request();
          foreach (array_keys($s->dget($this->get_label())) as $pid) {
            try {
              $p->restore(str_replace(',', '.', $pid));
            } catch (\Nudlle\Exception\Undefined $e) {
              $s->dclear($this->get_label().'.'.$pid);
            }
          }
        }

        $store_pid = $this->request->get_id();
      } else {
        $p = clone $this->request;
        $p->back();
        $store_pid = $p->get_id();
      }

      $store_pid = str_replace('.', ',', $store_pid);
      if (count($this->request->get()) > 0) {
        $s->dset($this->get_label().'.'.$store_pid, $this->request->get());
      }
    }
  }

  // Recursive htmlspecialchars()
  private static function sanitize($input) {
    if (!is_array($input)) {
      $input = htmlspecialchars($input, ENT_QUOTES, '', false);
    } else {
      foreach ($input as &$item) {
        $item = self::sanitize($item);
      }
    }
    return $input;
  }

  private function get($path, $source = self::SOURCE_DATA) {
    if (!$path || !is_string($path)) {
      throw new \Nudlle\Exception\App("Invalid parameter 'path': non-empty string expected.");
    }
    $path = Helper::tokenize_path($path);

    if ($source == self::SOURCE_DATA) {
      if (!array_key_exists($path[0], $this->data)) {
        throw new \Nudlle\Exception\Undefined();
      }
      $data = $this->data[$path[0]];
    } elseif ($source == self::SOURCE_REQUEST) {
      if (!$this->request->is_set($path[0])) {
        throw new \Nudlle\Exception\Undefined();
      }
      $data = $this->request->get($path[0]);
    } elseif ($source == self::SOURCE_SESSION) {
      if (!\Nudlle\has_module('Session')) {
        throw new \Nudlle\Exception\App('Session source needs Session module (congratulation for hitting this one!)');
      }
      if (!\Nudlle\Module\Session::is_set(Request::FORMS_DOMAIN.'.'.$path[0])) {
        throw new \Nudlle\Exception\Undefined();
      }
      $data = \Nudlle\Module\Session::get(Request::FORMS_DOMAIN.'.'.$path[0]);
    } else {
      throw new \Nudlle\Exception\Undefined();
    }

    for ($i = 1; $i < count($path); $i++) {
      if (!array_key_exists($path[$i], $data)) {
        throw new \Nudlle\Exception\Undefined();
      }
      $data = $data[$path[$i]];
    }

    if ($source == self::SOURCE_SESSION) {
      // So that we use it only once
      \Nudlle\Module\Session::clear(Request::FORMS_DOMAIN.'.'.implode('.', $path));
    }

    return $data;
  }

  final protected function E($path) {
    try {
      $this->get($path);
    } catch (\Nudlle\Exception\Undefined $e) {
      return false;
    }
    return true;
  }

  final protected function D($path, $sanitize = true) {
    try {
      $data = $this->get($path);
    } catch (\Nudlle\Exception\Undefined $e) {
      return '';
    }
    return $sanitize ? self::sanitize($data) : $data;
  }

  final protected function P($path, $sanitize = true) {
    try {
      $data = $this->get($path, self::SOURCE_REQUEST);
    } catch (\Nudlle\Exception\Undefined $e) {
      return '';
    }
    return $sanitize ? self::sanitize($data) : $data;
  }

  final protected function F($path, $sanitize = true) {
    try {
      $data = $this->get($path, self::SOURCE_SESSION);
    } catch (\Nudlle\Exception\Undefined $e) {
      return '';
    }
    return $sanitize ? self::sanitize($data) : $data;
  }

  final protected function S($data) {
    return self::sanitize($data);
  }

  final protected function J($path, $value) {
    $data = &$this->js_data;
    $path = Helper::tokenize_path($path);
    $last_key = array_pop($path);
    foreach ($path as $token) {
      if (!array_key_exists($token, $data)) {
        $data[$token] = [];
      }
      $data = &$data[$token];
    }
    $data[$last_key] = $value;
  }

  // TODO
  final protected function R($role) {
    return false;
  }

  final protected function I($filename, $module = null) {
    $path = call_user_func('\\Nudlle\\Module\\'.$this->get_module($module).'::get_images_path');
    return self::sanitize($path.'/'.$filename);
  }

  final protected function L() {
    \Nudlle\check_module('I18n');
    return \Nudlle\Module\I18n::get_locale();
  }

  final protected function i18n($label) {
    \Nudlle\check_module('I18n');
    return \Nudlle\Module\I18n::translate($label);
  }

  final protected function C($name, $value = null) {
    if ($value === null) {
      // Checkbox
      $value = 'on';
      $input = $this->F($name);
    } else {
      // Radiobutton
      if (!array_key_exists($name, $this->radio_values)) {
        $this->radio_values[$name] = $this->F($name);
      }
      $input = $this->radio_values[$name];
    }

    if ($input == $value) {
      return ' checked="checked"';
    } else {
      return '';
    }
  }

  final protected function A() {
    if (\Nudlle\has_module('Auth')) {
      return \Nudlle\Module\Auth::is_anonymous();
    } else {
      throw new \Nudlle\Exception\App("Required module 'Auth' not found.");
    }
  }

  final protected function get_module($module = null) {
    if (is_null($module)) {
      return $this->module;
    }

    if (!\Nudlle\has_module($module)) {
      throw new \Nudlle\Exception\App("Unknown module '$module'.");
    }
    return $module;
  }

  final protected function T($node_name, $id = null, $use_id = true) {
    if (is_null($id)) {
      $id_index = self::NO_ID;
    } else {
      $id_index = $id;
    }

    $attributes = [];

    if (array_key_exists($node_name, $this->node_attributes) &&
        array_key_exists($id_index, $this->node_attributes[$node_name])) {
      $attributes = $this->node_attributes[$node_name][$id_index];
    }
    if (!is_null($id) && $use_id) $attributes['id'] = $id;

    if (in_array($this->doctype, [ self::XHTML10_S, self::XHTML10_T, self::XHTML11 ])
        && array_key_exists($node_name, self::$empty_elements)) {
      $close = true;
    } else {
      $close = false;
    }

    return self::get_html_code($node_name, $attributes, $close);
  }

  public function set_base($href = self::BASE_AUTO) {
    if ($href == self::BASE_AUTO) {
      $this->base = Helper::get_location();
    } elseif ($href !== null) {
      $this->base = Helper::get_location($href);
    } else {
      $this->base = null;
    }
  }

  final protected function get_link($operation, $params = null, $module = null) {
    $module = $this->get_module($module);
    $link = call_user_func('\\Nudlle\\Module\\'.$module.'::get_link', $operation, $params);
    return $link;
  }

  final protected function get_link_repeat($params = [], Request $r = null, $ref = false) {
    if (is_null($r)) $r = $this->request;
    if ($r->is_ajax()) {
      $r = clone $r;
      $r->back();
    }

    if ($ref) {
      if (\Nudlle\has_module('Rewrite')) {
        $link = $r->get_id();
        $separ = '?';
      } else {
        $link = 'index.php?'.Request::INDEX_ID.'='.$r->get_id();
        $separ = '&amp;';
      }

      if (!empty($params)) {
        $x = [];
        foreach ($params as $key => $value) {
          $x[] = rawurlencode($key).'='.rawurlencode($value);
        }
        $link .= $separ.implode('&amp;', $x);
      }
    } else {
      $link = $this->get_link($r->get_operation(), array_merge($r->get(), $params), $r->get_module());
    }

    return $link;
  }

  final protected function get_link_back() {
    $r = clone $this->request;
    try {
      $r->back();
      return $this->get_link_repeat([], $r);
    } catch (\Nudlle\Exception\Undefined $e) {
      return '';
    }
  }

  protected static function get_indent_space($size = 1) {
    $space = '';
    for ($i = 0; $i < $size * self::INDENT_WIDTH; $i++) {
      $space .= ' ';
    }
    return $space;
  }

  protected static function write_buffer($rows, $indent = 0) {
    if ($indent > 0) {
      self::indent_buffer($rows, $indent);
    }
    foreach ($rows as $row) {
      echo $row.self::N;
    }
  }

  protected static function indent_buffer(&$rows, $amount = 0) {
    if ($amount <= 0) return;
    $space = self::get_indent_space($amount);
    foreach ($rows as &$row) {
      $row = $space.$row;
    }
  }

  protected static function shorten($text, $length) {
    if (mb_strlen($text) <= $length) {
      return $text;
    }

    $text = mb_substr($text, 0, $length);
    if (preg_match('/^(.*)\b\W/u', $text, $matches)) {
      return $matches[1].'&hellip;';
    } else {
      return $text;
    }
  }

  protected static function get_html_code($name, $attributes, $close = false) {
    $output = '<'.self::sanitize($name);

    foreach ($attributes as $key => $value) {
      if ($key == 'data') {
        foreach ($value as $key2 => $value2) {
          $output .= ' data-'.self::sanitize($key2).'="'.self::sanitize($value2).'"';
        }
      } elseif ($key == 'json') {
        foreach ($value as $key2 => $value2) {
          $output .= ' data-'.self::sanitize($key2)."='".json_encode($value2, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)."'";
        }
      } elseif ($key == 'class') {
        if (!is_array($value)) $value = [ $value ];
        $output .= ' class="'.self::sanitize(implode(' ', $value)).'"';
      } elseif ($key == 'style') {
        $parts = [];
        foreach ($value as $key2 => $value2) {
          $parts[] = $key2.': '.$value2;
        }
        $output .= ' style="'.self::sanitize(implode('; ', $parts)).'"';
      } else {
        $output .= ' '.self::sanitize($key);
        if (!is_null($value)) {
          $output .= '="'.self::sanitize($value).'"';
        }
      }
    }

    if ($close) {
      $output .= '/';
    }
    $output .= '>';

    return $output;
  }

  public function get_head_data() {
    return $this->head_data;
  }

  public function get_js_files() {
    return $this->js_files;
  }

  protected function add_js_file($file_name, $module = null) {
    if (preg_match('/^https?:\/\//', $file_name)) {
      $module = '';
    } else {
      $module = $this->get_module($module);
    }
    $this->js_files[$module.'-'.$file_name] = true;
  }

  protected function add_meta($attributes) {
    $this->head_data['meta'][] = $attributes;
  }

  protected function add_link($attributes) {
    $this->head_data['link'][] = $attributes;
  }

  protected function set_icon($icon) {
    $this->head_data['icon'] = $icon;
  }

  protected function set_title($title) {
    $this->head_data['title'] = $title;
  }

  public function get_css_files() {
    return $this->css_files;
  }

  protected function add_css_file($file_name, $module = null, $media = null) {
    if (preg_match('/^https?:\/\//', $file_name)) {
      $module = '';
    } else {
      $module = $this->get_module($module);
    }
    $this->css_files[$module.'-'.$file_name] = $media;
  }

  public function get_js_data() {
    return $this->js_data;
  }

  public function is_screen() {
    return $this->screen;
  }

  public function is_wrapped() {
    return $this->wrap && !$this->screen;
  }

  public function get_refresh() {
    return intval($this->refresh);
  }

  public function get_subwidgets() {
    return $this->subwidget_map;
  }

  public function set_id($id) {
    if (preg_match('/[\s._]/', $id)) {
      throw new \Nudlle\Exception\App("Whitespaces, periods and underscores are not allowed in a widget's ID.");
    }
    $this->id = $id;
  }

  public function get_id() {
    return $this->id;
  }

  private static function normalize_attribute($attr, $value) {
    if (!in_array($attr, [ 'data', 'json', 'class', 'style' ])) {
      if (!is_scalar($attr)) {
        throw new \Nudlle\Exception\App('Parameter $attr allows only scalar values.');
      }
      if (!is_scalar($value)) {
        throw new \Nudlle\Exception\App("Invalid '$attr' attribute: a scalar value expected.");
      }
    } elseif ($attr == 'class') {
      if (!is_array($value)) $value = [ $value ];
      foreach ($value as $item) {
        if (!is_scalar($item)) {
          throw new \Nudlle\Exception\App("Invalid 'class' attribute: a scalar value (or an array of scalar values) expected.");
        }
      }
    } else {
      if (!is_array($value)) {
        throw new \Nudlle\Exception\App("Invalid '$attr' attribute: an associative array (a map) expected.");
      }
      foreach ($value as $key => $value2) {
        if (!is_scalar($key)) {
          throw new \Nudlle\Exception\App("Invalid '$attr' attribute: only scalar values are allowed as keys.");
        }
        if ($attr != 'json' && !is_scalar($value2)) {
          throw new \Nudlle\Exception\App("Invalid '$attr' attribute: only scalars are allowed as values.");
        }
      }
    }

    return $value;
  }

  protected function add_attribute($node, $attr, $value, $id = null) {
    if (is_null($id)) $id = self::NO_ID;
    $value = self::normalize_attribute($attr, $value);

    if (   in_array($attr, [ 'data', 'json', 'class', 'style' ])
        && array_key_exists($node, $this->node_attributes)
        && array_key_exists($id, $this->node_attributes[$node])
        && array_key_exists($attr, $this->node_attributes[$node][$id])
    ) {
      $value = Helper::deep_array_merge($this->node_attributes[$node][$id][$attr], $value);
    }

    $this->node_attributes[$node][$id][$attr] = $value;
  }

  protected function set_attribute($node, $attr, $value, $id = null) {
    if (is_null($id)) $id = self::NO_ID;
    $value = self::normalize_attribute($attr, $value);
    $this->node_attributes[$node][$id][$attr] = $value;
  }

  protected function unset_attribute($node, $attr, $id = null) {
    if (is_null($id)) $id = self::NO_ID;
    unset($this->node_attributes[$node][$id][$attr]);
  }

  // The Widget label must follow this pattern:
  // Widget-<module name>-<widget name>[_<widget ID>]
  // The label is obtained automatically by parsing the widget's class name that
  // should follow this pattern:
  // \Nudlle\Module\<module name>\Widget\<widget name>
  // The widget ID must be set explicitly and is not required unless multiple
  // widgets of the same class occur in the page.
  public function get_label() {
    $label = 'Widget-'.$this->module.'-'.$this->name;
    if (!is_null($this->id)) {
      $label .= '_'.$this->id;
    }
    return $label;
  }

  protected function get_table($table_name = null) {
    return call_user_func('\\Nudlle\\Module\\'.$this->module.'::get_table', $table_name);
  }

  // For adding external subwidgets to be used later.
  // Called from an external code before the output.
  public function add_subwidget($key, Widget $subwidget) {
    if (!$key) {
      throw new \Nudlle\Exception\App("A proper key for the subwidget must be specified.");
    }
    if ($subwidget->is_screen()) {
      throw new \Nudlle\Exception\App("A screen widget can not be added as a subwidget.");
    }
    $this->ext_subwidgets[$key] = $subwidget;
  }

  // For adding a subwidget's output to the output of the current widget
  // Called internally during the output (eg. from a template)
  protected function insert_subwidget($subwidget, $indent = 0) {
    if (is_string($subwidget)) {
      if (!array_key_exists($subwidget, $this->ext_subwidgets)) {
        throw new \Nudlle\Exception\App("Undefined subwidget '$subwidget'.");
      }
      $subwidget = $this->ext_subwidgets[$subwidget];
    }
    if (!($subwidget instanceof Widget)) {
      throw new \Nudlle\Exception\App("The given subwidget is not an instance of the Widget class.");
    }
    if ($subwidget->is_screen()) {
      throw new \Nudlle\Exception\App("A screen widget can not be inserted as a subwidget.");
    }

    $int = array_intersect_key($this->subwidget_map, $subwidget->get_subwidgets());
    if ($subwidget->is_wrapped()) {
      $label = $subwidget->get_label();
      if (array_key_exists($label, $this->subwidget_map) || $label == $this->get_label()) {
        $int[$label] = true;
      }
    }

    if (count($int) > 0) {
      $list = "'".implode("', '", array_keys($int))."'";
      throw new \Nudlle\Exception\App("Multiple occurrence of widgets $list.");
    }

    ob_start();
    $subwidget->render();
    $buff = ob_get_contents();
    ob_end_clean();
    $buff = explode(self::N, $buff);

    if ($subwidget->is_wrapped()) {
      $conf = [ 'class' => 'widget', 'id' => $label ];
      $refresh = $subwidget->get_refresh();
      if ($refresh) $conf['data'] = [ 'refresh' => $refresh ];

      self::indent_buffer($buff, 1);
      array_unshift($buff, self::get_html_code('div', $conf));
      array_push($buff, '</div>');
    }

    self::write_buffer($buff, $indent);

    $this->head_data = Helper::deep_array_merge($this->head_data, $subwidget->get_head_data());
    $this->css_files = array_merge($this->css_files, $subwidget->get_css_files());
    $this->js_files = array_merge($this->js_files, $subwidget->get_js_files());
    $this->js_data = Helper::deep_array_merge($this->js_data, $subwidget->get_js_data());
    $this->subwidget_map = array_merge($this->subwidget_map, $subwidget->get_subwidgets());
    if ($subwidget->is_wrapped()) $this->subwidget_map[$label] = true;
  }

  protected function prepare_data() {
    // method is not compulsory, can be left empty (eg. static pages)
  }

  protected function output() {
    if (is_null($this->template)) {
      throw new \Nudlle\Exception\App("Neither template nor output method specified.");
    }

    $path = call_user_func('\\Nudlle\\Module\\'.$this->module.'::get_templates_path');
    $file_name = $path.'/'.$this->template.'.php';
    if (!file_exists($file_name)) {
      throw new \Nudlle\Exception\App("Template '$file_name' does not exist.");
    }
    if (!$this->screen) {
      require $file_name;
      return;
    }

    ob_start();
    require $file_name;
    $buff = ob_get_contents();
    ob_end_clean();

    if (\Nudlle\has_module('Js')) {
      if (count(\Nudlle\Module\Js::get_required_libs())) {
        $this->js_data[Request::INDEX_ID] = $this->request->get_id();
        if (\Nudlle\has_module('Auth', 'Session', 'Profile')) {
          $path = \Nudlle\Module\Profile::DOMAIN.'.'.\Nudlle\Module\Auth::SESSION_PING;
          if (\Nudlle\Module\Session::is_set($path)) {
            $this->js_data[Request::INDEX_PING] = true;
          }
        }
      }

      $r = new Request();
      foreach ($r->get_value() as $key => $foo) {
        $r->remove_value($key);
      }
      foreach ($this->js_data as $key => $value) {
        $r->set_value($key, $value);
      }
      $script = new \Nudlle\Module\Js\Widget\Script($r);
      $r = null;

      ob_start();
      $script->render();
      $this->css_files = array_merge($script->get_css_files(), $this->css_files);
      $this->js_files = array_merge($script->get_js_files(), $this->js_files);
      $script = ob_get_contents();
      ob_end_clean();
      $script = explode(self::N, $script);
    } else {
      $script = [];
    }

    $space = self::get_indent_space();
    self::write_buffer($this->get_doctype());

    $lang = null;
    if (\Nudlle\has_module('I18n')) {
      $lang = \Nudlle\Module\I18n::get_language();
    } else {
      $lang = substr(\Nudlle\LOCALE, 0, strpos(\Nudlle\LOCALE, '_'));
    }
    $this->set_attribute('html', 'lang', $lang);

    echo $this->T('html').self::N;
    echo $space.'<head>'.self::N;
    self::write_buffer($this->get_head(), 2);
    echo $space.'</head>'.self::N;
    echo $space.$this->T('body').self::N;
    self::write_buffer(explode(self::N, $buff), 2);
    self::write_buffer($this->get_script($script), 2);
    echo $space.'</body>'.self::N;
    echo '</html>'.self::N;
  }

  public function render() {
    $this->prepare_data();
    $this->output();
  }

  protected function get_doctype() {
    $buff = [];
    if (in_array($this->doctype, [ self::XHTML10_S, self::XHTML10_T, self::XHTML11 ])) {
      $buff[] = '<?xml version="1.0" encoding="'.\Nudlle\ENCODING.'"?>';
    }
    switch ($this->doctype) {
      case self::HTML4_S:
        $buff[] = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">';
        break;
      case self::HTML4_T:
        $buff[] = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">';
        break;
      case self::HTML5:
        $buff[] = '<!DOCTYPE HTML>';
        break;
      case self::XHTML10_S:
        $buff[] = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
        break;
      case self::XHTML10_T:
        $buff[] = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
        break;
      case self::XHTML11:
        $buff[] = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">';
        break;

      default: throw new \Nudlle\Exception\App('Unknown Doctype.');
    }

    return $buff;
  }

  protected function get_head() {
    $buff = [];

    if (in_array($this->doctype, [ self::XHTML10_S, self::XHTML10_T, self::XHTML11 ])) {
      $ct = 'application/xhtml+xml';
      $close = ' />';
      $close_bool = true;
    } else {
      $ct = 'text/html';
      $close = '>';
      $close_bool = false;
    }
    if ($this->doctype == self::HTML5) {
      $buff[] = '<meta charset="'.\Nudlle\ENCODING.'"'.$close;
    } else {
      $buff[] = '<meta http-equiv="Content-Type" content="'.$ct.'; charset='.\Nudlle\ENCODING.'"'.$close;
    }

    if ($this->base !== null) {
      $buff[] = '<base href="'.self::sanitize($this->base).'"'.$close;
    }

    if (array_key_exists('title', $this->head_data)) {
      $buff[] = '<title>'.self::sanitize($this->head_data['title']).'</title>';
    }
    if (array_key_exists('icon', $this->head_data)) {
      $icon = $this->head_data['icon'];
      $path = call_user_func('\\Nudlle\\Module\\'.$this->module.'::get_images_path');
      if (!is_array($icon) || !array_key_exists('type', $icon) || !array_key_exists('file', $icon)) {
        throw new \Nudlle\Exception\App('Invalid icon definition.');
      }
      $buff[] = '<link rel="shortcut icon" type="'.self::sanitize($icon['type']).'" href="'.self::sanitize($path.'/'.$icon['file']).'"'.$close;
    }
    foreach (['meta', 'link'] as $node) {
      if (array_key_exists($node, $this->head_data)) {
        foreach ($this->head_data[$node] as $item) {
          $buff[] = self::get_html_code($node, $item, $close_bool);
        }
      }
    }

    foreach ($this->css_files as $file => $media) {
      list($module, $file) = explode('-', $file, 2);
      if ($module) {
        $path = call_user_func('\\Nudlle\\Module\\'.$module.'::get_css_path');
        $file = $path.'/'.$file.'.css';
      }
      $file = ' href="'.self::sanitize($file).'"';
      $media = $media ? ' media="'.self::sanitize($media).'"' : '';
      $buff[] = '<link rel="stylesheet" type="text/css"'.$file.$media.$close;
    }

    return $buff;
  }

  protected function get_script($buff) {
    foreach ($this->js_files as $file => $foo) {
      list($module, $file) = explode('-', $file, 2);
      if ($module) {
        $path = call_user_func('\\Nudlle\\Module\\'.$module.'::get_js_path');
        $file = $path.'/'.$file.'.js';
      }
      $buff[] = '<script type="text/javascript" src="'.self::sanitize($file).'"></script>';
    }

    return $buff;
  }

}

?>
