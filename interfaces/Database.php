<?php
namespace Nudlle\Iface;

interface Database {

  public static function get_wrapper();

  public static function get_record(
    \Nudlle\Module\Database\Table $table,
    $key,
    \Nudlle\Module\Database\Wrapper $db = null
  );

  public static function get_collector(
    \Nudlle\Module\Database\Table $table,
    \Nudlle\Module\Database\Wrapper $db = null
  );

  public static function get_manager(
    \Nudlle\Module\Database\Table $table,
    \Nudlle\Module\Database\Wrapper $db = null
  );

  public static function Q($expr);
  public static function encrypt($str);
  public static function decrypt($hex);

}

?>
