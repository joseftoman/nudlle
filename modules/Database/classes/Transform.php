<?php
namespace Nudlle\Module\Database;
use Nudlle\Module\Database as NDB;

abstract class Transform {

  private static function datetime($value) {
    if (is_object($value) && get_class($value) == 'DateTime') {
      return $value;
    } elseif (is_int($value)) {
      $obj = new \DateTime("@$value");
      $obj->setTimezone(new \DateTimeZone(\Nudlle\TIMEZONE));
      return $obj;
    } elseif (is_string($value)) {
      try {
        $obj = new \DateTime($value, new \DateTimeZone(\Nudlle\TIMEZONE));
        return $obj;
      } catch (\Throwable $e) {
        throw new \Nudlle\Exception\WrongData("Unparsable date&time value: '$value'");
      }
    }

    return $value;
  }

  public static function bool($value) {
    if (is_bool($value)) return $value;
    if (preg_match('/^(t|true|y|yes|on|1)$/i', $value)) return 1;
    if (preg_match('/^(f|false|n|no|off|0)$/i', $value)) return 0;
    return $value ? 1 : 0;
  }

  public static function value($value, $type) {
    $function = null;
    switch ($type) {
      case NDB::DATE: $function = 'datetime'; break;
      case NDB::DATETIME: $function = 'datetime'; break;
      case NDB::BOOL: $function = 'bool'; break;
    }

    if (is_null($function)) {
      return $value;
    }

    if (is_scalar($value)) {
      return self::$function($value);
    } elseif (is_array($value)) {
      return array_map("self::$function", $value);
    } else {
      return $value;
    }
  }

}

?>
