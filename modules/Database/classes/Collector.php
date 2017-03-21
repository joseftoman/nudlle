<?php
namespace Nudlle\Module\Database;
use Nudlle\Module\Session;
use Nudlle\Module\Database as NDB;

class Collector extends TableAggregator implements \Nudlle\Iface\Collector {

  const PARAM_COL = 'order_column';
  const PARAM_DIR = 'order_direction';
  const PARAM_TABLE = 'table_label';
  const DIR_ASC = 'asc';
  const DIR_DESC = 'desc';
  const DOMAIN = 'collector';
  const KEY_ORDERING = 'ordering';

  protected $db;
  protected $table_map = [];
  protected $alias_map = [];
  protected $query;
  protected $params;
  protected $order = [];

  public function __construct(Wrapper $db, Table $table) {
    if (!\Nudlle\has_module('Session')) {
      throw new \Nudlle\Exception\App("Collector requires a Session module.");
    }
    parent::__construct($table);
    $this->db = $db;
  }

  // $order can be:
  // a) column name
  // b) flat array [ column name, direction] (A)
  // c) array of arrays [ A, A, A, ... ]
  public function set_order($order, $dir = self::DIR_ASC) {
    if (is_scalar($order)) {
      $order = [ [ $order, $dir ] ];
    } elseif (is_array($order) && is_scalar($order[0])) {
      $order = [ $order ];
    }

    if (!is_array($order)) {
      throw new \Nudlle\Exception\App('Invalid parameters');
    }
    $this->order = [];

    foreach ($order as $col_spec) {
      if (!is_array($col_spec) || count($col_spec) > 2 || count($col_spec) == 0 || !is_scalar($col_spec[0])) {
        throw new \Nudlle\Exception\App('Invalid parameters');
      }
      $this->check_column($col_spec[0]);

      if (count($col_spec) == 2) {
        if (!is_scalar($col_spec[1])) {
          throw new \Nudlle\Exception\App('Invalid parameters');
        }
        $col_spec[1] = trim(strtolower($col_spec[1]));
        if ($col_spec[1] != self::DIR_ASC && $col_spec[1] != self::DIR_DESC) {
          throw new \Nudlle\Exception\App('Invalid parameters');
        }
      } else {
        $col_spec[1] = $dir;
      }

      $this->order[] = $col_spec;
    }
  }

  public function add_table(Table $table, Filter $filter = null, $allow_null = false) {
    if (array_key_exists($table::get_name(), $this->table_map)) return;

    $primary = $table::get_primary(true);
    if (empty($primary)) {
      throw new \Nudlle\Exception\Model("Can not join table '".$table::get_name()."': no primary key found.");
    }

    $join = null;
    foreach ($this->tables as $t) {
      $join = $t::get_foreign_key($table::get_name());
      if ($join) {
        $join = [ [ $t::get_name(), $table::get_name(), $join[1] ] ];
        break;
      }
    }

    if (!$join) {
      foreach ($this->tables as $t) {
        $join = $t::get_many2many($table::get_name());
        if ($join) {
          $join = [
            [ $t::get_name(), $join[1], $join[2] ],
            [ $join[1], $table::get_name(), $join[3] ]
          ];
          break;
        }
      }
    }

    if (!$join) {
      throw new \Nudlle\Exception\Model("Can not join table '".$table::get_name()."': no relation found.");
    }

    $this->tables[$table::get_name()] = $table;
    $this->table_map[$table::get_name()] = [ $join, $filter, $allow_null ];
    return $this;
  }

  protected function generate_table_id(\Nudlle\Core\Request $request = null) {
    if (is_null($request)) {
      $table_id = array_keys($this->tables);
      sort($table_id);
      $table_id = '__'.implode('_', $table_id);
    } else {
      $table_id = '_'.$request->get_module().'_'.$request->get_operation();
    }

    return $table_id;
  }

  protected function write_columns($cols) {
    if (is_null($cols)) {
      $cols = $this->tables[$this->basic_table]->get_columns();
    } elseif (!is_array($cols)) {
      $cols = [ $cols ];
    }

    $cols_sql = [];
    $this->alias_map = [];

    foreach ($cols as $col_spec) {
      if (!is_array($col_spec)) $col_spec = [ $col_spec, $col_spec ];

      if (count($col_spec) > 2) {
        throw new \Nudlle\Exception\Model("Wrong format of column specification: '".print_r($col_spec, true)."'.");
      } else {
        $column = $col_spec[0];
        $alias = count($col_spec) == 2 ? $col_spec[1] : null;
      }

      $tmp = explode('.', $column);
      if (count($tmp) > 2) {
        throw new \Nudlle\Exception\Model("Wrong format of column name: '$column'.");
      } elseif (count($tmp) == 2) {
        $table = $tmp[0];
        $column = $tmp[1];
      } else {
        $table = $this->basic_table;
      }

      if (is_null($alias)) {
        $alias = $column;
      }

      $this->check_column($column, $table);
      $this->alias_map[$alias] = [ $table, $column ];
      $cols_sql[] = NDB::Q($table).'.'.NDB::Q($column).' AS '.NDB::Q($alias);
    }

    $this->query .= ' '.implode(', ', $cols_sql);
  }

