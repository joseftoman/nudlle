<?php
namespace Nudlle\Iface;

interface Record {

  public function get($column = null);
  public function set($column, $value);
  public function save($force_update = false);
  public function delete();
  public function move($amount);
  public function get_related($table, $object_or_columns = null);

}

?>
