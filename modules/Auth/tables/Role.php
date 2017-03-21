<?php
namespace Nudlle\Module\Auth\Table;
use Nudlle\Module\Database as NDB;

class Role extends \Nudlle\Module\Database\Table {

  protected static $table_name = 'role';

  protected static $columns = [
    'id' => [
      'type' => NDB::INT,
      'primary' => true,
      'unsigned' => true
    ],
    'name' => [
      'type' => NDB::VARCHAR,
      'length' => 20
    ]
  ];

  protected static $relations = [
    'many2many' => [
      [
        '\\Nudlle\\Module\\Profile\\Table\\Profile',
        'profile_has_role',
        [ 'id' => 'id_role' ],
        [ 'id_profile' => 'id' ]
      ]
    ]
  ];

}

?>
