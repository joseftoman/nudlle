<?php
namespace Nudlle\Core;

class Debug {

  // Implies EXCEPTION = 0, BUFFER = 0, REPORT_ALL = 1 and no exception/error logging.
  // Fatal exceptions/errors are written directly on the screen.
  const DEVEL_MODE = 1;

  // Exception caught in the main application loop will be var_dumped.
  // When set to 2, a notice of each created exception object will be written out (one line).
  const EXCEPTION = 0;

  // Incomplete output buffer will be written out instead of discarding.
  const BUFFER = 0;

  // All error messages will be presented to the user instead of the selected ones only.
  const REPORT_ALL = 0;

  const LOG_FILE = 'debug.txt';

  public static function log($text, $token = null) {
    $ok = true;
    $ok = $ok && $f = fopen(\Nudlle\LOG_PATH.'/'.self::LOG_FILE, 'a');
    $ok = $ok && fwrite($f, date('j.n.Y H:i:s ').($token ? '('.$token.'): ' : '').$text."\n");
    $ok = $ok && fclose($f);

    if (!$ok) {
      throw new \Nudlle\Exception\File('Can not write to debug log', true);
    }
  }

  public static function dump_exception(\Throwable $e) {
    echo '<pre>';
    var_dump($e);
    echo '</pre>';
  }

  public static function dump_error($type, $message, $file, $line, $context) {
    echo '<pre>';
    echo "ERROR: $type - $message\nLine $line in $file\n";
    echo "</pre>\n";
  }

}

?>
