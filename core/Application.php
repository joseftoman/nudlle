<?php
namespace Nudlle\Core;
use Nudlle\Module\Session;

class Application {

  private $db = null;
  private $request;
  private static $debug_token = null;
  private $default_operation = false;

  public function __construct() {
    if (\Nudlle\has_module('Session')) {
      Session::init();
    }

    if (\Nudlle\has_module('Rewrite')) {
      \Nudlle\Module\Rewrite::translate_request();
    }
    if (\Nudlle\has_module('Database')) {
      $this->db = \Nudlle\Module\Database::get_wrapper();
    }

    $this->request = new Request(false);

    self::$debug_token = $this->request->get_id();
  }

  private function set_default($anonymous = false) {
    if ($anonymous && \Nudlle\has_module('Auth')) {
      list($module, $operation) = \Nudlle\Module\Auth::get_anonymous_fallback();
    } else {
      list($module, $operation) = \Nudlle\translate_default();
    }
    $this->request->set_module($module);
    $this->request->set_operation($operation);
    $this->default_operation = true;
  }

  public static function log($text) {
    Debug::log($text, self::$debug_token);
  }

  private function redirect() {
    $url = $this->request->get_redirect();
    if (!preg_match('/^[a-z][a-z0-9.+-]*:\/\//', $url)) {
      $url = Helper::get_location().$url;
    }
    header('Location: '.html_entity_decode($url));
    if (\Nudlle\has_module('Session')) {
      Session::finish();
    }
    exit;
  }

  public function start() {
    $is_404 = $this->request->get_operation() == \Nudlle\Module\Baseview::O_404;
    if ($is_404) header('HTTP/1.1 404 Not Found');

    if ($this->request->is_ajax() && ($this->request->is_ping() || $is_404)) return;

    if (\Nudlle\has_module('Auth')) {
      if ($this->request->get_operation() == \Nudlle\Module\Baseview::O_LOGOUT) {
        \Nudlle\Module\Auth::logout($this->request);
      } elseif (
        !\Nudlle\Module\Auth::identify($this->request, $this->db)
        && !$is_404
        && !\Nudlle\Module\Auth::allow_anonymous()
      ) {
        $this->set_default(true);
      }

      if ($this->request->is_redirected()) $this->redirect();
    }
    unset($is_404);

    // TODO: move to module
    //try {
    //  Authorize(...);
    //} catch (Authorization_Exception $e) {
    //  ...
    //}

    $controller = null;
    if (\Nudlle\has_module('Controller')) {
      $controller = new \Nudlle\Module\Controller($this->request, $this->db);
    } elseif (!$this->request->get_module() || !$this->request->get_operation()) {
      $this->set_default();
    }

    $finished = false;
    while (!$finished) {
      if (is_null($controller)) {
        $module = $this->request->get_module();
      } else {
        $module = $controller->get_module();
      }
      if (!\Nudlle\has_module($module)) {
        throw new \Nudlle\Exception\Module($module);
      }
      $module = '\\Nudlle\\Module\\'.$module;
      $module = new $module($this->db);

      try {
        $module->run_operation($this->request);
        if (is_null($controller)) {
          $finished = true;
        } else {
          $finished = $controller->is_finished();
        }
      } catch (\Throwable $e) {
        if (Debug::EXCEPTION && !Debug::DEVEL_MODE) {
          Debug::dump_exception($e);
        }

        if (is_null($controller)) {
          $finished = $this->error($e);
        } else {
          $finished = $controller->error($e);
        }
      }
    }

    if (
      !$this->request->is_redirected()
      && (
        (!$this->request->is_ajax() && !$this->request->is_file())
        || $this->request->is_required()
      )
    ) {
      $this->request->store();
    } else {
      $this->request->shift();
    }

    if ($this->request->is_redirected()) $this->redirect();

    if (\Nudlle\has_module('Session')) {
      Session::finish();
    }
  }

  private function error(\Throwable $e) {
    if ($e instanceof \Nudlle\Exception\Code404) {
      header('HTTP/1.1 404 Not Found');

      if ($this->request->is_ajax()) return true;
      if ($this->request->is_file()) $this->request->no_file();

      $this->request->set_module('Baseview');
      $this->request->set_operation(ContentModule::O_404);
      return false;
    }

    if ($e instanceof \Nudlle\Exception\Auth && $this->request->is_ajax()) {
      $this->request->set_module('Baseview');
      $this->request->set_operation(ContentModule::O_NOAUTH);
      $this->request->noauth();
      return false;
    }

    if (($e instanceof \Nudlle\Exception\General && $e->is_visible()) || Debug::REPORT_ALL || DEBUG::DEVEL_MODE) {
      $this->request->add_error($e->getMessage());
    } else {
      $this->request->add_error();
    }

    if ($e instanceof \Nudlle\Exception\NonFatal && $e->is_loggable()) {
      if (DEBUG::DEVEL_MODE && !$this->request->is_ajax()) {
        throw $e;
      } else {
        ErrorHandler::process_exception($e, true, $this->request->is_ajax());
      }
    }

    if ($e instanceof \Nudlle\Exception\NonFatal) {
      if ($this->request->is_ajax()) {
        header('Content-type: application/json; charset='.\Nudlle\ENCODING);
        echo $this->request->to_json();
        return true;
      }

      if ($this->request->is_file()) {
        $this->request->no_file();
        $this->request->set_module('Baseview');
        $this->request->set_operation(ContentModule::O_ERROR_FILE);
        return false;
      }

      if ($this->default_operation) {
        $this->request->set_module('Baseview');
        $this->request->set_operation(ContentModule::O_ERROR);
      } else {
        try {
          $this->request->back();
        } catch (\Nudlle\Exception\Undefined $e) {
          $this->set_default();
        }
      }

      return false;
    }

    throw $e;
  }

}

?>
