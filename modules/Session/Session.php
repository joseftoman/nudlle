<?php
namespace Nudlle\Module;

class Session extends \Nudlle\Core\Module implements \Nudlle\Iface\Session {

  const DATA_KEY = '__new_data';

  private $domain_name = null;
  private $domain = null;

  public static function init($id = null) {
    if ($id) {
      session_id($id);
    }
    if (!session_start()) {
      return false;
    }

    if (array_key_exists(self::DATA_KEY, $_SESSION)) {
      unset($_SESSION[self::DATA_KEY]);
    }
    $tmp = $_SESSION;
    $_SESSION[self::DATA_KEY] = $tmp;

    return true;
  }

  public static function finish() {
    if (!isset($_SESSION)) {
      throw new \Nudlle\Exception\App('Can not finish session until it is initialized');
    }
    $_SESSION = $_SESSION[self::DATA_KEY];
    session_write_close();
  }

  public static function destroy() {
    if (!isset($_SESSION)) {
      throw new \Nudlle\Exception\App('Can not destroy session until it is initialized');
    }

    $_SESSION = [];
    $cookie = true;
    if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      $cookie = setcookie(
        session_name(), '', 1, $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
      );
    }
    return $cookie && session_destroy();
  }

  public static function get_id() {
    return session_id();
  }

  public static function change_id($id = null, $copy = false) {
    if (!isset($_SESSION)) {
      throw new \Nudlle\Exception\App('Can not change session ID until it is initialized');
    }

    if (!$id) {
      return session_regenerate_id(!$copy);
    } else {
      $session_data = $_SESSION;
      if ($copy) {
        self::finish();
      } else {
        if (!self::destroy()) return false;
      }
      session_id($id);
      if (!session_start()) return false;
      $_SESSION = $session_data;
      return true;
    }
  }

  private static function _get(&$data, $key = null, $domain_name = null) {
    foreach (\Nudlle\Core\Helper::tokenize_path($key) as $token) {
      if (!array_key_exists($token, $data)) {
        $message = 'Session: key "'.$key.'" does not exist';
        if ($domain_name) {
          $message .= ' in domain "'.$domain_name.'"';
        }
        throw new \Nudlle\Exception\Undefined($message);
      }
      $data = &$data[$token];
    }

    return $data;
  }

  private static function _set(&$data, $key = null, $value) {
    if (is_null($key)) {
      $data = $value;
    } else {
      $key = \Nudlle\Core\Helper::tokenize_path($key);
      $last_key = array_pop($key);
      foreach ($key as $token) {
        if (!array_key_exists($token, $data)) {
          $data[$token] = [];
        }
        $data = &$data[$token];
      }
      $data[$last_key] = $value;
    }
  }

  private static function _is_set(&$data, $key) {
    foreach (\Nudlle\Core\Helper::tokenize_path($key) as $token) {
      if (!array_key_exists($token, $data)) {
        return false;
      }
      $data = &$data[$token];
    }
    return true;
  }

  private static function _clear(&$data, $key = null) {
    if (is_null($key)) {
      $data = [];
    } else {
      $key = \Nudlle\Core\Helper::tokenize_path($key);
      $last_key = array_pop($key);
      while (count($key) && array_key_exists($key[0], $data)) {
        $data = &$data[array_shift($key)];
      }

      if (!count($key) && array_key_exists($last_key, $data)) {
        unset($data[$last_key]);
      }
    }
  }

  public static function get($key = null) {
    if (!isset($_SESSION)) {
      throw new \Nudlle\Exception\App('Session has to be initialized first');
    }
    return self::_get($_SESSION[self::DATA_KEY], $key);
  }

  public static function set($key = null, $value) {
    if (!isset($_SESSION)) {
      throw new \Nudlle\Exception\App('Session has to be initialized first');
    }
    self::_set($_SESSION[self::DATA_KEY], $key, $value);
  }

  public static function is_set($key) {
    if (!isset($_SESSION)) {
      throw new \Nudlle\Exception\App('Session has to be initialized first');
    }
    return self::_is_set($_SESSION[self::DATA_KEY], $key);
  }

  public static function clear($key = null) {
    if (!isset($_SESSION)) {
      throw new \Nudlle\Exception\App('Session has to be initialized first');
    }
    self::_clear($_SESSION[self::DATA_KEY], $key);
  }

  public function __construct($domain = null) {
    if (!isset($_SESSION)) {
      throw new \Nudlle\Exception\App('Session has to be initialized first');
    }
    $data = &$_SESSION[self::DATA_KEY];

    if ($domain) {
      foreach (\Nudlle\Core\Helper::tokenize_path($domain) as $token) {
        if (!array_key_exists($token, $data)) {
          $data[$token] = [];
        }
        $data = &$data[$token];
      }
      $this->domain_name = $domain;
    }

    $this->domain = &$data;
  }

  public function dget($key = null) {
    return self::_get($this->domain, $key, $this->domain_name);
  }

  public function dset($key = null, $value) {
    self::_set($this->domain, $key, $value);
  }

  public function dis_set($key) {
    return self::_is_set($this->domain, $key);
  }

  public function dclear($key = null) {
    self::_clear($this->domain, $key);
  }

}

?>
