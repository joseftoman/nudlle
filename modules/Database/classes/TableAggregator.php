<?php
namespace Nudlle\Module\Database;

abstract class TableAggregator {

  protected $basic_table;
  protected $tables = [];

  public function __construct(Table $table) {
    $this->basic_table = $table->get_name();
    $this->tables[$this->basic_table] = $table;
  }

  public function add_table(Table $table, Filter $filter = null, $allow_null = false) {
    $this->tables[$table->get_name()] = $table;
    return $this;
  }

  protected function locate_column($column) {
    $column = explode('.', $column);
    $table = count($column) == 1 ? $this->basic_table : array_shift($column);
    if (!array_key_exists($table, $this->tables)) {
      throw new \Nudlle\Exception\Undefined("Table '$table' not found.");
    }
    $table = $this->tables[$table];
    return [ $table, $column[0] ];
  }

  protected function check_value($column, $value) {
    list($table, $column) = $this->locate_column($column);
    Validate::value($value, $column, $table);
  }

  protected function transform_value($column, $value) {
    list($table, $column) = $this->locate_column($column);
    return Transform::value($value, $table->get_type($column));
  }

  protected function check_column($column, $table = null) {
    if ($table) {
      $parts = [ $table, $column ];
    } else {
      $parts = explode('.', $column);
    }

    if (count($parts) > 2) {
      throw new \Nudlle\Exception\Model("Wrong format of column name: '$column'.");
    } elseif (count($parts) == 1) {
      if (!$this->tables[$this->basic_table]->has_column($parts[0])) {
        throw new \Nudlle\Exception\Model('Column "'.$parts[0].'" does not exist in table "'.$this->basic_table.'".');
      }
    } else {
      if (!array_key_exists($parts[0], $this->tables)) {
        throw new \Nudlle\Exception\Undefined('Unknown table "'.$parts[0].'".');
      }
      if (!$this->tables[$parts[0]]->has_column($parts[1])) {
        throw new \Nudlle\Exception\Model('Column "'.$parts[1].'" does not exist in table "'.$parts[0].'".');
      }
    }
  }

  protected function normalize_column($column) {
    $parts = explode('.', $column);
    if (count($parts) == 1) {
      $column = $this->basic_table.'.'.$column;
    }
    return $column;
  }

  protected function get_type($column) {
    list($table, $column) = $this->locate_column($column);
    return $table->get_type($column);
  }

  protected function is_encrypted($column) {
    list($table, $column) = $this->locate_column($column);
    return $table->is_encrypted($column);
  }

}

?>
