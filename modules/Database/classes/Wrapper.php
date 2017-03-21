<?php
namespace Nudlle\Module\Database;

// Because Wrapper extends PDO directly, it should be usable for any RDBMS
// supported by PDO with none or minimal changes. But it is limited to MySQL
// and PostgreSQL by design at the moment.

class Wrapper extends \PDO implements \Nudlle\Iface\Wrapper {

  // 0: no logging
  // 1: log parse & execute errors (normal query execution + STMT prepare)
  // 2: log parse & execute & STMT execute errors (all errors)
  // 3: log all queries
  // NOTICE: some PDO drivers has incomplete support of prepared statements.
  // As a result a wrong query (syntax) might not fail during the preparation
  // but only when it is executed.
  // Use LOG = 2 unless you know exactly what you are doing.
  // Use LOG = 3 for debugging or profiling.
  const LOG = 2;

  // Log $_SERVER['REQUEST_URI'] as well when true
  const LOG_URI = 1;

  // Extension '.txt' will by added automatically. Log dir = \Nudlle\LOG_PATH.
  const LOG_FILE = 'queries';

  // Max file size [bytes] for log rotation.
  const LOG_MAX = 10485760; // 10MB

  // When true a notification is sent to email address \Nudlle\EMERGENCY each
  // time the log file reaches its maximum size LOG_MAX.
  const SEND_MAIL = 1;

  // 0: Silent mode. Use methods errorCode() and errorInfo() to check for errors.
  // 1: E_USER_WARNING error is triggered.
  // 2: Exception is thrown.
  const ERROR_HANDLING = 2;

  // When true, Exception \Nudlle\Exception\DBEmpty is thrown each time an empty result set is returned.
  const EXCEPTION_ON_EMPTY = 0;
  
  const MYSQL = 'mysql';
  const PGSQL = 'pgsql';
  const ASSOC = \PDO::FETCH_ASSOC;
  const NUM = \PDO::FETCH_NUM;
  const BOTH = \PDO::FETCH_BOTH;

  private $transaction_nesting = 0;

  public function __construct($driver, $host, $db_name, $user, $pass, $enc = 'UTF8', $port = '') {
    if ($driver != self::MYSQL && $driver != self::PGSQL) {
      $e = new \PDOException('Unknown database driver "'.$driver.'"');
      self::handle_error($e);
    }

    $separator = ';';
    if ($driver == self::PGSQL) {
      $separator = ' ';
    }

    $dsn = $driver.':host='.$host.$separator.'dbname='.$db_name;
    if ($port) {
      $dsn .= $separator.'port='.$port;
    }

    try {
      parent::__construct($dsn, $user, $pass);
      $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      $this->exec("SET NAMES '".$enc."'");
    } catch (\PDOException $e) {
      self::handle_error($e);
    }
  }

  private static function handle_error(\Throwable $e, $error_info = null) {
    if (self::ERROR_HANDLING == 0) return;

    if (self::ERROR_HANDLING == 1) {
      $location = '(file <b>'.$e->getFile().'</b>, line <b>'.$e->getLine().'</b>)';
      if (!is_null($error_info) && $e instanceof \PDOException) {
        $error = '<b>'.$error_info[0].':</b> <i>'.$error_info[1].'</i> '.$error_info[2];
      } else {
        $error = $e->getMessage();
      }
      $error .= ' '.$location;
      trigger_error($error, E_USER_WARNING);
    } elseif (self::ERROR_HANDLING == 2) {
      throw $e;
    }
  }

  private static function send_emergency_email($subject, $body) {
  	if (self::SEND_MAIL) {
      $headers = 'From: emergency@'.\Nudlle\DOMAIN_NAME."\r\n";
    	$headers .= "MIME-Version: 1.0\r\n";
    	$headers .= "Content-type: text/plain; charset=utf-8\r\n";
    	mail(
        \Nudlle\EMERGENCY,
        $subject,
        $body,
        $headers,
        '-femergency@'.\Nudlle\DOMAIN_NAME
      );
    }
  }

