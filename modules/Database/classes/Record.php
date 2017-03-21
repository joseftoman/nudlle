<?php
namespace Nudlle\Module\Database;
use Nudlle\Module\Database as NDB;

class Record implements \Nudlle\Iface\Record {

  protected $table;
  protected $db;
  protected $data;
  private $orig_data;

  public function __construct(Wrapper $db, Table $table, $key = null, $blind = false) {
    $this->db = $db;
    $this->table = $table;
    $this->data = $this->orig_data = [];

    if ($blind) {
      if (!is_array($key)) {
        throw new \Nudlle\Core\App("Invalid parameter '\$key': an array expected.");
      }
      foreach ($table->get_columns() as $col_name) {
        if (!array_key_exists($col_name, $key)) {
          throw new \Nudlle\Core\App("Missing value for column '$col_name'.");
        }
        $this->data[$col_name] = $key[$col_name];
      }

      $this->orig_data = $this->data;
      return;
    }

    if (is_null($key)) return;

    $mod_rw_column = $this->table->get_mod_rw();
    $primary = $this->table->get_primary(true);

    // A) $key is a single value
    if (is_scalar($key)) {
      if (count($primary) == 1) {
        $this->data = [ $primary[0] => $key ];
        try {
          $this->load();
          return;
        } catch (\Nudlle\Exception\Undefined $e) {
        } catch (\Nudlle\Exception\WrongData $e) {
        } catch (\Nudlle\Exception\Model $e) {}
      }

      if (!is_null($mod_rw_column)) {
        $this->data = [ $mod_rw_column => $key ];
        try {
          $this->load();
          return;
        } catch (\Nudlle\Exception\Undefined $e) {
        } catch (\Nudlle\Exception\WrongData $e) {
        } catch (\Nudlle\Exception\Model $e) {}
      }

    // B) $key is an array of values
    } elseif (is_array($key) && !empty($key)) {
      $load = false;
      if (\Nudlle\Core\Helper::is_assoc($key)) {
        foreach ($key as $column => $value) {
          $this->data[$column] = $value;
          $load = true;
        }
      } else {
        if (count($primary) == count($key)) {
          for ($i = 0; $i < count($primary); $i++) {
            $this->data[$primary[$i]] = $key[$i];
          }
          $load = true;
        }
      }

      if ($load) {
        try {
          $this->load();
          return;
        } catch (\Nudlle\Exception\Undefined $e) {
        } catch (\Nudlle\Exception\WrongData $e) {
        } catch (\Nudlle\Exception\Model $e) {}
      }

    // C) $key is an instance of request class
    } elseif ($key instanceof \Nudlle\Core\Request) {
      if (!empty($primary)) {
        foreach ($primary as $column) {
          if ($key->is_set($column)) {
            $this->data[$column] = $key->get($column);
          } else {
            $this->data = [];
            break;
          }
        }
      }
      if (!empty($this->data)) {
        try {
          $this->load();
          return;
        } catch (\Nudlle\Exception\Undefined $e) {
        } catch (\Nudlle\Exception\WrongData $e) {
        } catch (\Nudlle\Exception\Model $e) {}
      }

      if (!is_null($mod_rw_column) && $key->is_set($mod_rw_column)) {
        $this->data = [ $mod_rw_column => $key->get($mod_rw_column) ];
        try {
          $this->load();
          return;
        } catch (\Nudlle\Exception\Undefined $e) {
        } catch (\Nudlle\Exception\WrongData $e) {
        } catch (\Nudlle\Exception\Model $e) {}
      }
    }

    throw new \Nudlle\Exception\Undefined('No record with a given key found.');
  }

  public function dump(\Nudlle\Core\Request $request) {
    if (empty($this->data)) {
      throw \Nudlle\Exception\Model('Record is not loaded properly.');
    }
    foreach ($this->data as $key => $value) {
      $request->set_value($key, $value);
    }
  }

