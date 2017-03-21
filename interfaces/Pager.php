<?php
namespace Nudlle\Iface;

interface Pager {

  public static function get_pager(
    \Nudlle\Module\Database\Table $table,
    \Nudlle\Module\Database\Wrapper $db
  );

}

?>
