<?php
namespace Nudlle\Module\Pager;
use Nudlle\Module\Session;
use Nudlle\Module\Database as NDB;

class Model extends NDB\Collector {

  const PARAM_PAGE = 'pager_page';
  const PARAM_SIZE = 'pager_size';
  const PAGE_SIZE = 20;
  const ADJACENT = 5;
  const LAST_PAGE = 'last';
  const KEY_PAGINATION = 'pagination';

  const KEY_PAGE = 'page';
  const KEY_SIZE = 'size';
  const KEY_FIRST = 'first';
  const KEY_LAST = 'last';
  const KEY_PREV = 'prev';
  const KEY_NEXT = 'next';
  const KEY_FROM = 'from';
  const KEY_COUNT = 'count';

  public function get_list($cols = null, \Nudlle\Core\Request $request = null, NDB\Filter $filter = null, $table_id = null) {
    if (is_null($table_id)) {
      $table_id = $this->generate_table_id($request);
    }
    if ($request) $this->set_ordering($request, $table_id);
    $this->calculate_pagination($request, $table_id, $filter);

    $this->query = 'SELECT';
    $this->params = [];

    $this->write_columns($cols);
    $this->write_from();
    if (!is_null($filter)) $this->write_where($filter);
    $this->write_order_by($table_id);
    $this->write_pagination($table_id);

    $rows = $this->db->fetch_array([ $this->query, $this->params ]);
    for ($i = 0; $i < count($rows); $i++) {
      $this->postprocess_row($rows[$i]);
    }
    return $rows;
  }

  protected function calculate_pagination(\Nudlle\Core\Request $request, $table_id, $filter = null) {
    if ($request->is_set(self::PARAM_TABLE) && $request->get(self::PARAM_TABLE) != $table_id) {
      return;
    }

    $s = new Session(NDB::DOMAIN.'.'.self::DOMAIN.'.'.$table_id.'.'.self::KEY_PAGINATION);

    if ($request && $request->is_set(self::PARAM_PAGE)) {
      $page = $request->get(self::PARAM_PAGE);
      $request->remove(self::PARAM_PAGE);
    } elseif ($s->dis_set(self::KEY_PAGE)) {
      $page = $s->dget(self::KEY_PAGE);
    } else {
      $page = 1;
    }

    if ($request && $request->is_set(self::PARAM_SIZE)) {
      $size = $request->get(self::PARAM_SIZE);
      $request->remove(self::PARAM_SIZE);
    } elseif ($s->dis_set(self::KEY_SIZE)) {
      $size = $s->dget(self::KEY_SIZE);
    } else {
      $size = self::PAGE_SIZE;
    }
    if (!is_numeric($size) || intval($size) <= 0) {
      $size = self::PAGE_SIZE;
    } else {
      $size = intval($size);
    }

    $s->dset(self::KEY_SIZE, $size);
    $prev = [];
    $next = [];
    $s->dset(self::KEY_FIRST, false);
    $s->dset(self::KEY_LAST, false);

    $this->query = 'SELECT COUNT(*)';
    $this->params = [];
    $this->write_from();
    if (!is_null($filter)) $this->write_where($filter);
    $count = $this->db->fetch_value([ $this->query, $this->params ]);
    if ($count == 0) {
      $page_count = 0;
    } else {
      $page_count = ceil($count / $size);
    }
    $s->dset(self::KEY_COUNT, $count);

    if ($page == self::LAST_PAGE) {
      $page = $page_count;
    }

    if (!is_numeric($page) || $page < 1) {
      $page = 1;
    } else {
      $page = intval($page);
      if ($page_count > 0 && $page > $page_count) {
        $page = $page_count;
      }
    }
    $s->dset(self::KEY_PAGE, $page);

    $from = ($page - 1) * $size + 1;
    $s->dset(self::KEY_FROM, $from);

    $i = max(1, $page - self::ADJACENT);
    if ($i > 1) {
      $s->dset(self::KEY_FIRST, true);
    }
    while ($i < $page) {
      $prev[] = $i++;
    }
    $s->dset(self::KEY_PREV, $prev);

    $i = min($page + self::ADJACENT, $page_count);
    if ($i < $page_count) {
      $s->dset(self::KEY_LAST, true);
    }
    while ($i > $page) {
      array_unshift($next, $i--);
    }
    $s->dset(self::KEY_NEXT, $next);
  }

  protected function write_pagination($table_id) {
    $key = NDB::DOMAIN.'.'.self::DOMAIN.'.'.$table_id.'.'.self::KEY_PAGINATION;
    $pagination = Session::get($key);

    $offset = $pagination[self::KEY_FROM] - 1;
    $size = $pagination[self::KEY_SIZE];
    $this->query .= ' LIMIT '.$size.' OFFSET '.$offset;
  }

}

?>