  protected function load() {
    if (empty($this->data)) {
      throw new \Nudlle\Exception\Model('No identifier set, can not load data');
    }

    $query = 'SELECT '.implode(', ', array_map('\Nudlle\Module\Database::Q', $this->table->get_columns()));
    $params = [];
    $columns = [];

    $filter = new Filter($this->table);
    foreach ($this->data as $column => $value) {
      $filter->extend([ $column, '=', $value ]);
    }
    $filter = $filter->export();

    $query .= ' FROM '.NDB::Q($this->table->get_name()).' WHERE '.$filter[0];
    $params = array_merge($params, $filter[1]);

    try {
      $data = $this->db->fetch_array([ $query, $params ]);
      if (empty($data)) throw new \Nudlle\Exception\DBEmpty();
    } catch (\Nudlle\Exception\DBEmpty $e) {
      $this->data = [];
      throw new \Nudlle\Exception\Undefined('No record with a given key found.');
    }
    if (count($data) > 1) {
      $this->data = [];
      throw new \Nudlle\Exception\Model('Given key is not a proper key - multiple records found.');
    }
    $data = $data[0];
    $this->table->postprocess_row($data);

    foreach ($data as $column => $value) {
      $this->data[$column] = $value;
    }
    $this->orig_data = $this->data;
  }

  protected function sync() {
    $data = $this->data;
    $primary = $this->table->get_primary(true);
    if ($primary && count(array_diff($primary, array_keys($data))) == 0) {
      $key = [];
      foreach ($primary as $column) {
        $key[$column] = $data[$column];
      }
      $this->data = $key;

      try {
        $this->load();
        foreach ($data as $column => $value) {
          $this->data[$column] = $value;
        }
        return;
      } catch (\Nudlle\Exception\Model $e) {
        throw new \Nudlle\Exception\Consistency("Primary key of table '".$this->table->get_name()."' identifies multiple records: ".print_r($key, true));
      } catch (\Nudlle\Exception\Undefined $e) {};
    }

    $mod_rw = $this->table->get_mod_rw();
    if ($mod_rw && array_key_exists($data[$mod_rw])) {
      $this->data = [ $mod_rw => $data[$mod_rw] ];
      try {
        $this->load();
        foreach ($data as $column => $value) {
          $this->data[$column] = $value;
        }
        return;
      } catch (\Nudlle\Exception\Model $e) {
        throw new \Nudlle\Exception\Consistency("Mod_rw value '".$data[$mod_rw]."' identifies multiple records in table '".$this->table->get_name()."'");
      } catch (\Nudlle\Exception\Undefined $e) {};
    }

    $this->data = $data;
    throw new \Nudlle\Exception\Model('Unable to synchronize with the database.');
  }

  public function get($column = null) {
    if (is_null($column)) {
      if (empty($this->data)) {
        throw \Nudlle\Exception\Model('Record is not loaded properly.');
      }
      return $this->data;
    }

    if (!$this->table->has_column($column)) {
      throw new \Nudlle\Exception\Model("Column '$column' does not exist in table '".$this->table->get_name()."'.");
    }

    if (!array_key_exists($column, $this->data)) {
      throw new \Nudlle\Exception\Undefined("Undefined value of column '$column' in a record of table '".$this->table->get_name()."'");
    }

    return $this->data[$column];
  }

  public function set($column, $value) {
    if (!$this->table->has_column($column)) {
      throw new \Nudlle\Exception\Model("Column '$column' does not exist in table '".$this->table->get_name()."'.");
    }
    if ($this->table->is_auto_increment($column) || $this->table->is_mod_rw($column) || $this->table->is_order($column)) {
      throw new \Nudlle\Exception\Model("Column '$column' can not be set manually.");
    }

    if (is_string($value) && mb_strlen($value) == 0
        && !$this->table->is_empty($column) && $this->table->is_null($column)
    ) {
      $value = null;
    }

    $value = Transform::value($value, $this->table->get_type($column));
    Validate::value($value, $column, $this->table);
    $this->data[$column] = $value;
  }

