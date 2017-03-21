<?php
namespace Nudlle\Module\Mail\Table;
use Nudlle\Module\Database as NDB;

class Mail extends \Nudlle\Module\Database\Table {

  protected static $table_name = 'mail';

  protected static $columns = [
    'id' => [
      'type' => NDB::INT,
      'primary' => true,
      'auto_increment' => true,
      'unsigned' => true
    ],
    'to' => [
      'type' => NDB::TEXT,
      'null' => true
    ],
    'cc' => [
      'type' => NDB::TEXT,
      'null' => true
    ],
    'bcc' => [
      'type' => NDB::TEXT,
      'null' => true
    ],
    'from' => [
      'type' => NDB::VARCHAR,
      'length' => 100,
      'email' => true
    ],
    'from_label' => [
      'type' => NDB::VARCHAR,
      'length' => 100,
      'null' => true
    ],
    'subject' => [
      'type' => NDB::VARCHAR,
      'length' => 255
    ],
    'body' => [
      'type' => NDB::TEXT,
    ],
    'txt' => [
      'type' => NDB::TEXT,
      'null' => true
    ],
    'rid' => [
      'type' => NDB::VARCHAR,
      'length' => 23,
      'null' => true
    ],
    'time' => [
      'type' => NDB::DATETIME,
      'null' => true
    ]
  ];

}

?>
