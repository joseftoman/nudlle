<?php
namespace Nudlle\Core;

abstract class ErrorHandler {

  const CALLBACK_ON_ERROR = 'Nudlle\\fallback';
  const EXIT_ON_ERROR = true;
  const LOG_FILE = 'crash.txt';
  // Bitmask of errors to ignore.
  // E_DEPRECATED is needed because of the PHPMailer library.
  //const IGNORE = 0;
  const IGNORE = E_DEPRECATED;

  private static function finish() {
    if (self::CALLBACK_ON_ERROR) {
      call_user_func(self::CALLBACK_ON_ERROR);
    }
    if (self::EXIT_ON_ERROR) {
      exit(1);
    }
  }

  private static function clear_buffer() {
    if (!Debug::BUFFER || Debug::DEVEL_MODE) {
      while (ob_get_level() > 0) {
        ob_end_clean();
      }
    }
  }

  // For arguments see http://php.net/manual/en/function.set-error-handler.php
  public static function process_error($type, $message, $file, $line, $context) {
    if (error_reporting() == 0) {
      // For commands prefixed with '@'
      return false;
    }
    if ($type & self::IGNORE) {
      return false;
    }
    self::clear_buffer();

    if (Debug::DEVEL_MODE) {
      Debug::dump_error($type, $message, $file, $line, $context);
      exit(1);
    } else {
      $text = $type.", Message: '".$message."'\n";
      $text .= "File '".$file."', line no. ".$line."\n";
      ob_start();
      var_dump($context);
      $text .= ob_get_contents();
      ob_end_clean();
      self::store_report($text);
      self::finish();
      return true;
    }
  }

  public static function process_exception(\Throwable $e, $store_only = false, $no_devel = false) {
    self::clear_buffer();

    if (Debug::DEVEL_MODE && !$no_devel) {
      Debug::dump_exception($e);
      exit(1);
    } else {
      ob_start();
      var_dump($e);
      $text = ob_get_contents();
      ob_end_clean();
      self::store_report($text);
      if (!$store_only) {
        self::finish();
      }
      return true;
    }
  }

  private static function store_report_mail($report) {
    $report = preg_replace('/\n/', "\r\n", $report);
  	$headers = "From: emergency@".\Nudlle\DOMAIN_NAME."\r\n";
  	$headers .= "MIME-Version: 1.0\r\n";
  	$headers .= "Content-type: text/plain; charset=utf-8\r\n";
  	mail(
      \Nudlle\EMERGENCY,
      '[Nudlle] System failure in application '.\Nudlle\DOMAIN_NAME,
      $report,
      $headers,
      '-femergency@'.\Nudlle\DOMAIN_NAME
    );
  }

  private static function store_report_file($report) {
    $ok = true;
    $ok = $ok && ($log = fopen(\Nudlle\LOG_PATH.'/'.self::LOG_FILE, 'a'));
    $ok = $ok && fwrite($log, $report."\n");
    $ok = $ok && fclose($log);

    if (!$ok) {
      throw new \Nudlle\Exception\File();
    }
  }

  private static function store_report_db($report) {
    if (!\Nudlle\has_module('Database', 'Failure')) {
      throw new \Exception();
    }

    $failure = \Nudlle\Module\Database::get_record(\Nudlle\Module\Failure::get_table());
    foreach ($report as $key => $value) {
      $failure->set($key, $value);
    }
    $failure->save();
  }

  public static function store_report($text) {
    $report = [];
    $report['error'] = $text;
    $report['time'] = date('Y-m-d H:i:s');

    $request = new Request(false);
    $report['request'] = serialize($request);
    $report['login'] = null;
    if (\Nudlle\has_module('Session', 'Profile', 'Auth')) {
      try {
        $report['login'] = \Nudlle\Module\Session::get(\Nudlle\Module\Profile::DOMAIN.'.'.\Nudlle\Module\Auth::SESSION_USERNAME);
      } catch (\Nudlle\Exception\Undefined $e) {}
    }

    ob_start();
    print_r($GLOBALS);
    $report['variables'] = ob_get_contents();
    ob_end_clean();

    try {
      self::store_report_db($report);
    } catch (\Throwable $e) {
      $report_txt = "TIME: ".$report['time']."\n";
      $report_txt .= "ERROR: ".$report['error']."\n";
      $report_txt .= "VARIABLES: ".$report['variables']."\n";
      $report_txt .= "REQUEST: ".$report['request']."\n";
      $report_txt .= "LOGIN: ".$report['login']."\n";
      try {
        self::store_report_file($report_txt);
      } catch (\Throwable $e) {
        try {
          self::store_report_mail($report_txt);
        } catch (\Throwable $e) {
          echo "<p>Error report could not be saved. Please contact support.</p>\n";
        }
      }
    }
  }
}

?>