  // When there is a column with the 'mod_rw' feature in the table and some of
  // the source columns (feature 'mod_rw_source') have been updated, the 'mod_rw'
  // column will by updated accordingly as well.
  public function save($force_update = false) {
    /* REASONING ABOUT INSERT/UPDATE:
    When we have the original data, the record must have been loaded from DB
    (or constructed in blind mode, which is semantically the same). No matter
    what we do with the record it's still just another instance of an entity
    stored in the database. In case the entity is deleted from DB (or altered
    drastically - i.e. primary key), this instance must be invalidated. Any
    ideas along the line of checking the existence first and doing insert
    instead of update are wrong, wrong and wrong.

    Example:
    1. A request to update an entity arrives. A record is constructed by loading
    the entity from DB.
    2. The record is altered with the set() method.
    3. A concurring request deletes the entity.
    4. The record is about to be saved.

    The only reasonable action is to fail, because the entity as an underlying
    paradigm of the record no longer exists. Inserting a new one is wrong,
    because the user wants to edit an existing entity, not to create a new one.
    ----------------------------------------------------------------------------
    When we don't have the original data, the record is just an idea or a
    suggestion of a future entity. Any thoughts of "being smart" and trying to
    look up the suggested entity in DB first and then doing either insert or
    update are immensely wrong.

    Example:
    1. A request to create an entity arrives. Empty record is constructed.
    2. The record is filled with data using the set() method.
    3. A concurring request creates an entity with the same primary key but
    otherwise completely different.
    4. The record is about to be saved.

    The only reasonable action is (again) to fail, because the idea of a future
    entity has been already used and occupied. Updating an existing entity is
    wrong, because the user wanted to create a new one.
    ----------------------------------------------------------------------------
    The only downside is the case of a simple update when we know the entity's
    primary key and need to change some of its properties without reading the
    property first. The original entity could have been erased and a new one
    with the same primary key could have been created after we acquired the
    primary key and before we issued the update request. But loading the entity
    first before the actual update has no benefit because there's no way to
    detect the delete&create mumbo jumbo anyway. It would require checksums or
    similar techniques, which are (at least at the moment) out of scope of this
    framework.

    So - when we want to be super smart and super efficient and avoid the intial
    SELECT request before updating the entity, we can use $force_update = true.
    END OF REASONING */

    if ($force_update) {
      if (!empty($this->orig_data)) {
        throw new \Nudlle\Exception\App('Enforced update on a record loaded from DB: dubious, possible error -> not allowed.');
      }
      $insert = false;
    } else {
      $insert = empty($this->orig_data);
    }
    $primary = $this->table->get_primary(true);
    foreach ($primary as $column) {
      if (!isset($this->data[$column]) && !($insert && $this->table->is_auto_increment($column))) {
        throw new \Nudlle\Exception\Model('Incomplete primary key.');
      }
    }

    if ($insert) {
      foreach ($this->table->get_columns() as $column) {
        if (!array_key_exists($column, $this->data) && $this->table->is_now($column)) {
          $this->data[$column] = new \DateTime();
        }
      }
    }

    $tokens = [];
    foreach ($this->data as $column => $value) {
      // When updating, we are using only those columns that have been changed.
      // We have to disregard the primary key columns, when update is forced.
      if (!$insert && (
        (array_key_exists($column, $this->orig_data) && $value === $this->orig_data[$column])
        || ($force_update && $this->table->is_primary($column))
      )) continue;

      // $token = [ column name, SQL template, array of values for the template ]
      $token = [];
      $token[0] = $column;
      $type = $this->table->get_type($column);
      if (is_null($value)) {
        $token[1] = 'NULL';
        $token[2] = [];
      } else {
        $token[1] = '?';
        $token[2] = [ $this->table->preprocess_data($column, $value) ];
      }
      $tokens[] = $token;
    }

    $mod_rw_change = false;
    foreach ($tokens as $token) {
      if ($this->table->is_mod_rw_source($token[0])) {
        $mod_rw_change = true;
      }
    }
    if ($mod_rw_change) {
      $mod_rw = $this->update_mod_rw($insert);
      if (!is_null($mod_rw)) {
        $tokens[] = [ $mod_rw[0], '?', [ $mod_rw[1] ] ];
      }
    }

    $data = [];
    if ($insert) {
      $columns = [];
      $templates = [];

      $column = $this->table->get_order();
      if (!is_null($column)) {
        $this->data[$column] = $this->get_max_order($insert) + 1;
        $tokens[] = [ $column, '?', [ $this->data[$column] ] ];
      }

      foreach ($tokens as $token) {
        $columns[] = NDB::Q($token[0]);
        $templates[] = $token[1];
        $data = array_merge($data, $token[2]);
      }
      $query = 'INSERT INTO '.NDB::Q($this->table->get_name()).' ('.implode(', ', $columns).')';
      $query .= ' VALUES ('.implode(', ', $templates).')';
    } else {
      $items = [];
      foreach ($tokens as $token) {
        $items[] = NDB::Q($token[0]).'='.$token[1];
        $data = array_merge($data, $token[2]);
      }
      if (empty($items)) {
        return 0;
      }

      $filter = new Filter($this->table);
      foreach ($primary as $column) {
        $filter->extend([
          $column,
          '=',
          $force_update ? $this->data[$column] : $this->orig_data[$column]
        ]);
      }
      if ($filter->is_empty()) {
        throw new \Nudlle\Exception\Model('Can not update a record with no primary column.');
      } else {
        $filter = $filter->export();
      }

      $query = 'UPDATE '.NDB::Q($this->table->get_name()).' SET '.implode(', ', $items);
      $query .= ' WHERE '.$filter[0];
      $data = array_merge($data, $filter[1]);
    }

    $res = $this->db->execute($query, $data);

    if ($insert) {
      $column = $this->table->get_auto_increment(true);
      if (!is_null($column)) {
        $this->data[$column[0]] = $this->db->last_id($column[1]);
      }
    }

    $this->orig_data = $this->data;
    return $res;
  }

