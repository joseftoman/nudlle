<?php
namespace Nudlle\Iface;

interface Filter {

  public function add_table(\Nudlle\Module\Database\Table $table);
  public function extend($input, $operator = self::O_AND);
  public function export();
  public function is_empty();

}

?>
