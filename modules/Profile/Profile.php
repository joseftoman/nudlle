<?php
namespace Nudlle\Module;
use Nudlle\Module\Database as NDB;

class Profile extends \Nudlle\Core\ContentModule implements \Nudlle\Iface\Profile {

  public static function to_session($record) {
    if (!\Nudlle\has_module('Session')) return;

    $s = new Session(self::DOMAIN);
    foreach ([ 'id', 'first_name', 'surname', 'email' ] as $col) {
      $s->dset($col, $record->get($col));
    }
  }

}

?>