  protected function update_mod_rw($insert) {
    $mod_rw_column = $table->get_mod_rw();
    if (is_null($mod_rw_column)) {
      throw new \Nudlle\Exception\Model("Table '".$table->get_name()."' does not support mod_rw.");
    }

    if (!$insert && !array_key_exists($mod_rw_column, $this->data)) {
      $this->sync();
    }
    $cur_value = $insert ? null : $this->data[$mod_rw_column];

    $value = [];
    foreach ($this->table->get_columns() as $column) {
      if ($this->table->is_mod_rw_source($column)) {
        if (!array_key_exists($column, $this->data)) {
          if ($insert) {
            throw new \Nudlle\Exception\Model("Can not create mod_rw value - source column '$column' is not initialized.");
          } else {
            $this->sync();
          }
        }
        $value[] = $this->data[$column];
      }
    }
    $value = \Nudlle\Core\Format::mod_rw(implode(' ', $value));

    if ($value == $cur_value) {
      return null;
    }

    $query = 'SELECT '.NDB::Q($mod_rw_column).' FROM '.NDB::Q($table->get_name());
    try {
      $used = array_fill_keys($db->fetch_column($query), '');
    } catch (\Nudlle\Exception\DBEmpty $e) {
      $used = [];
    }
    $used = array_merge($used, array_fill_keys($this->table->get_mod_rw_reserved(), ''));

    if (array_key_exists($value, $used)) {
      $i = 1;
      while (array_key_exists($value.'-'.$i, $used)) {
        $i++;
      }
      $value = $value.'-'.$i;
    }

    $this->data[$mod_rw_column] = $mod_rw;
    return [ $mod_rw_column, $value ];
  }

