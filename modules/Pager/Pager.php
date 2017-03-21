<?php
namespace Nudlle\Module;
use Nudlle\Module\Database as NDB;

abstract class Pager extends \Nudlle\Core\Module implements \Nudlle\Iface\Pager {

  protected static $dependencies = [ 'Js' ];

  public static function get_pager(
    \Nudlle\Module\Database\Table $table,
    \Nudlle\Module\Database\Wrapper $db
  ) {
    return new Pager\Model($db, $table);
  }

}

?>
