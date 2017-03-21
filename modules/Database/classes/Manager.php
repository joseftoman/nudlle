<?php
namespace Nudlle\Module\Database;
use Nudlle\Module\Database as NDB;

class Manager extends Collector implements \Nudlle\Iface\Manager {

  protected function get_filter_from_key($key) {
    if (count($this->tables) > 1) {
      throw new \Nudlle\Exception\Model('Filtering by key value(s) is not allowed with more than one table.');
    }

    $primary = $this->tables[$this->basic_table]->get_primary(true);
    if (count($primary) > 1) {
      throw new \Nudlle\Exception\Model('Filtering by key value(s) is not allowed on tables with a compound primary key.');
    } elseif (empty($primary)) {
      throw new \Nudlle\Exception\Model('Filtering by key value(s) is not allowed on tables without a primary key.');
    } else {
      $primary = $primary[0];

      if (is_scalar($key)) {
        $cond = [ $primary, '=', $key ];
      } elseif (count($key) == 0) {
        throw new \Nudlle\Exception\App('Empty set of keys.');
      } else {
        $cond = [ $primary, 'IN', $key ];
      }
      $filter = $this->derive_filter();
      $filter->extend($cond);
    }

    return $filter;
  }

  protected function preprocess_data($column, $value) {
    $loc = $this->locate_column($column);
    return $loc[0]->preprocess_data($loc[1], $value);
  }

  protected function write_where(Filter $filter) {
    $this->query .= ' WHERE ';
    $conds = [];

    foreach (array_keys($this->tables) as $name) {
      if ($name == $this->basic_table) continue;
      $info = $this->table_map[$name];

      foreach ($info[0] as $join) {
        foreach ($join[2] as $col1 => $col2) {
          $conds[] = NDB::Q($join[0]).'.'.NDB::Q($col1).' = '.NDB::Q($join[1]).'.'.NDB::Q($col2);
        }
      }

      if (!is_null($info[1]) && !$info[1]->is_empty()) {
        $export = $info[1]->export();
        $conds[] = $export[0];
        $this->params = array_merge($this->params, $export[1]);
      }
    }

    if ($filter && !$filter->is_empty()) {
      $export = $filter->export();
      $conds[] = $export[0];
      $this->params = array_merge($this->params, $export[1]);
    }

    $this->query .= implode(' AND ', $conds);
  }

  public function update($what, $filter = null) {
    if (!is_array($what)) {
      throw new \Nudlle\Exception\App("Invalid value of parameter 'what' - array expected.");
    }
    if (is_scalar($filter) || is_array($filter)) {
      $filter = $this->get_filter_from_key($filter);
    } elseif (!is_null($filter) && !($filter instanceof Filter)) {
      throw new \Nudlle\Exception\App("Unknown format or data type of parameter 'filter'.");
    }

    $this->query = 'UPDATE '.NDB::Q($this->basic_table);
    $this->params = [];

    $set = [];
    foreach ($what as $column => $value) {
      $loc = $this->locate_column($column);
      if ($loc[0]->get_name() != $this->basic_table) {
        throw new \Nudlle\Exception\Model("Only columns of the primary table (which the manager has been constructed with) can be updated.");
      }
      $column = $loc[1];
      $this->check_column($column, $loc[0]->get_name());

      if (is_scalar($value)) {
        $set[] = NDB::Q($column).' = ?';
        $value = $this->transform_value($column, $value);
        $this->check_value($column, $value);
        $this->params[] = $this->preprocess_data($column, $value);
      } elseif (is_array($value)) {
        if (count($value) != 2) {
          throw new \Nudlle\Exception\App("Invalid value for the column '$column' - 2 items expected in the array.");
        }
        if (!is_string($value[0])) {
          throw new \Nudlle\Exception\App("Invalid value for the column '$column' - a string expression expected as the first item.");
        }
        if (!is_array($value[1])) {
          throw new \Nudlle\Exception\App("Invalid value for the column '$column' - an array expected as the second item.");
        }

        $set[] = NDB::Q($column).' = '.$value[0];
        $this->params = array_merge($this->params, $value[1]);
      } elseif (is_null($value)) {
        $set[] = NDB::Q($column).' = NULL';
      } else {
        throw new \Nudlle\Exception\App("Invalid format of the value for the column '$column' - a scalar or an array expected.");
      }
    }

    $this->query .= ' SET '.implode(', ', $set);

    $other = array_diff(array_keys($this->tables), [ $this->basic_table ]);
    if ($other) $this->query .= ' FROM '.implode(', ', array_map('\Nudlle\Module\Database::Q', $other));
    if ($other || !is_null($filter)) $this->write_where($filter);

    return $this->db->execute($this->query, $this->params);
  }

  public function delete($filter = null) {
    if (is_scalar($filter) || is_array($filter)) {
      $filter = $this->get_filter_from_key($filter);
    } elseif (!is_null($filter) && !($filter instanceof Filter)) {
      throw new \Nudlle\Exception\App("Unknown format or data type of parameter 'filter'.");
    }

    $this->query = 'DELETE FROM '.NDB::Q($this->basic_table);
    $this->params = [];

    $other = array_diff(array_keys($this->tables), [ $this->basic_table ]);
    if ($other) $this->query .= ' USING '.implode(', ', array_map('\Nudlle\Module\Database::Q', $other));
    if ($other || !is_null($filter)) $this->write_where($filter);

    return $this->db->execute($this->query, $this->params);
  }

}

?>
