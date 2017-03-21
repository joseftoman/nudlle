<?php
// The whole index.php is just a minimalistic wrapper of the application. It
// sets up class loading and global error/exception handling. It's the only
// source file that is directly accessible (single point of entry).

// Every piece of the framework's code is encapsulated in the namespace Nudlle.
namespace Nudlle;

/*****************/
/* Configuration */
/*****************/

// IMPORTANT: YOU NEED TO SET THESE CONSTANTS AND FUNCTIONS!

// (Intended) domain name of your application (also works as an universal label)
// This value will not be used for generating URIs
const DOMAIN_NAME = 'example.com';
// E-mail address for sending emergency messages
const EMERGENCY = 'john.doe@example.com';
// Timezone of your application ('Europe/Prague', 'Pacific/Tahiti', etc.)
const TIMEZONE = 'UTC';
// Default locale of your application. Has virtually no use when using module
// I18n - locale is reset as soon as the incoming request is processed.
const LOCALE = 'en_GB.UTF-8';

// You need to provide a default operation (and its module) when no operation
// is set. The default operation may depend on the current context so we need
// a function for this.
function translate_default() {
  return [ 'Baseview', Module\Baseview::O_HOMEPAGE ];
}

// END OF IMPORTANT

// Notice: default values are good and you don't need to change them. In fact
// it is recommended not to change them. But it's your application after all...

// Directory with the Nudlle's core classes
const CORE_PATH = 'core';
// Directory with the Nudlle's modules
const MODULES_PATH = 'modules';
// Directory with the Nudlle's interfaces
const INTERFACES_PATH = 'interfaces';
// Directory with log files
const LOG_PATH = 'logs';
// Directory with a content not belonging to Nudlle.
const NOT_NUDLLE_PATH = 'not_nudlle';
// Encoding of the application
const ENCODING = 'UTF-8';
// Session domain for modules
const MODULES_DOMAIN = 'modules';

/************************/
/* End of configuration */
/************************/

// Neccessary for asynchronous requests - they pass on the SID as a parameter
// ini_set('session.use_only_cookies', 0);

spl_autoload_register('Nudlle\autoload');

ini_set('default_charset', ENCODING);
date_default_timezone_set(TIMEZONE);
setlocale(LC_COLLATE, LOCALE);
setlocale(LC_CTYPE, LOCALE);
setlocale(LC_MONETARY, LOCALE);
setlocale(LC_TIME, LOCALE);

$has_session = false;
if (file_exists(MODULES_PATH.'/Session') && file_exists(MODULES_PATH.'/Session/Session.php')) {
  $reflect = new \ReflectionClass('Nudlle\Module\Session');
  if ($reflect->implementsInterface('Nudlle\Iface\Session')) {
    $has_session = true;
  }
}

set_error_handler([ 'Nudlle\Core\ErrorHandler', 'process_error' ]);
set_exception_handler([ 'Nudlle\Core\ErrorHandler', 'process_exception' ]);

// All output is buffered. Reasons:
// 1. Buffer is discarded in case of error and error message is displayed instead.
// 2. Writing output before session is initialized is not a problem
// 3. All output can be postprocessed if needed
ob_start();

$app = new Core\Application();
$app->start();

while (ob_get_level()) {
  ob_end_flush();
}

