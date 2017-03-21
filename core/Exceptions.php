<?php
namespace Nudlle\Exception;

class General extends \Exception {
  protected $is_visible;

  public function __construct($message = '', $frontend = false, $code = 0) {
    parent::__construct($message, $code);
    $this->is_visible = $frontend;
    if (\Nudlle\Core\Debug::EXCEPTION >= 2 && !\Nudlle\Core\Debug::DEVEL_MODE) {
      echo '<p><b>'.get_class().':</b> <i>'.$this->getFile().', '.$this->getLine().';</i> '.$message.'</p>'."\n";
    }
  }

  public function is_visible() {
    return $this->is_visible;
  }
}

class NonFatal extends General {
  protected $is_loggable;

  public function __construct($message = '', $frontend = false, $loggable = false, $code = 0) {
    parent::__construct($message, $frontend, $code);
    $this->is_loggable = $loggable;
  }

  public function is_loggable() {
    return $this->is_loggable;
  }
}

// Thrown when class could not be loaded
class Load extends General {
  public function __construct($class_name) {
    $message = "Class '$class_name' could not be loaded.";
    parent::__construct($message);
  }
}

class Module extends General {
  public function __construct($module) {
    $message = "Requested functionallity requires module '$module', which is not available.";
    parent::__construct($message);
  }
}

// Serious error that should not exist in a production system (e.g. a wrong function call).
// The purpose is to fail hard ASAP during the development.
class App extends General {
  public function __construct($message) {
    parent::__construct($message);
  }
}

class Cfg extends General {
  public function __construct($file_name) {
    $message = "Error while reading config file '$file_name.";
    parent::__construct($message);
  }
}

// Data modelling errors - unknown columns and tables or actions that would
// corrupt the database.
class Model extends General {
  public function __construct($message = '', $frontend = false, $code = 0) {
    if (!$message) {
      $message = 'The operation is not compatible with the data model.';
    }
    parent::__construct($message, $frontend, $code);
  }
}

class Undefined extends General {
  public function __construct($message = '', $frontend = false, $code = 0) {
    if (!$message) {
      $message = 'Undefined value detected.';
    }
    parent::__construct($message, $frontend, $code);
  }
}

class Code404 extends NonFatal {
  public function __construct() {
    parent::__construct();
  }
}

class File extends NonFatal {
  public function __construct($message = '', $frontend = false, $code = 0) {
    if (!$message) {
      $message = 'File operation failed.';
    }
    parent::__construct($message, $frontend, true, $code);
  }
}

class Auth extends NonFatal {
  public function __construct($module = null, $operation = null, $user = false, $frontend = false, $code = 0) {
    if ($user === false) {
      $message = 'The user';
    } elseif ($user === null) {
      $message = 'Anonymous user';
    } else {
      $message = "The user '$user'";
    }

    $message .= ' is not allowed to run ';

    if ($module && $operation) {
      $message .= "the operation '$operation' of the module '$module'";
    } else {
      $message .= 'the requested operation';
    }

    $message .= '.';

    parent::__construct($message, $frontend, false, $code);
  }
}

class Forbidden extends NonFatal {
  public function __construct($message = '', $frontend = false, $code = 0) {
    if (!$message) {
      $message = 'An attempt to carry out an unauthorized operation detected.';
    }
    parent::__construct($message, $frontend, false, $code);
  }
}

class Consistency extends NonFatal {
  public function __construct($message = '', $frontend = false, $code = 0) {
    if (!$message) {
      // The default message should never be used. The inconsistency should be
      // described by the caller code in the $message parameter.
      $message = 'A database inconsistency detected.';
    }
    parent::__construct($message, $frontend, true, $code);
  }
}

class WrongData extends NonFatal {
  public function __construct($message = '', $frontend = false, $code = 0) {
    if (!$message) {
      $message = 'Wrong data format or structure.';
    }
    parent::__construct($message, $frontend, false, $code);
  }
}

?>