  protected function write_source() {
    $this->query .= ' '.NDB::Q($this->basic_table);

    foreach (array_keys($this->tables) as $name) {
      if ($name == $this->basic_table) continue;
      $info = $this->table_map[$name];

      foreach ($info[0] as $join) {
        $on = [];
        foreach ($join[2] as $col1 => $col2) {
          $on[] = NDB::Q($join[0]).'.'.NDB::Q($col1).' = '.NDB::Q($join[1]).'.'.NDB::Q($col2);
        }

        if ($info[2]) $this->query .= ' LEFT';
        $this->query .= ' JOIN '.NDB::Q($join[1]).' ON '.implode(' AND ', $on);
      }

      if (!is_null($info[1]) && !$info[1]->is_empty()) {
        $filter = $info[1]->export();
        $this->query .= ' AND '.$filter[0];
        $this->params = array_merge($this->params, $filter[1]);
      }
    }
  }

  protected function write_from() {
    $this->query .= ' FROM';
    $this->write_source();
  }

  protected function write_where(Filter $filter) {
    if (!$filter->is_empty()) {
      $export = $filter->export();
      $this->query .= ' WHERE '.$export[0];
      $this->params = array_merge($this->params, $export[1]);
    }
  }

  protected function postprocess_row(&$row) {
    $tables = [];
    foreach (array_keys($row) as $key) {
      $tables[$this->alias_map[$key][0]][$this->alias_map[$key][1]] = &$row[$key];
    }
    foreach (array_keys($tables) as $table) {
      $this->tables[$table]->postprocess_row($tables[$table]);
    }
  }

  public function get_list($cols = null, \Nudlle\Core\Request $request = null, Filter $filter = null, $table_id = null) {
    if (is_null($table_id)) {
      $table_id = $this->generate_table_id($request);
    }
    if ($request) $this->set_ordering($request, $table_id);

    $this->query = 'SELECT';
    $this->params = [];

    $this->write_columns($cols);
    $this->write_from();
    if (!is_null($filter)) $this->write_where($filter);
    $this->write_order_by($table_id);

    $rows = $this->db->fetch_array([ $this->query, $this->params ]);
    for ($i = 0; $i < count($rows); $i++) {
      $this->postprocess_row($rows[$i]);
    }
    return $rows;
  }

  protected function set_ordering(\Nudlle\Core\Request $request, $table_id) {
    if ($request->is_set(self::PARAM_TABLE) && $request->get(self::PARAM_TABLE) != $table_id) {
      return;
    }

    if ($request->is_set(self::PARAM_COL)) {
      $column = $request->get(self::PARAM_COL);
      $request->remove(self::PARAM_COL);
      $this->check_column($column);

      if ($request->is_set(self::PARAM_DIR)) {
        $dir = $request->get(self::PARAM_DIR) == self::DIR_ASC ? self::DIR_ASC : self::DIR_DESC;
        $request->remove(self::PARAM_DIR);
      } else {
        $dir = self::DIR_ASC;
      }

      $order = [ $column, $dir ];
      $s = new Session(NDB::DOMAIN.'.'.self::DOMAIN.'.'.$table_id);

      if ($s->dis_set(self::KEY_ORDERING)) {
        $old_order = $s->dget(self::KEY_ORDERING);
        for ($i = 0; $i < count($old_order); $i += 2) {
          if ($old_order[$i] != $column) {
            $order[] = $old_order[$i];
            $order[] = $old_order[$i+1];
          }
        }
      }
      $s->dset(self::KEY_ORDERING, $order);
    }
  }

  protected function write_order_by($table_id) {
    $key = NDB::DOMAIN.'.'.self::DOMAIN.'.'.$table_id.'.'.self::KEY_ORDERING;
    if (Session::is_set($key)) {
      $order_session = Session::get($key);
    } else {
      $order_session = [];
    }

    $sql = [];
    $used_columns = [];
    foreach ($this->order as $col_spec) {
      if (!isset($used_columns[$col_spec[0]])) {
        $sql[] = NDB::Q($col_spec[0]).' '.$col_spec[1];
        $used_columns[$col_spec[0]] = 1;
      }
    }

    for ($i = 0; $i < count($order_session) - 1; $i += 2) {
      // There are no duplicates in this array => no need to extend $used_columns
      if (!isset($used_columns[$order_session[$i]])) {
        $sql[] = NDB::Q($order_session[$i]).' '.$order_session[$i+1];
      }
    }

    if (count($sql)) {
      $this->query .= ' ORDER BY '.implode(', ', $sql);
    }
  }

  public function derive_filter() {
    $filter = new Filter($this->tables[$this->basic_table]);
    foreach (array_keys($this->table_map) as $table) {
      $filter->add_table($this->tables[$table]);
    }
    return $filter;
  }

}

?>
