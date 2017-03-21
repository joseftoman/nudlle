<?php
namespace Nudlle\Core;

abstract class Helper {

  const DEFAULT_PORT_HTTP = 80;
  const DEFAULT_PORT_HTTPS = 443;

  static $mime_map = [
    'text/plain' => 'txt',
    'text/html' => 'html',
    'text/css' => 'css',
    'application/javascript' => 'js',
    'application/json' => 'json',
    'application/xml' => 'xml',
    'application/x-shockwave-flash' => 'swf',
    'video/x-flv' => 'flv',

    // images
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/gif' => 'gif',
    'image/bmp' => 'bmp',
    'image/vnd.microsoft.icon' => 'ico',
    'image/tiff' => 'tiff',
    'image/svg+xml' => 'svg',

    // archives
    'application/zip' => 'zip',
    'application/x-rar-compressed' => 'rar',
    'application/vnd.ms-cab-compressed' => 'cab',

    // audio/video
    'audio/mpeg' => 'mp3',
    'video/quicktime' => 'mov',

    // adobe
    'application/pdf' => 'pdf',
    'image/vnd.adobe.photoshop' => 'psd',
    'application/postscript' => 'ps',

    // ms office
    'application/msword' => 'doc',
    'application/rtf' => 'rtf',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.ms-powerpoint' => 'ppt',

    // open office
    'application/vnd.oasis.opendocument.text' => 'odt',
    'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
  ];

  public static function is_assoc($input) {
    if (!is_array($input)) {
      throw new \Nudlle\Exception\App("Invalid value of parameter 'input' - array expected");
    }

    $last_key = -1;
    foreach ($input as $key => $val) {
      if (!is_int($key)) {
        return true;
      }
      if ($key !== $last_key + 1) {
        return true;
      }
      $last_key = $key;
    }
    return false;
  }

  public static function tokenize_path($path) {
    if (is_null($path)) {
      return [];
    }

    if (substr($path, 0, 1) == '.'
        || substr($path, -1) == '.'
        || strpos($path, ' ') !== false
        || strpos($path, '..') !== false
    ) {
      throw new \Nudlle\Exception\App('Invalid data path');
    }

    return explode('.', $path);
  }

  // Unlike array_merge_recursive() when a key is repeated, the value is overwritten.
  // When both values are arrays, they are merged.
  public static function deep_array_merge() {
    if (func_num_args() < 2) {
      throw new \Nudlle\Exception\App('No less than 2 array can be merged.');
    }
    $arrays = func_get_args();
    $merged = [];

    while ($arrays) {
      $array = array_shift($arrays);
      if (!is_array($array)) {
        throw new \Nudlle\Exception\App('Only arrays are allowed to be merged.');
      }

      foreach ($array as $key => $value) {
        if (is_string($key)) {
          if (is_array($value) && array_key_exists($key, $merged) && is_array($merged[$key])) {
            $merged[$key] = self::deep_array_merge($merged[$key], $value);
          } else {
            $merged[$key] = $value;
          }
        } else {
          $merged[] = $value;
        }
      }
    }

    return $merged;
  }

  public static function get_location($url = null) {
    $path = '';
    $loc = '';

    if ($url === null) {
      $https = isset($_SERVER['HTTPS']);
      $loc = $https ? 'https://' : 'http://';
      $loc .= $_SERVER['HTTP_HOST'];
      if (parse_url($loc, PHP_URL_PORT) === null && (
        ($https && $_SERVER['SERVER_PORT'] != self::DEFAULT_PORT_HTTPS)
        || (!$https && $_SERVER['SERVER_PORT'] != self::DEFAULT_PORT_HTTP)
      )) {
        $loc .= ':'.$_SERVER['SERVER_PORT'];
      }
      $loc .= '/';
      $path = substr($_SERVER['SCRIPT_NAME'], 1, strrpos($_SERVER['SCRIPT_NAME'], '/'));
    } else {
      $parts = parse_url($url);
      if (!$parts) {
        throw new \Nudlle\Exception\General("URL '$url' could not be parsed.");
      }
      $loc = array_key_exists('scheme', $parts) ? $parts['scheme'] : 'http';
      $loc .= '://'.$parts['host'];
      if (isset($parts['port'])) $loc .= ':'.$parts['port'];
      $loc .= '/';
      $path = isset($parts['path']) ? substr($parts['path'], 1, strrpos($parts['path'], '/')) : '';
    }

    if (strlen($path)) {
      $path = preg_replace('/\/+$/', '', $path);
      $path = array_map(function($a) { return rawurlencode($a); }, explode('/', $path));
      $loc .= implode('/', $path).'/';
    }

    return $loc;
  }

  public static function check_file_upload($label, $max, $mime = null) {
    if (!isset($_FILES[$label]['error']) || is_array($_FILES[$label]['error'])) {
      return false;
    }

    if ($_FILES[$label]['error'] !== UPLOAD_ERR_OK) {
      return $_FILES[$label]['error'];
    }

    if ($_FILES[$label]['size'] > $max) {
      return UPLOAD_ERR_FORM_SIZE;
    }

    if (is_array($mime)) {
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $allowed = array_search($finfo->file($_FILES[$label]['tmp_name']), $mime, true);
      if ($allowed === false) return -1;
    }

    return UPLOAD_ERR_OK;
  }

  public static function get_file_extension($path) {
    return self::mime_to_extension(mime_content_type($path));
  }

  public static function mime_to_extension($mime_type) {
    if (array_key_exists($mime_type, self::$mime_map)) {
      return self::$mime_map[$mime_type];
    } else {
      throw new \Nudlle\Exception\Undefined("Unknown mime type '$mime_type'");
    }
  }

}

?>
