<?php
namespace Nudlle\Module\Profile\Table;
use Nudlle\Module\Database as NDB;

class Profile extends \Nudlle\Module\Database\Table {

  protected static $table_name = 'profile';

  protected static $columns = [
    'id' => [
      'type' => NDB::INT,
      'primary' => true,
      'auto_increment' => true,
      'unsigned' => true
    ],
    'first_name' => [
      'type' => NDB::VARCHAR,
      'length' => 45
    ],
    'surname' => [
      'type' => NDB::VARCHAR,
      'length' => 45
    ],
    'username' => [
      'type' => NDB::VARCHAR,
      'length' => 50
    ],
    'password' => [
      'type' => NDB::BINARY
    ],
    'email' => [
      'type' => NDB::VARCHAR,
      'length' => 100,
      'null' => true,
      'email' => true
    ],
    'is_allowed' => [
      'type' => NDB::BOOL
    ]
  ];

  protected static $relations = [
    'many2many' => [
      [
        '\\Nudlle\\Module\\Auth\\Table\\Role',
        'profile_has_role',
        [ 'id' => 'id_profile' ],
        [ 'id_role' => 'id' ]
      ]
    ]
  ];

}

?>