function autoload($class_name) {
  if (substr($class_name, 0, 1) == '\\') $class_name = substr($class_name, 1);
  $path = explode('\\', $class_name);

  if (count($path) == 1) {
    $file_name = NOT_NUDLLE_PATH.'/classes/'.$path[0].'.php';
    if (file_exists($file_name)) {
      require_once $file_name;
    } else {
      throw new Exception\Load($class_name);
    }
  } elseif ($path[0] == 'Nudlle') {
    if (count($path) == 3) {
      if ($path[1] == 'Core') {
        $file_name = CORE_PATH.'/'.$path[2].'.php';
      } elseif ($path[1] == 'Exception') {
        $file_name = CORE_PATH.'/Exceptions.php';
      } elseif ($path[1] == 'Module') {
        $file_name = MODULES_PATH.'/'.$path[2].'/'.$path[2].'.php';
      } elseif ($path[1] == 'Iface') {
        $file_name = INTERFACES_PATH.'/'.$path[2].'.php';
      }

      if (file_exists($file_name)) {
        require_once $file_name;
      } else {
        throw new Exception\Load($class_name);
      }
    } elseif ($path[1] == 'Module' && count($path) > 3) {
      try {
        call_user_func([ 'Nudlle\\Module\\'.$path[2], 'load' ], implode('\\', array_slice($path, 3)));
      } catch (Exception\Load $e) {
        throw new Exception\Load($class_name);
      }
    } else {
      throw new Exception\Load($class_name);
    }
  } else {
    throw new Exception\Load($class_name);
  }
}

function fallback() {
  try {
    ob_start();
    $request = new Core\Request();
    // The previous request is used in order to sustain a correct succession of
    // PIDs (and (possibly) a correct function of the "back" button). The
    // request that has caused the error is not stored in the history.
    $request->restore();
    $request->set_module('Baseview');
    $request->set_operation(Core\ContentModule::O_ERROR);
    $db = null;
    if (has_module('Database')) {
      $db = Module\Database::get_wrapper();
    }
    $baseview = new Module\Baseview($db);
    $baseview->run_operation($request);
    ob_end_flush();
  } catch (\Throwable $e) {
    ob_end_clean();

    // In case there is something wrong with the Baseview module, database is
    // not working, or something of similar importance has gone haywire, we use
    // a complete fallback. It is bare HTML code and there is nothing that could
    // go wrong.
    echo '<!DOCTYPE html>'."\n";
    echo '<html><head><title>Error</title><meta charset="UTF-8"></head><body>'."\n";
    echo '<p>Dear user,<br>this boring white page is an unmistakable sign of a serious error. It is bad and it should not happen (at least not too often).</p>'."\n";
    echo '<p>The good thing about this situation is that this site is using PHP framework Nudlle. What is so good about it? A complete log of the current unfortunate situation has been created and stored even before you started to read this. A person responsible for maintenance of this site will be notified about the situation you have just experienced and should make everything work again very soon.</p>'."\n";
    echo '<p>You can try to go back to the <a href="index.php">main page</a>, it should not cause any harm. But it is highly probable that it will not work either.</p></body></html>'."\n";
  }
}

function has_module() {
  global $has_session;

  for ($i = 0; $i < func_num_args(); $i++) {
    $module_name = func_get_arg($i);
    $module_ok = true;

    if ($module_name == 'Session') {
      if (!$has_session) return false;
      continue;
    }

    if ($has_session && !Core\Debug::DEVEL_MODE) {
      $s = new Module\Session(MODULES_DOMAIN);
      if ($s->dis_set($module_name)) {
        if ($s->dget($module_name)) {
          continue;
        } else {
          return false;
        }
      }
    }

    $module_ok = file_exists(MODULES_PATH.'/'.$module_name);
    $module_ok = $module_ok && file_exists(MODULES_PATH.'/'.$module_name.'/'.$module_name.'.php');

    if ($module_ok) {
      try {
        $interface = interface_exists('Nudlle\\Iface\\'.$module_name);
      } catch (Exception\Load $e) {
        $interface = false;
      }
      if ($interface) {
        $reflect = new \ReflectionClass('Nudlle\Module\\'.$module_name);
        if (!$reflect->implementsInterface('Nudlle\Iface\\'.$module_name)) {
          $module_ok = false;
        }
      }
    }

    if ($module_ok) {
      $module_ok = call_user_func('\\Nudlle\\Module\\'.$module_name.'::test_dependencies');
    }

    if ($has_session && !Core\Debug::DEVEL_MODE) {
      $s->dset($module_name, $module_ok);
    }
    if (!$module_ok) return false;
  }

  return true;
}

function check_module ($module) {
  if (!has_module($module)) throw new \Nudlle\Exception\Module($module);
}

?>