  protected function get_max_order($insert) {
    $order_column = $this->table->get_order();
    if (is_null($order_column)) {
      throw new \Nudlle\Exception\Model("Table '".$this->table->get_name()."' does not support ordering.");
    }

    $filter = new Filter($this->table);
    foreach ($this->table->get_order_group() as $column) {
      if (!array_key_exists($column, $this->data)) {
        if ($insert) {
          throw new \Nudlle\Exception\Model("Can not estimate ordering index - grouping column '$column' is not initialized.");
        } else {
          $this->sync();
        }
      }

      $filter->extend([ $column, '=', $this->data[$column] ]);
    }

    $query = 'SELECT MAX('.NDB::Q($order_column).') FROM '.NDB::Q($this->table->get_name());
    $params = [];
    if (!$filter->is_empty()) {
      $filter = $filter->export();
      $query .= ' WHERE '.$filter[0];
      $params = $filter[1];
    }

    return $this->db->fetch_value([ $query, $params ]);
  }

  public function delete() {
    $filter = new Filter($this->table);
    $primary_ok = false;
    $ordered = !is_null($this->table->get_order());

    if ($ordered && empty($this->orig_data)) {
      $this->sync();
    }
    if (empty($this->orig_data)) {
      $data = &$this->data;
    } else {
      $data = &$this->orig_data;
    }

    $primary = $this->table->get_primary(true);
    if ($primary) {
      $primary_ok = true;
      foreach ($primary as $column) {
        if (array_key_exists($column, $data)) {
          $filter->extend([ $column, '=', $data[$column] ]);
        } else {
          if (!$filter->is_empty()) $filter = new Filter($this->table);
          $primary_ok = false;
          break;
        }
      }
    }
    if (!$primary_ok) {
      $mod_rw = $this->table->get_mod_rw();
      if ($mod_rw && array_key_exists($mod_rw, $data)) {
        $filter->extend([ $mod_rw, '=', $data[$mod_rw] ]);
      } else {
        foreach ($this->table->get_columns() as $column) {
          if (!array_key_exists($column, $data)) {
            throw new \Nudlle\Exception\Model('Can not delete incomplete record.');
          }
          $filter->extend([ $column, '=', $data[$column] ]);
        }
      }
    }

    unset($data);
    $filter = $filter->export();
    $query = 'DELETE FROM '.NDB::Q($this->table->get_name()).' WHERE '.$filter[0];

    $this->db->begin();
    try {
      $res = $this->db->execute($query, $filter[1]);
      if ($ordered) {
        $this->normalize_order();
      }
      $this->db->commit();
    } catch (\Throwable $e) {
      $this->db->rollback();
      throw $e;
    }

    $this->table = $this->db = null;
    $this->data = $this->orig_data = [];
    return $res;
  }

  protected function normalize_order() {
    $order_column = $this->table->get_order();
    if (is_null($order_column)) {
      throw new \Nudlle\Exception\Model("Table '".$this->table->get_name()."' does not support ordering.");
    }

    $data = &$this->orig_data;
    $group = $this->table->get_order_group();
    if (empty($this->orig_data)) {
      foreach ($group as $column) {
        if (!array_key_exists($column, $this->data)) {
          $this->sync();
          break;
        }
      }
      $data = &$this->data;
    }

    $filter = new Filter($this->table);
    foreach ($group as $column) {
      $filter->extend([ $column, '=', $data[$column] ]);
    }
    unset($data);

    $primary = $this->table->get_primary(true);
    if (!$primary) {
      $primary = [ $this->table->get_mod_rw() ];
    }

    $query = 'SELECT '.implode(', ', array_map(NDB::Q, $primary)).', '.NDB::Q($order_column);
    $query .= ' FROM '.NDB::Q($this->table->get_name());
    $params = [];
    if (!$filter->is_empty()) {
      $filter = $filter->export();
      $query .= ' WHERE '.$filter[0];
      $params = $filter[1];
    }
    $query .= ' ORDER BY '.NDB::Q($order_column).' ASC';
    try {
      $data = $this->db->fetch_array([ $query, $params ]);
      if (empty($data)) return;
    } catch (\Nudlle\Exception\DBEmpty $e) {
      // Nothing to normalize
      return;
    }

    $order = 1;
    $params = [];
    foreach ($data as $row) {
      if ($row[$order_column] != $order) {
        $row_params = [ $order ];
        foreach ($primary as $column) {
          $row_params[] = $row[$column];
        }
        $params[] = $row_params;
      }
      $order++;
    }

    if (!empty($params)) {
      $query = 'UPDATE '.NDB::Q($this->table->get_name()).' SET '.NDB::Q($order_column).' = ?';
      $query .= ' WHERE '.implode(' AND ', array_map(function($a) { return NDB::Q($a).' = ?'; }, $primary));
      $this->db->begin();
      try {
        $this->db->execute($query, $params);
        $this->db->commit();
      } catch (\Throwable $e) {
        $this->db->rollback();
        throw $e;
      }
    }
  }