  private static function log_query($query, $params = null) {
    if (self::LOG == 0) {
      return;
    }
    if (self::LOG == 1) {
      if (is_null($params)) {
        $record = $query;
      } else {
        return;
      }
    }
    if (self::LOG >= 2) {
      if (self::LOG == 3) {
        $record = 'OK: ';
      } else {
        $record = '';
      }
      $record .= $query;
      if (!is_null($params)) {
        $record .= ' | Parameters:';
        foreach ($params as $key => $value) {
          ob_start();
          var_dump($value);
          $value = trim(ob_get_contents());
          ob_end_clean();
          $record .= " [$key] => $value;";
        }
      }
    }
    if (self::LOG_URI && array_key_exists('REQUEST_URI', $_SERVER)) {
      $record .= ' | URI: '.$_SERVER['REQUEST_URI'];
    }

    $file = \Nudlle\LOG_PATH.'/'.self::LOG_FILE.'.txt';

    $OK = true;

    if (file_exists($file) && filesize($file) >= self::LOG_MAX) {
      $date = date('Ymd');
      $OK = $OK && @rename($file, \Nudlle\LOG_PATH.'/'.self::LOG_FILE.'-'.$date.'.txt');

    	$subject = '['.\Nudlle\DOMAIN_NAME.'] Query log overflow';
    	$body = 'The query log of project "'.\Nudlle\DOMAIN_NAME.'" has exceeded its maximum allowed size ('.\Nudlle\Core\Format::size(self::LOG_MAX).').';
    	self::send_emergency_email($subject, $body);
    }

    $OK = $OK && ($res = @fopen($file, 'a'));
    $OK = $OK && @fwrite($res, date('Y-m-d H:i:s').' | '.$record."\n");
    $OK = $OK && @fclose($res);

    if (!\Nudlle\has_module('Session')) {
      $send_mail = !$OK;
    } else {
      $s = new \Nudlle\Module\Session(\Nudlle\Module\Database::DOMAIN);
      if (!$OK) {
        $send_mail = ! $s->dis_set('log_error');
        $s->dset('log_error', true);
      } else {
        $send_mail = false;
        $s->dclear('log_error');
      }
      unset($s);
    }

    if ($send_mail) {
      $subject = '['.\Nudlle\DOMAIN_NAME.'] Query log unwritable';
      $body = 'Writing to the query log of project "'.\Nudlle\DOMAIN_NAME.'" has failed.';
      self::send_emergency_email($subject, $body);
    }
  }

  public function begin() {
    $ok = true;
    if ($this->transaction_nesting == 0) {
      try {
        parent::beginTransaction();
      } catch (\PDOException $e) {
        self::handle_error($e, $this->errorInfo());
        $ok = false;
      }
    }
    $this->transaction_nesting += $ok ? 1 : 0;
  }

  public function rollback() {
    $ok = true;
    if ($this->transaction_nesting == 1) {
      try {
        parent::rollBack();
      } catch (\PDOException $e) {
        self::handle_error($e, $this->errorInfo());
        $ok = false;
      }
    }
    $this->transaction_nesting -= $ok ? 1 : 0;
  }

  public function commit() {
    $ok = true;
    if ($this->transaction_nesting == 1) {
      try {
        parent::commit();
      } catch (\PDOException $e) {
        self::handle_error($e, $this->errorInfo());
        $ok = false;
      }
    }
    $this->transaction_nesting -= $ok ? 1 : 0;
  }

  // It is better to redefine (mask) the parent class method.
  public function beginTransaction() {
    $this->begin();
  }

  public function last_id($sequence = null) {
    try {
      return $this->lastInsertId($sequence);
    } catch (\PDOException $e) {
      self::handle_error($e, $this->errorInfo());
      return false;
    }
  }

  public function query($query) {
    $statement = null;
    try {
      $statement = parent::query($query);
      if (self::LOG == 3) {
        self::log_query($query);
      }
    } catch (\PDOException $e) {
      self::log_query($query);
      self::handle_error($e, $this->errorInfo());
    }

    return $statement;
  }

  public function prepare($query, $options = []) {
    $statement = null;
    try {
      $statement = parent::prepare($query, $options);
    } catch (\PDOException $e) {
      self::log_query($query);
      self::handle_error($e, $this->errorInfo());
    }

    return $statement;
  }

  // It is better to redefine (mask) the parent class method.
  public function exec($query) {
    return $this->execute($query);
  }

  public function execute($query, $params = null) {
    try {
      if (is_null($params)) {
        $res = parent::exec($query);
        if (self::LOG == 3) {
          self::log_query($query);
        }
        return $res;
      } else {
        $stmt = parent::prepare($query);
      }
    } catch (\PDOException $e) {
      self::log_query($query);
      self::handle_error($e, $this->errorInfo());
      return 0;
    }

    $affected = 0;
    if (!is_array($params)) {
      $e = new \Nudlle\Exception\App('Wrong usage of Wrapper::execute() in module Database. Parameter $params has to be either array or null.');
      self::handle_error($e);
    } else {
      try {
        // Can not use $params[0] to access the first item, because the params
        // could be named as well (E.g. [ ':name' => 'value', ...]).
        $first = reset($params);
        if (is_scalar($first) || is_null($first)) {
          $params = [ $params ];
        }
        foreach ($params as $one_set) {
          $stmt->execute($one_set);
          if (self::LOG == 3) {
            self::log_query($stmt->queryString, $one_set);
          }
          $affected += $stmt->rowCount();
        }
        return $affected;
      } catch (\PDOException $e) {
        self::log_query($stmt->queryString, $one_set);
        self::handle_error($e, $this->errorInfo());
        return $affected;
      }
    }
  }

