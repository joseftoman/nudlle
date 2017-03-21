<?php
namespace Nudlle\Module\Database;
use Nudlle\Module\Database as NDB;

class Filter extends TableAggregator implements \Nudlle\Iface\Filter {

  const O_AND = 1;
  const O_OR = 2;
  const O_NOT = 4;

  const COLUMN = 'column';
  const OPERATOR = 'operator';
  const VALUE = 'value';

  const LEAF = 32;
  const NODE = 64;

  private $structure = null;
  private static $operators = [
    '=', '!=', '<', '>', '<=', '>=', '~', '~*', '!~', '!~*',
    'IN', 'NOT IN', 'LIKE', 'NOT LIKE', 'SIMILAR TO', 'NOT SIMILAR TO',
    'IS NULL', 'IS NOT NULL'
  ];
  private static $untyped_operators = [
    '~', '~*', '!~', '!~*', 'LIKE', 'NOT LIKE', 'SIMILAR TO', 'NOT SIMILAR TO'
  ];
  private static $unary_operators = [
    'IS NULL', 'IS NOT NULL'
  ];
  private static $no_concat = [
    NDB::BINARY, NDB::DATE, NDB::DATETIME, NDB::BOOL
  ];

  public function __construct(Table $table, $input = null) {
    parent::__construct($table);
    if (!is_null($input)) {
      $this->extend($input);
    }
  }

  private function validate(&$input, $operator) {
    // Filtering on a single boolean value can be set just by the column name
    if (is_scalar($input)) $input = [ $input ];

    if (!($input instanceof Filter) && (!is_array($input) || empty($input))) {
      throw new \Nudlle\Exception\App("Invalid type of parameter 'input'.");
    }
    $test = ($operator & self::O_AND) + ($operator & self::O_OR);
    if ($test == self::O_AND + self::O_OR || (!$this->is_empty() && $test == 0)) {
      throw new \Nudlle\Exception\App("Invalid value of parameter 'operator'.");
    }

    if ($input instanceof Filter) {
      if (is_null($this->structure)) {
        throw new \Nudlle\Exception\App("Initialization of Filter with another Filter is not allowed.");
      }
      return true;
    }

    // When $input is an associative array, convert it to normal array
    if (array_key_exists(self::COLUMN, $input) && array_key_exists(self::OPERATOR, $input)) {
      $tmp = [ $input[self::COLUMN], $input[self::OPERATOR] ];
      if (array_key_exists(self::VALUE, $input)) {
        $tmp[] = $input[self::VALUE];
      }
      $input = $tmp;
      unset($tmp);
    }

    if (count($input) < 1 || count($input) > 3) {
      throw new \Nudlle\Exception\App("Parameter 'input': invalid structure - single scalar or array with 1-3 items required.");
    }

    if (is_array($input[0])) {
      if (count($input[0]) == 0) {
        throw new \Nudlle\Exception\App("Parameter 'input': list of columns can not be empty.");
      } elseif (count($input[0]) == 1) {
        $input[0] = $input[0][0];
      }
    }

    if (!is_array($input[0])) {
      $input[0] = $this->normalize_column($input[0]);
      $this->check_column($input[0]);
    } else {
      foreach ($input[0] as &$column) {
        $column = $this->normalize_column($column);
        $this->check_column($column);
        if (in_array($this->get_type($column), self::$no_concat)) {
          throw new \Nudlle\Exception\Model("Column '$column' can not be used in column concatenation (wrong type).");
        }
      }
    }

    if (!is_array($input[0]) && $this->get_type($input[0]) == NDB::BOOL && count($input) == 1) {
      return true;
    }

    if (count($input) < 2) {
      throw new \Nudlle\Exception\App('Operator is missing.');
    }

    $input[1] = strtoupper($input[1]);
    if (!in_array($input[1], self::$operators)) {
      throw new \Nudlle\Exception\App('Unknown operator "'.$input[1].'".');
    }
    if (in_array($input[1], self::$unary_operators)) {
      if (count($input) > 2) throw new \Nudlle\Exception\App('Operator "'.$input[1].'" does not allow secondary operand.');
    } elseif (count($input) != 3) {
      throw new \Nudlle\Exception\App('Second operand is missing.');
    }

    if (!is_array($input[0])) {
      if ($this->is_encrypted($input[0]) && !in_array($input[1], self::$unary_operators)) {
        throw new \Nudlle\Exception\Model('Operator "'.$input[1].'" is not allowed to be used with an encrypted column.');
      } elseif (
        in_array($this->get_type($input[0]), [ NDB::DATE, NDB::DATETIME ])
        && in_array($input[1], self::$untyped_operators)
      ) {
        throw new \Nudlle\Exception\Model('Operator "'.$input[1].'" is not allowed to be used with a date(time) column.');
      } elseif (
        $this->get_type($input[0]) == NDB::BOOL
        && !in_array($input[1], self::$unary_operators)
      ) {
        throw new \Nudlle\Exception\Model('Operator "'.$input[1].'" is not allowed to be used with a boolean column.');
      }
    }
    if (count($input) == 3 && !is_array($input[0])) {
      $input[2] = $this->transform_value($input[0], $input[2]);
    }

    if ($input[1] == 'IN' || $input[1] == 'NOT IN') {
      if (!is_array($input[2]) || empty($input[2])) {
        throw new \Nudlle\Exception\App('Operator "'.$input[1].'" requires a non-empty array as the second operand.');
      }
      // Typ se nekontroluje, protoze pujde o textove zretezeni sloupcu.
      if (!is_array($input[0])) {
        foreach ($input[2] as $value) {
          $this->check_value($input[0], $value);
        }
      }
    } elseif (!in_array($input[1], self::$unary_operators)) {
      if (is_array($input[2])) {
        throw new \Nudlle\Exception\App('Operator "'.$input[1].'" does not allow an array as the second operand.');
      }
      // Typ se nekontroluje, protoze pujde o textove zretezeni sloupcu.
      if (!is_array($input[0]) && !in_array($input[1], self::$untyped_operators)) {
        $this->check_value($input[0], $input[2]);
      }
    }

    return true;
  }

