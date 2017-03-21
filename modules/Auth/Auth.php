<?php
namespace Nudlle\Module;
use Nudlle\Module\Database as NDB;
use Nudlle\Module\Profile as Profile;

abstract class Auth extends \Nudlle\Core\Module implements \Nudlle\Iface\Auth {

  const DOMAIN = 'auth';
  const AUTH_FAIL = 0;
  const AUTH_OK = 1;

  const PARAM_USERNAME = 'auth_user';
  const PARAM_PASSWORD = 'auth_pass';
  const SESSION_USERNAME = 'username';
  const SESSION_PING = 'ping';

  protected static $cfg_pattern = [
    'allow_anonymous' => '1b',
    'hash_func' => '1|sha512',
    'salt_length' => '1i',
    'db' => [
      'username' => '1|username',
      'password' => '1|password',
      'active' => 0
    ],
    'roles' => [
      self::CFG_UNKNOWN => 'i'
    ]
  ];

  protected static $dependencies = [ 'Database', 'Profile', 'Session' ];

  private static $anonymous = true;

  public static function is_anonymous() {
    return self::$anonymous;
  }

  public static function allow_anonymous() {
    return self::get_cfg('allow_anonymous');
  }

  public static function get_anonymous_fallback() {
    return [ 'Baseview', Baseview::O_LOGIN ];
  }

  protected static function invalid_login($request) {
    if (!$request->is_ajax()) {
      Session::set(self::DOMAIN, self::AUTH_FAIL);
      $request->set_redirect(''); // Redirect to default operation
    } else {
      $request->set_module('Baseview');
      $request->set_operation(Baseview::O_NOAUTH);
      $request->noauth();
    }
  }

  protected static function test_password($password, $test_against) {
    $salt_length = self::get_cfg('salt_length');
    $hash_func = self::get_cfg('hash_func');

    $salt = substr($test_against, 0, $salt_length);
    $hash = hash($hash_func, $salt.$password, true);
    return $hash == substr($test_against, $salt_length);
  }

  public static function identify(\Nudlle\Core\Request $request, \Nudlle\Module\Database\Wrapper $db) {
    $table = Profile::get_table();
    $cfg = self::get_cfg();

    foreach ([ 'username', 'password', 'active' ] as $column) {
      if (!isset($cfg['db'][$column])) continue;
      $column = $cfg['db'][$column];
      if (!$table->has_column($column)) {
        throw new \Nudlle\Aception\App("Required column '$column' not found in table '".$table->get_name()."'.");
      }
    }

    $s = new Session(Profile::DOMAIN);
    try {
      $session_user = $s->dget(self::SESSION_USERNAME);
    } catch (\Nudlle\Exception\Undefined $e) {
      $session_user = null;
    }

    try {
      $request_user = $request->get(self::PARAM_USERNAME);
      $request_pass = $request->get(self::PARAM_PASSWORD);
      $request->remove(self::PARAM_USERNAME);
      $request->remove(self::PARAM_PASSWORD);
    } catch (\Nudlle\Exception\Undefined $e) {
      $request_user = null;
    }

    if (!is_null($request_user) && (is_null($session_user) || $request_user != $session_user)) {
      // Authentication request has been received
      Session::set(\Nudlle\Core\Request::FORMS_DOMAIN.'.'.self::PARAM_USERNAME, $request_user);

      try {
        $record = NDB::get_record($table, [ $cfg['db']['username'] => $request_user ], $db);
      } catch (\Nudlle\Exception\Undefined $e) {
        self::invalid_login($request);
        return false;
      }

      if (isset($cfg['db']['active']) && !$record->get($cfg['db']['active'])) {
        self::invalid_login($request);
        return false;
      }

      if (!self::test_password($request_pass, $record->get($cfg['db']['password']))) {
        // Invalid password
        self::invalid_login($request);
        return false;
      }

      $s->dclear();
      Session::change_id();

      if ($request->is_ping()) {
        $s->dset(self::SESSION_PING, true);
      }

      $s->dset(self::SESSION_USERNAME, $record->get($cfg['db']['username']));

      if ($request->get_module() && $request->get_operation()) {
        $link = call_user_func(
          '\\Nudlle\\Module\\'.$request->get_module().'::get_link',
          $request->get_operation(),
          $request->get()
        );
        $request->set_redirect($link);
      } else {
        $request->set_redirect(''); // Redirect to a default operation
      }

      Session::clear(\Nudlle\Core\Request::FORMS_DOMAIN);
      Session::set(self::DOMAIN, self::AUTH_OK);
    } elseif (!is_null($session_user)) {
      // No authentication tokens in the request, try the current session next.
      try {
        $record = NDB::get_record($table, [ $cfg['db']['username'] => $session_user ], $db);
      } catch (\Nudlle\Exception\Undefined $e) {
        $s->dclear();
        return false;
      }

      if (isset($cfg['db']['active']) && !$record->get($cfg['db']['active'])) {
        $s->dclear();
        return false;
      }
    } else {
      // Anonymous
      return false;
    }

    Profile::to_session($record);
    $s->dset(self::SESSION_USERNAME, $record->get($cfg['db']['username']));

    $roles = [];
    foreach ($record->get_related(self::get_table('Role'), [ 'id', 'name' ]) as $role) {
      $roles[$role['id']] = $role['name'];
    }
    $s->dset('roles', $roles);

    self::$anonymous = false;
    return true;
  }

  public static function logout(\Nudlle\Core\Request $request) {
    $domains = [
      \Nudlle\Core\Request::FORMS_DOMAIN, \Nudlle\Core\Request::HISTORY_DOMAIN,
      Profile::DOMAIN, self::DOMAIN
    ];

    foreach ($domains as $domain) {
      Session::clear($domain);
    }
    Session::change_id();

    $request->set_redirect('');
    self::$anonymous = true;
  }

  public static function get_roles() {
    return Session::get(Profile::DOMAIN.'.roles');
  }

  public static function has_role($role) {
    return Session::is_set(Profile::DOMAIN.'.roles.'.self::get_cfg('roles.'.$role));
  }

  private static function manipulate_role($profile, $role, $db, $add) {
    if ($profile instanceof NDB\Record) {
      $profile = $profile->get('id');
    } else {
      NDB\Validate::value($profile, 'id', Profile::get_table());
    }

    $roles = self::get_cfg('roles');
    if (!array_key_exists($role, $roles)) {
      throw new \Nudlle\Exception\Model("Unknown role '$role'.");
    }

    $query = $add
      ? 'INSERT INTO profile_has_role (id_profile, id_role) VALUES (?, ?)'
      : 'DELETE FROM profile_has_role WHERE id_profile = ? AND id_role = ?';

    $db->execute($query, [ $profile, $roles[$role] ]);
  }

  public static function add_role($profile, $role, NDB\Wrapper $db) {
    self::manipulate_role($profile, $role, $db, true);
  }

  public static function remove_role($profile, $role, NDB\Wrapper $db) {
    self::manipulate_role($profile, $role, $db, false);
  }

  public static function change_password($record, $password, $old_password = null) {
    $db_cfg = self::get_cfg('db');
    $hash_func = self::get_cfg('hash_func');
    $salt_length = self::get_cfg('salt_length');

    if (!is_null($old_password) && !self::test_password($old_password, $record->get($db_cfg['password']))) {
      throw new \Nudlle\Exception\Forbidden();
    }

    $salt = openssl_random_pseudo_bytes($salt_length);
    $hash = hash($hash_func, $salt.$password, true);
    $record->set($db_cfg['password'], $salt.$hash);
  }

}

?>
