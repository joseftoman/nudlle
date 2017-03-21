<?php
namespace Nudlle\Module\Database;
use Nudlle\Module\Database as NDB;

abstract class Validate {

  private static function int_type($value, $column, $table) {
    if (!is_int($value) && (!is_numeric($value) || (is_string($value) && strpos($value, '.') !== false))) {
      throw new \Nudlle\Exception\WrongData("'$value' is not a proper integer value.");
    }

    switch ($table->get_type($column)) {
      case NDB::SMALLINT: $min = -32768; $max = 32767; break;
      case NDB::INT: $min = -2147483648; $max = 2147483647; break;
      case NDB::BIGINT: $min = null; $max = null; break;
    }
    if ($table->is_unsigned($column)) {
      $min = 0;
    }

    if ((!is_null($min) && $value < $min) || (!is_null($max) && $value > $max)) {
      throw new \Nudlle\Exception\WrongData("Value '$value' can not be represented by type '$type'.");
    }
  }

  private static function string_type($value, $column, $table) {
    $type = $table->get_type($column);
    if ($type == NDB::BINARY) {
      $len = strlen($value);
    } else {
      $len = mb_strlen($value);
    }

    $max = $table->get_length($column);
    if (!is_null($max) && $len > $max) {
      throw new \Nudlle\Exception\WrongData('String value is too long.');
    }

    if (!$table->is_empty($column) && $value === '') {
      throw new \Nudlle\Exception\WrongData("Column '$column' does not allow empty strings.");
    }
  }

  public static function value($value, $column, Table $table) {
    if ($value === null && $table->is_null($column)) return;

    switch ($table->get_type($column)) {
      case NDB::SMALLINT:
      case NDB::INT:
      case NDB::BIGINT:
        self::int_type($value, $column, $table);
        break;

      case NDB::REAL:
      case NDB::DOUBLE:
        if (!is_numeric($value)) {
          throw new \Nudlle\Exception\WrongData("'$value' is not a numeric value.");
        }
        break;

      case NDB::DECIMAL:
        if (!is_numeric($value)) {
          throw new \Nudlle\Exception\WrongData("'$value' is not a numeric value.");
        } else {
          if (preg_match('/^-?([0-9]*)(?:\.([0-9]+))?$/', strval($value), $matches)) {
            $scale = count($matches) == 3 ? strlen($matches[2]) : 0;
            $len = ($matches[1] == 0 ? 0 : strlen($matches[1])) + $scale;
            if ($len > $table->get_length($column) || $scale > $table->get_scale($column)) {
              throw new \Nudlle\Exception\WrongData("Value '$value' exceeds specified bounds.");
            }
          }
        }
        break;

      case NDB::CHAR:
      case NDB::VARCHAR:
      case NDB::TEXT:
      case NDB::BINARY:
        self::string_type($value, $column, $table);
        break;

      case NDB::DATE:
      case NDB::DATETIME:
        if (!is_object($value) || get_class($value) != 'DateTime') {
          throw new \Nudlle\Exception\WrongData("A DateTime object is required for column '$column'.");
        }
        break;
    }

    if ($table->is_unsigned($column) && $value < 0) {
      throw new \Nudlle\Exception\WrongData("Option 'unsigned' does not allow negative values.");
    }
    if (!$table->is_null($column) && $value === null) {
      throw new \Nudlle\Exception\WrongData("Column '$column' does not allow NULL values.");
    }

    $own = [];
    foreach ($table->get_own_methods() as $name) {
      $own[$name] = true;
    }
    foreach ($table->get_semantic_options($column) as $option) {
      if (isset($own[$option])) {
        call_user_func([ $table, $option ], $value);
      } else {
        self::$option($value);
      }
    }
  }

  public static function email($value) {
    $regexp = '/^(?:[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+\.)*[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+@(?:(?:(?:[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!\.)){0,61}[a-zA-Z0-9_-]?\.)+[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!$)){0,61}[a-zA-Z0-9_]?)|(?:\[(?:(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\]))$/';
    if (preg_match($regexp, $value)) {
      return;
    }
    throw new \Nudlle\Exception\WrongData("Invalid e-mail address: '$value'");
  }

  public static function phone($value) {
    $regexp = '/^\+?(?:[0-9][ .-]?){6,14}[0-9]$/';
    if (preg_match($regexp, $value)) {
      return;
    }
    throw new \Nudlle\Exception\WrongData("Invalid phone number: '$value'");
  }

}

?>
