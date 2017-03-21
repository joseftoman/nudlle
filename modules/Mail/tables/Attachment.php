<?php
namespace Nudlle\Module\Mail\Table;
use Nudlle\Module\Database as NDB;

class Attachment extends \Nudlle\Module\Database\Table {

  protected static $table_name = 'attachment';

  protected static $columns = [
    'id' => [
      'type' => NDB::INT,
      'primary' => true,
      'auto_increment' => true,
      'unsigned' => true
    ],
    'id_mail' => [
      'type' => NDB::INT,
      'unsigned' => true
    ],
    'file' => [
      'type' => NDB::VARCHAR,
      'length' => 255
    ],
    'rename' => [
      'type' => NDB::VARCHAR,
      'null' => true,
      'length' => 100
    ]
  ];

  protected static $relations = [
    'foreign_keys' => [
      [
        '\\Nudlle\\Module\\Mail\\Table\\Mail',
        [ 'id_mail' => 'id' ]
      ]
    ]
  ];

}

?>
