<?php
namespace Nudlle\Iface;

interface Session {

  public static function init($id = null);
  public static function finish();
  public static function destroy();

  public static function get_id();
  public static function change_id($id = null, $copy = false);

  public static function get($key = null);
  public static function set($key = null, $value);
  public static function is_set($key);
  public static function clear($key = null);

  public function __construct($domain = null);

  public function dget($key = null);
  public function dset($key = null, $value);
  public function dis_set($key);
  public function dclear($key = null);

}

?>
