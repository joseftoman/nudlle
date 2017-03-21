<?php
namespace Nudlle\Module;

// TODO: class RecordSet for multiple inserts (in one SQL statement)

abstract class Database extends \Nudlle\Core\Module implements \Nudlle\Iface\Database {

  const CRYPT_METHOD = 'AES-256-CBC';
  const DATE_FORMAT = 'Y-m-d';
  const DATETIME_FORMAT = 'Y-m-d H:i:s P';

  const DOMAIN = 'database';

  /* Data types */
  const SMALLINT = 'smallint';
  const INT = 'integer';
  // Test PHP_INT_SIZE. If < 8 than disallow BIGINT.
  const BIGINT = 'bigint';

  // Smallserial, serial and bigserial doesn't need separate data types. They
  // should be represented as smallint, int and bigint in the table definition
  // classes together with the option auto_increment. (Probably also primary
  // and unsigned.)

  const DECIMAL = 'decimal';
  const REAL = 'real';
  const DOUBLE = 'double precision';

  const CHAR = 'char';
  const VARCHAR = 'varchar';
  const TEXT = 'text';
  const BINARY = 'bytea';

  const DATE = 'date';
  const DATETIME = 'timestamptz';

  const BOOL = 'boolean';
  const JSONB = 'jsonb';

  protected static $cfg_pattern = [
    'name' => 1,
    'host' => '1|localhost',
    'user' => '1|postgres',
    'password' => 0,
    'crypt_key' => 0
  ];

  public static function get_wrapper() {
    $cfg = self::get_cfg();

    return new Database\Wrapper(
      Database\Wrapper::PGSQL,
      $cfg['host'],
      $cfg['name'],
      $cfg['user'],
      array_key_exists('password', $cfg) ? $cfg['password'] : null
    );
  }

  public static function get_record(
    \Nudlle\Module\Database\Table $table,
    $key = null,
    \Nudlle\Module\Database\Wrapper $db = null
  ) {
    return new Database\Record($db ? $db : static::get_wrapper(), $table, $key);
  }

  public static function get_collector(
    \Nudlle\Module\Database\Table $table,
    \Nudlle\Module\Database\Wrapper $db = null
  ) {
    return new Database\Collector($db ? $db : static::get_wrapper(), $table);
  }

  public static function get_manager(
    \Nudlle\Module\Database\Table $table,
    \Nudlle\Module\Database\Wrapper $db = null
  ) {
    return new Database\Manager($db ? $db : static::get_wrapper(), $table);
  }

  // Quoting column names and other identifiers
  public static function Q($expr) {
    return implode('.', array_map(function($a) { return "\"$a\""; }, explode('.', $expr)));
  }

  public static function encrypt($str) {
    $iv_size = openssl_cipher_iv_length(self::CRYPT_METHOD);
    $iv = openssl_random_pseudo_bytes($iv_size);
    $enc = openssl_encrypt($str, self::CRYPT_METHOD, self::get_cfg('crypt_key'), 0, $iv);
    return $iv.base64_decode($enc);
  }

  public static function decrypt($bin_str) {
    $iv_size = openssl_cipher_iv_length(self::CRYPT_METHOD);
    return openssl_decrypt(
      base64_encode(substr($bin_str, $iv_size)),
      self::CRYPT_METHOD, self::get_cfg('crypt_key'), 0,
      substr($bin_str, 0, $iv_size)
    );
  }

}

?>
