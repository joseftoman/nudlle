<?php
namespace Nudlle\Module\Failure\Table;
use Nudlle\Module\Database as NDB;

class Failure extends \Nudlle\Module\Database\Table {

  protected static $table_name = 'failure';

  protected static $columns = [
    'id' => [
      'type' => NDB::INT,
      'primary' => true,
      'auto_increment' => true,
      'unsigned' => true
    ],
    'login' => [
      'type' => NDB::VARCHAR,
      'length' => 50,
      'null' => true
    ],
    'error' => [
      'type' => NDB::BINARY,
      'encrypted' => true
    ],
    'variables' => [
      'type' => NDB::BINARY,
      'encrypted' => true
    ],
    'request' => [
      'type' => NDB::BINARY,
      'encrypted' => true
    ],
    'time' => [
      'type' => NDB::DATETIME,
      'now' => true
    ]
  ];

}

?>
