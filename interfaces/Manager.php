<?php
namespace Nudlle\Iface;

interface Manager {

  public function update($what, $filter = null);
  public function delete($filter = null);

}

?>