  public function move($amount) {
    $order_column = $this->table->get_order();
    if (is_null($order_column)) {
      throw new \Nudlle\Exception\Model("Table '".$this->table->get_name()."' does not support ordering.");
    }
    if (empty($this->orig_data)) {
      throw new \Nudlle\Exception\App("Moving a record that has not been initialized with data from database is considered illegal.");
    }

    if (!is_numeric($amount) || !intval($amount)) {
      throw new \Nudlle\Exception\WrongData();
    }
    $amount = intval($amount);

    $this->db->begin();

    $max_order = $this->get_max_order(false);
    $order = $this->orig_data[$order_column];

    $new_order = $order + $amount;
    if ($new_order < 1 || $new_order > $max_order) {
      throw new \Nudlle\Exception\Model('Overflow detected.');
    }

    $filter = new Filter($this->table);
    foreach ($this->table->get_order_group() as $column) {
      $filter->extend([ $column, '=', $this->orig_data[$column] ]);
    }

    $query = 'UPDATE '.NDB::Q($this->table->get_name()).'
              SET '.NDB::Q($order_column).' = '.NDB::Q($order_column).' '.($amount < 0 ? '+' : '-').' 1
              WHERE '.NDB::Q($order_column).' >'.($amount < 0 ? '=' : '').' '.min($order, $new_order).'
                AND '.NDB::Q($order_column).' <'.($amount < 0 ? '' : '=').' '.max($order, $new_order);
    if ($filter->is_empty()) {
      $params = [];
    } else {
      $filter = $filter->export();
      $query .= ' AND '.$filter[0];
      $params = $filter[1];
    }

    $this->data[$order_column] = $new_order;

    try {
      $this->db->execute($query, $params);
      $this->save();
      $this->db->commit();
    } catch (\Throwable $e) {
      $this->db->rollback();
      throw $e;
    }
  }

