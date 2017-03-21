<?php
namespace Nudlle\Iface;

interface Controller {

  public function __construct(\Nudlle\Core\Request $request, \Nudlle\Module\Database\Wrapper $db = null);
  public function get_module();
  public function is_finished();
  public function error(\Throwable $e);

}

?>
