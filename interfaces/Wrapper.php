<?php
namespace Nudlle\Iface;

interface Wrapper {

  public function begin();

  public function rollback();

  public function commit();

  public function last_id($sequence = null);

  public function execute($query, $params = null);

  public function fetch_array($source, $only_first = false, $result_type = null);

  public function fetch_value($source, $col_num = 0);

  public function fetch_map($source, $key_col, $data_col = null, $unique = true);

  public function fetch_column($source, $column = null);

  public function next_id($label);

}

?>