  public function fetch_array($source, $only_first = false, $result_type = null) {
    if (is_null($result_type)) $result_type = self::ASSOC;
    if (!in_array($result_type, [ self::ASSOC, self::NUM, self::BOTH ])) {
      throw new \Nudlle\Exception\App('Wrong usage of Wrapper::fetch_array() in module Database. Invalid value of parameter $result_type.');
    }

    try {
      if (is_string($source)) {
        $mod = 'query';
        $stmt = $this->query($source);
      } elseif ($source instanceof \PDOStatement) {
        // Metoda dostala už hotový objekt a nesmí ho rozbít
        $mod = 'stmt';
        $stmt = $source;
      } elseif (is_array($source)) {
        // Metoda si musí vytvořit svůj vlastní objekt a na konci ho zase zrušit
        $mod = 'stmt_tmp';
        $stmt = $this->prepare($source[0]);
        try {
          $stmt->execute($source[1]);
        } catch (\PDOException $e) {
          self::log_query($stmt->queryString, $source[1]);
          self::handle_error($e, $stmt->errorInfo());
        }
      } else {
        throw new \Nudlle\Exception\App('Wrong usage of Wrapper::fetch_array() in module Database. Unknown format of parameter $source.');
      }

      if ($only_first) {
        $result = $stmt->fetch($result_type);
        $stmt->closeCursor();
      } else {
        $result = $stmt->fetchAll($result_type);
      }

      if (self::LOG == 3) {
        if ($mod == 'stmt_tmp') {
          self::log_query($stmt->queryString, $source[1]);
        } elseif ($mod == 'stmt') {
          self::log_query($stmt->queryString);
        }
        // When $mod == 'query', the query was logged by $this->query().
      }

      // fetch() returns false, fetchAll returns []
      if (!$result && self::EXCEPTION_ON_EMPTY) {
        throw new \Nudlle\Exception\DBEmpty();
      }

      if ($mod != 'stmt') {
        $stmt = null;
      }
      return $result;
    } catch (\PDOException $e) {
      self::handle_error($e, $this->errorInfo());
      return [];
    }
  }

  public function fetch_value($source, $col_num = 0) {
    $result = $this->fetch_array($source, true, self::NUM);
    if (!array_key_exists($col_num, $result)) {
      $e = new \PDOException('Invalid column number.');
      self::handle_error($e);
      return null;
    }

    return $result[$col_num];
  }

  public function fetch_map($source, $key_col, $data_col = null, $unique = true) {
    $data = $this->fetch_array($source, false, self::ASSOC);
    if (!$data) return [];

    if (!array_key_exists($key_col, $data[0]) || ($data_col && !array_key_exists($data_col, $data[0]))) {
      $e = new \PDOException('Required columns were not found in the query result.');
      self::handle_error($e);
      return [];
    }

    $result = [];
    foreach ($data as $row) {
      if ($data_col) {
        $value = $row[$data_col];
      } else {
        $value = $row;
      }
      if ($unique) {
        if (array_key_exists($row[$key_col], $result)) {
          $e = new \PDOException('Duplicate values in the key column.');
          self::handle_error($e);
        }
        $result[$row[$key_col]] = $value;
      } else {
        $result[$row[$key_col]][] = $value;
      }
    }
    return $result;
  }

  public function fetch_column($source, $column = null) {
    $data = $this->fetch_array($source, false, self::ASSOC);

    if (!is_null($column) && !array_key_exists($column, $data[0])) {
      $e = new \PDOException('Required column was not found in the query result.');
      self::handle_error($e);
      return [];
    }

    $result = [];
    foreach ($data as $row) {
      if (is_null($column)) {
        $result[] = array_shift($row);
      } else {
        $result[] = $row[$column];
      }
    }
    return $result;
  }

  // For MySQL: $label = table name
  // For PostgreSQL: $label = sequence name
  public function next_id($label) {
    $driver = $this->getAttribute(\PDO::ATTR_DRIVER_NAME);
    if ($driver == self::MYSQL) {
      $query = 'SHOW TABLE STATUS LIKE ?';
      $info = $this->fetch_array([ $query, [ $label ] ], true);
      $auto_inc = $info['Auto_increment'];
      $query = 'ALTER TABLE "'.$label.'" AUTO_INCREMENT = '.($auto_inc + 1);
      $this->query($query);
      return $auto_inc;
    } elseif ($driver == self::PGSQL) {
      $query = 'SELECT nextval(?)';
      return $this->fetch_value([ $query, [ $label ] ]);
    }
  }

}

namespace Nudlle\Exception;

class DBEmpty extends \PDOException {
  public function __construct() {
    parent::__construct('Result of the query is empty.');
  }
}

?>