  // Method's behaviour is very complex. Let's call $object_or_columns simply OC
  // from now on.
  // OC is boolean true: method returns object(s) of class Record
  // OC is array or string or null: method returns an array with indices
  // equivalent to the table columns
  //
  // Return value is also influenced by a type of the relation
  // Foreign key: method returns a single object or a flat array
  // Many to many: method returns an array of objects or a two-dimensional array
  //
  // When no related records exists, method returns
  // a) null (FK relation)
  // b) empty array (M2M relation)
  // or throws the DBEmpty exception.
  //
  // When OC is array (or a single string), the resulting array will contain
  // only columns specified in this manner.
  // When OC is null, all columns will be used.
  //
  // When the relation table (m2m relation) has some additional data (in columns
  // that are not used to represent the relation itself), these columns can be
  // fetched upon enumerating them in the $rel_columns parameter. They will be
  // merged with the primary data. Therefore it's available only when the
  // primary data are NOT returned as objects of the Record class. $rel_columns
  // support aliasing of the columns to avoid conflicts with the primary data.
  // $rel_columns := null | col_name | <col_list>
  // col_list := [ <col_spec>, ... ]
  // col_spec := col_name | [ col_name, col_alias ]
  public function get_related($table, $object_or_columns = null, $rel_columns = null) {
    if ($object_or_columns === true) {
      $get_object = true;
      $cols = $table->get_columns();
    } else {
      $get_object = false;
      $cols = $object_or_columns;

      if (!is_null($cols)) {
        if (is_scalar($cols)) $cols = [ $cols ];
        if (!is_array($cols)) {
          throw new \Nudlle\Exception\App('Invalid value of parameter \'$object_or_columns\': an array or a scalar value expected.');
        }
      } else {
        $cols = $table->get_columns();
      }
    }
    if (!is_null($rel_columns)) {
      if ($get_object) throw new \Nudlle\Exception\App('Invalid parameters: can not fetch relation table columns when returning objects.');
      if (is_scalar($rel_columns)) $rel_columns = [ $rel_columns ];
      if (!is_array($rel_columns)) {
        throw new \Nudlle\Exception\App('Invalid value of parameter \'$rel_columns\': an array or a scalar value expected.');
      }
    }

    $join = $this->table->get_foreign_key($table::get_name());
    if ($join) {
      $join = [ 'fk', $join[1] ];
    }
    if (!$join) {
      $join = $this->table->get_many2many($table::get_name());
      if ($join) {
        $join = [ 'm2m', $join[1], $join[2], $join[3] ];
      }
    }
    if (!$join) {
      throw new \Nudlle\Exception\Model("No relation found for table '".$table::get_name()."'.");
    }

    foreach ($cols as &$col_name) {
      $col_name = NDB::Q($table->get_name()).'.'.NDB::Q($col_name).' AS '.NDB::Q($col_name);
    }
    unset($col_name);

    if ($join[0] != 'm2m' && $rel_columns) {
      throw new \Nudlle\Exception\App('Can not fetch data from a relation table while using foreign key relation.');
    } elseif ($rel_columns) {
      foreach ($rel_columns as $col_name) {
        if (!is_array($col_name)) $col_name = [ $col_name, $col_name ];
        $cols[] = NDB::Q($join[1]).'.'.NDB::Q($col_name[0]).' AS '.NDB::Q($col_name[1]);
      }
    }

    $need = $join[0] == 'fk' ? $join[1] : $join[2];
    foreach ($need as $col1 => $col2) {
      if (!isset($col1, $this->data)) {
        throw new \Nudlle\Exception\Model("Can not fetch related records: unknown value of column '$col1'.");
      }
    }

    $query = 'SELECT '.implode(', ', $cols).' FROM '.NDB::Q($table->get_name());
    $filter = [];
    $params = [];

    if ($join[0] == 'fk') {
      foreach ($join[1] as $col1 => $col2) {
        $filter[] = NDB::Q($col2).' = ?';
        $params[] = $this->data[$col1];
      }
    } else {
      $stitches = [];
      foreach ($join[3] as $col1 => $col2) {
        $stitches[] = NDB::Q($join[1]).'.'.NDB::Q($col1).' = '.NDB::Q($table->get_name()).'.'.NDB::Q($col2);
      }
      $query .= ' JOIN '.NDB::Q($join[1]).' ON '.implode(', ', $stitches);

      foreach ($join[2] as $col1 => $col2) {
        $filter[] = NDB::Q($join[1]).'.'.NDB::Q($col2).' = ?';
        $params[] = $this->data[$col1];
      }
    }

    $query .= ' WHERE '.implode(' AND ', $filter);
    $data = $this->db->fetch_array([ $query, $params ]);

    if (!$data) return $join[0] == 'fk' ? null : [];
    foreach ($data as &$row) {
      $table->postprocess_row($row);
    }

    if ($get_object) {
      $list = [];
      foreach ($data as $row) {
        $list[] = new Record($this->db, $table, $row, true);
      }
    } else {
      $list = &$data;
    }

    if ($join[0] == 'fk') {
      return $list[0];
    } else {
      return $list;
    }
  }

}

?>