  public function extend($input, $operator = self::O_AND) {
    $this->validate($input, $operator);

    if (is_null($this->structure)) {
      // tree init
      $this->structure = [ self::LEAF | ($operator & self::O_NOT), $input ];
    } else {
      if (!($input instanceof Filter)) {
        $input = [ self::LEAF, $input ];
      } elseif ($operator & self::O_NOT && $input->is_negated()) {
        throw new \Nudlle\Exception\Model('Double negation detected, possible error -> not allowed.');
      }
      // new node
      $this->structure = [ self::NODE, $operator, [ $this->structure, $input ] ];
    }

    return $this;
  }

  private function node_to_sql($node) {
    if ($node instanceof Filter) {
      return $node->export();
    }

    if ($node[0] == self::NODE) {
      list($l_sql, $l_data) = $this->node_to_sql($node[2][0]);
      list($r_sql, $r_data) = $this->node_to_sql($node[2][1]);
      $operator = $node[1] & self::O_AND ? 'AND' : 'OR';
      if ($node[1] & self::O_NOT) {
        $operator .= ' NOT';
      }
      $sql = '('.$l_sql.' '.$operator.' '.$r_sql.')';
      $data = array_merge($l_data, $r_data);
      return [ $sql, $data ];
    } else {
      $negation = $node[0] & self::O_NOT;
      $node = $node[1];
      $data = [];
      $loc = null;

      if (!is_array($node[0])) {
        $sql = NDB::Q($node[0]);
        $loc = $this->locate_column($node[0]);
      } else {
        // CONCAT_WS = no troubles with NULL values.
        $sql = 'CONCAT_WS(" "';
        foreach ($node[0] as $name) {
          $sql .= ', '.NDB::Q($name);
        }
        $sql .= ')';
      }

      if (count($node) > 1) {
        $sql .= ' '.$node[1];

        if (count($node) == 3) {
          if (is_array($node[2])) {
            $value = '('.implode(', ', array_fill(0, count($node[2]), '?')).')';
            if ($loc) {
              $data = array_merge($data, array_map(
                [ $loc[0], 'preprocess_data' ],
                array_fill(0, count($node[2]), $loc[1]),
                $node[2]
              ));
            } else {
              $data = array_merge($data, $node[2]);
            }
          } else {
            $value = '?';
            $data[] = $loc ? $loc[0]->preprocess_data($loc[1], $node[2]) : $node[2];
          }
          $sql .= ' '.$value;
        }
      }

      $sql = ($negation ? 'NOT ' : '').'('.$sql.')';
      return [ $sql, $data ];
    }
  }

  public function export() {
    if ($this->is_empty()) {
      throw new \Nudlle\Exception\App('Can not export an empty filter.');
    }
    return $this->node_to_sql($this->structure);
  }

  public function is_empty() {
    return is_null($this->structure) ? true : false;
  }

  /*
   * Filters are independent units that can be combined together freely. But at
   * one point one filter needs to know this information about another filter.
   * This function is meant only for a "filter to filter" communication. It can
   * be used anywhere else without any harm, but I don't see any possible use
   * case. It would be defined as private if the filters weren't independent.
   * That's why it's not included in the interface. It's not supposed to be a
   * public functionality. Its "publicness" is demanded only by this specific
   * implementation.
   */
  public function is_negated() {
    return ($this->structure[0] & self::LEAF) && ($this->structure[0] & self::O_NOT);
  }

}

?>
