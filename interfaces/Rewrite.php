<?php
namespace Nudlle\Iface;

interface Rewrite {

  const MODULE_SUPERCLASS = '\\Nudlle\\Core\\ContentModule';
  const PARAM = 'rewrite_param';

  public static function translate_request();

}

?>
