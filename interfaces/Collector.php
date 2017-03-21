<?php
namespace Nudlle\Iface;

interface Collector {

  public function add_table(
    \Nudlle\Module\Database\Table $table,
    \Nudlle\Module\Database\Filter $filter = null,
    $allow_null = false
  );

  public function set_order($order, $dir = self::DIR_ASC);

  public function get_list(
    $cols = null,
    \Nudlle\Core\Request $request = null,
    \Nudlle\Module\Database\Filter $filter = null,
    $table_id = null
  );

  public function derive_filter();

}

?>
