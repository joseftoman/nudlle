<?php
namespace Nudlle\Module\Pager\Widget;
use Nudlle\Module\Database as NDB;
use Nudlle\Module\Session;
use Nudlle\Core\ContentModule as CM;
use Nudlle\Module\Pager\Model;

abstract class Bootstrap extends \Nudlle\Core\Widget {

  const INDEX_DATA = '_pager_data';
  protected $wrap = true;

  /* CONFIGURATION */
  protected $header = true;
  protected $footer = true;
  protected $sort = true;
  protected $order = false;
  protected $order_column = null;

  protected $asc = 'Seřadit vzestupně';
  protected $desc = 'Seřadit sestupně';
  protected $empty = 'Nebyly nalezeny žádné položky.';
  protected $pagesize_label = 'Velikost stránky:';
  protected $pagesize_button = 'Změnit';

  protected $first = 'První stránka';
  protected $prev = 'Předchozí stránka';
  protected $page_no = 'Stránka č.';
  protected $next = 'Následující stránka';
  protected $last = 'Poslední stránka';

  protected $detail = 'Zobrazit detail';
  protected $delete = 'Ostranit';
  protected $move_up = 'Posunout nahoru';
  protected $move_down = 'Posunout dolů';
  protected $flag_on = 'Nastavit příznak';
  protected $flag_off = 'Odebrat příznak';

  // TODO: translate & review
  /**
   * Pro kazdy sloupec lze pouzit tyto konfiguracni direktivy
   * 'label': Nazev sloupce v zahlavi. Neni-li nastaveno, pouzije se "".
   * 'align': Hodnota CSS vlastnosti 'text-align'. Neni-li nastaveno jinak,
   *    pouzije se "left".
   * 'class': CSS trida. Aplikuje se na bunky (element <td>) sloupce. Hodnotou
   *    muze byt skalar i pole. V jednom retezci muze byt i vice trid oddelenych
   *    mezerami (stejne jako se tridy zapisuji v HTML).
   * 'width': CSS hodnota pro sirku (px, %, ...)
   * 'max_length': Maximalni pocet znaku. Delsi udaje budou zkraceny a puvodni
   *    hodnota bude uvedena v atributu "title".
   * 'sort': Zapina/vypina moznost seradit tabulku podle hodnot v danem
   *    sloupci. Neni-li nastaveno, razeni nebude mozne. Direktiva nema zadny
   *    vliv, pokud je usporadani vypnuto na urovni cele tabulky. Razeni probiha
   *    na urovni databaze. Neni-li direktivou 'sort_col' receno
   *    jinak, k razeni se pouzije DB sloupec podle prislusneho indexu pole
   *    $this->cols.
   * 'sort_col': Umoznuje pouzit pro serazeni tabulky hodnoty jineho
   *    DB sloupce. Je mozne pouzit i sloupce jine tabulky (obvykly zapis pomoci
   *    tecky - tabulka.sloupec).
   * 'order': Je-li true, pracuje se s hodnou sloupce jako s poradim zaznamu.
   *    Pouziva se, kdyz je zapnuty priznak 'order' v hlavnim nastaveni.
   *    Muze byt zadano i pomoci promenne $this->order_column.
   * 'order_content': Oznacuje sloupec, jehoz obsah bude zobrazen pri pretahovani
   *    radku na novou pozici v tabulce.
   * 'html': Oznacuje sloupec, do ktereho bude vkladan obsah s HTML tagy, takze
   *    nelze provadet automaticke escapovani.
   * 'action': sloupec bude obsahovat ikonu pro provedeni operace - hodnotou
   *    je nazev operace (napr. <trida>::O_DELETE). Vsechny ostatni direktivy
   *    krome 'label' pozbyvaji platnost.
   *
   * Nasleduji direktivy platne pouze pri pouziti konkretni operace.
   * O_DETAIL|O_DELETE|O_MOVE:
   * 'icon': glyphicon ikona pro odkaz (default = glyphicon-zoom-in)
   * 'btn-type': bootstrap button type (default, success, warning, ...)
   *
   * O_TOGGLE|O_MOVE:
   * 'titles': dvojice hodnot pro atribut 'title'; prvni se pouzije, kdyz neni
   *    priznak nastaven, druha naopak. Respektive pro zmenu poradi smerem
   *    nahoru (-1) a dolu (1)
   * 'icons': dvojice ikon (glyphicon); poradi stejne jako u 'titles'
   *
   * O_TOGGLE:
   * 'column': jmeno sloupce s priznakem
   *
   * O_MOVE:
   * 'amount': relativni zmena poradi (-1 = nahoru, 1 = dolu)
   */
  protected $cols = [];

  public function __construct(\Nudlle\Core\Request $request, \Nudlle\Module\Database\Wrapper $db = null) {
    parent::__construct($request, $db);
    $this->data[self::INDEX_DATA] = [];
  }

  protected static function shorten($text, $max_length = 30) {
    if (mb_strlen($text, \Nudlle\ENCODING) <= $max_length) {
      return $this->S($text);
    } else {
      $short = parent::shorten($text, $max_length);
      return '<span title="'.$this->S($text).'">'.$this->S($short).'</span>';
    }
  }

  protected function get_count() {
    return count($this->data[self::INDEX_DATA]);
  }

  protected function push($item) {
    $this->data[self::INDEX_DATA][] = $item;
  }

  protected function pull() {
    return array_shift($this->data[self::INDEX_DATA]);
  }

  abstract protected function configure_columns();

  private function postprocess_columns() {
    foreach ($this->cols as $col_name => &$info) {
      if (empty($info)) {
        unset($this->cols[$col_name]);
        continue;
      }

      if (array_key_exists('action', $info)) {
        if (!call_user_func('\\Nudlle\\Module\\'.$this->module.'::check_auth', $info['action'])) {
          unset($this->cols[$col_name]);
          continue;
        }

        $info['html'] = true;
        $info['class'] = 'icon';
        if ($info['action'] == CM::O_TOGGLE && !array_key_exists('column', $info)) {
          $info['column'] = $col_name;
        }
      }

      if (!array_key_exists('label', $info)) {
        $info['label'] = '';
      }
    }
  }

  protected function icon_link($href, $icon, $btn = 'btn-default', $title = null) {
    $icon = $this->S($icon);
    $params = [
      'href' => $this->S($href),
      'data' => [ 'icon' => $icon ],
      'class' => [ 'btn', $this->S($btn), 'btn-xs' ],
      'role' => 'button',
    ];
    if (!is_null($title)) $params['title'] = $this->S($title);
    $span = '<span class="glyphicon '.$icon.'"></span>';

    return self::get_html_code('a', $params).$span.'</a>';
  }

  protected function icon_ajax($action, $icon, $btn = 'btn-default', $title = null, $data = []) {
    $icon = $this->S($icon);
    $data['action'] = $action;
    $data['icon'] = $icon;
    $params = [
      'data' => $data,
      'class' => [ 'btn', $this->S($btn), 'btn-xs' ],
      'type' => 'button',
    ];
    if (!is_null($title)) $params['title'] = $this->S($title);
    $span = '<span class="glyphicon '.$icon.'"></span>';

    return self::get_html_code('button', $params).$span.'</button>';
  }

  private function postprocess_data() {
    foreach ($this->data[self::INDEX_DATA] as &$item) {
      if (array_key_exists('_data', $item) && !is_array($item['_data'])) {
        unset($item['_data']);
      }

      foreach (array_keys($item) as $key) {
        if (substr($key, 0, 1) == '_') continue;

        if (!is_array($item[$key])) {
          $item[$key] = [ 'content' => $item[$key] ];
        } else {
          if (!array_key_exists('content', $item[$key])) {
            $item[$key]['content'] = '';
          }
          if (array_key_exists('class', $item[$key]) && !is_array($item[$key]['class'])) {
            $item[$key]['class'] = [ $item[$key]['class'] ];
          }
          if (array_key_exists('data', $item[$key]) && !is_array($item[$key]['data'])) {
            unset($item[$key]['data']);
          }
        }

        if ($key == 'id' && !array_key_exists('_id', $item)) {
          $item['_id'] = $item['id']['content'];
        }
      }
    }

    foreach ($this->cols as $col_name => $info) {
      if (array_key_exists('action', $info)) {
        $icons = [];
        $titles = [];
        $task = [];
        $a = $info['action'];

        foreach ($this->data[self::INDEX_DATA] as &$row) {
          if (!array_key_exists($col_name, $row)) {
            $btn = isset($info['btn-type']) ? $info['btn-type'] : 'btn-default';

            switch ($a) {
              case CM::O_DETAIL:
                $icon = isset($info['icon']) ? $info['icon'] : 'glyphicon-zoom-in';
                $href = call_user_func('\\Nudlle\\Module\\'.$this->module.'::get_link', CM::O_DETAIL, [ 'id' => $row['_id'] ]);
                $value = $this->icon_link($href, $icon, $btn, $this->detail);
                break;

              case CM::O_DELETE:
                $icon = isset($info['icon']) ? $info['icon'] : 'glyphicon-remove';
                $value = $this->icon_ajax(CM::O_DELETE, $icon, $btn, $this->delete);
                break;

              case CM::O_TOGGLE:
                $icons[$a] = isset($info['icons']) ? $info['icons'] : [ 'glyphicon-unchecked', 'glyphicon-check' ];
                $titles[$a] = isset($info['titles']) ? $info['titles'] : [ $this->flag_on, $this->flag_off ];

                $index = $row[$info['column']]['content'] ? 1 : 0;
                $value = $this->icon_ajax(CM::O_TOGGLE, $icons[$a][$index], $btn, $titles[$a][$index], [ 'column' => $info['column'] ]);
                break;

              case CM::O_MOVE:
                $icons[$a] = isset($info['icons']) ? $info['icons'] : [ 'glyphicon-chevron-up', 'glyphicon-chevron-down' ];
                $titles[$a] = isset($info['titles']) ? $info['titles'] : [ $this->move_up, $this->move_down ];
                $index = $info['amount'] < 0 ? 0 : 1;
                $value = $this->icon_ajax(CM::O_MOVE, $icons[$a][$index], $btn, $titles[$a][$index], [ 'amount' => $info['amount'] ]);
                break;

              default:
                $value = '';
            }

            $row[$col_name]['content'] = $value;
          }
        }

        switch ($a) {
          case CM::O_DETAIL:
            $task = null;
            break;

          case CM::O_DELETE:
            $task['update'] = true;
            break;

          case CM::O_TOGGLE:
            $task['icons'] = $icons[$a];
            $task['titles'] = $titles[$a];
            break;

          case CM::O_MOVE:
            $task['icons'] = $icons[$a];
            $task['titles'] = $titles[$a];
            $task['update'] = true;
            break;

          default:
            $task = null;
        }

        if (!is_null($task)) {
          if ($a == CM::O_TOGGLE) {
            $this->J(implode('.', [ self::JS_TASKS, $this->get_label(), $a, $info['column'] ]), $task);
          } else {
            $this->J(implode('.', [ self::JS_TASKS, $this->get_label(), $a ]), $task);
          }
        }
      }
    }
  }

  protected function render_header() {
    $sort = new Session(NDB::DOMAIN.'.'.Model::DOMAIN.'.'.$this->get_label());
    if ($sort->dis_set(Model::KEY_ORDERING)) {
      $sort_seq = $sort->dget(Model::KEY_ORDERING);
      $sort = [];
      for ($i = 0; $i < count($sort_seq); $i += 2) {
        $sort[$sort_seq[$i]] = [ $sort_seq[$i+1], $i / 2 ];
      }
    } else {
      $sort = [];
    }

    $buff[] = '<tr>';

    foreach ($this->cols as $col_name => $info) {
      $styles = [];
      if (array_key_exists('align', $info)) {
        $styles['text-align'] = $info['align'];
      } else {
        $styles['text-align'] = 'left';
      }

      if (array_key_exists('width', $info)) {
        $styles['width'] = $info['width'];
      }

      $line = '  '.self::get_html_code('th', [ 'style' => $styles ]);

      if (array_key_exists('sort', $info) && $info['sort'] && $this->sort) {
        if (array_key_exists('sort_col', $info)) {
          $link_col = $info['sort_col'];
        } else {
          $link_col = $col_name;
        }
        if (array_key_exists($link_col, $sort)) {
          if ($sort[$link_col][0] == Model::DIR_ASC) {
            $arrow = '&nbsp;<span class="pager-order '.($sort[$link_col][1] == 0 ? 'asc' : 'asc2').'"></span>';
            $title = $this->desc;
            $new_dir = Model::DIR_DESC;
          } else {
            $arrow = '&nbsp;<span class="pager-order '.($sort[$link_col][1] == 0 ? 'desc' : 'desc2').'"></span>';
            $title = $this->asc;
            $new_dir = Model::DIR_ASC;
          }
        } else {
          $arrow = '';
          $title = $this->asc;
          $new_dir = Model::DIR_ASC;
        }

        $line .= sprintf(
          '<a title="%s" href="%s" data-update="1">%s%s</a>',
          $title,
          $this->get_link_repeat([
            Model::PARAM_COL => $link_col,
            Model::PARAM_DIR => $new_dir,
            Model::PARAM_TABLE => $this->get_label()
          ]),
          $info['label'],
          $arrow
        );
      } elseif (array_key_exists('order', $info) && $info['order']) {
        $line .= '<span class="help" title="Pořadí v tabulce lze měnit tažením jednotlivých řádků."></span>';
      } else {
        $line .= $info['label'];
      }

      $line .= '</th>';
      $buff[] = $line;
    }
    $buff[] = '</tr>';

    return $buff;
  }

  protected function render_rows() {
    $buff = [];

    while ($item = $this->pull()) {
      $attributes = [];
      if (array_key_exists('_data', $item)) {
        $attributes['data'] = $item['_data'];
      }
      if (array_key_exists('_id', $item)) {
        $attributes['data']['id'] = $item['_id'];
      }
      if ($this->order) {
        $attributes['data']['position'] = $item[$this->order_column]['content'];
      }
      if (array_key_exists('_class', $item)) {
        $attributes['class'][] = $item['_class'];
      }
      if ($this->order) {
        $attributes['class'][] = 'ordered';
      }
      $buff[] = self::get_html_code('tr', $attributes);

      foreach ($this->cols as $col_name => $info) {
        $attributes = [];
        if (array_key_exists('align', $info)) {
          $attributes['style']['text-align'] = $info['align'];
        } else {
          $attributes['style']['text-align'] = 'left';
        }
        if (array_key_exists('width', $info)) {
          $attributes['style']['width'] = $info['width'];
        }
        if (array_key_exists('data', $item[$col_name])) {
          $attributes['data'] = $item[$col_name]['data'];
        }

        if (array_key_exists('class', $info)) {
          if (is_array($info['class'])) {
            $attributes['class'] = array_merge($attributes['class'], $info['class']);
          } else {
            $attributes['class'][] = $info['class'];
          }
        }
        if (array_key_exists('class', $item[$col_name])) {
          $attributes['class'] = array_merge($attributes['class'], $item[$col_name]['class']);
        }

        if (!array_key_exists('html', $info) || !$info['html']) {
          $item[$col_name]['content'] = $this->S($item[$col_name]['content']);
        }

        $line = self::get_indent_space().self::get_html_code('td', $attributes);
        if (array_key_exists('max_length', $info)) {
          $line .= $this->shorten($item[$col_name]['content'], $info['max_length']);
        } else {
          $line .= $item[$col_name]['content'];
        }
        $line .= '</td>';
        $buff[] = $line;
      }
      $buff[] = '</tr>';
    }

    return $buff;
  }

  protected function render_footer() {
    $pagination = new Session(NDB::DOMAIN.'.'.Model::DOMAIN.'.'.$this->get_label().'.'.Model::KEY_PAGINATION);
    $page = $pagination->dget(Model::KEY_PAGE);
    $s = self::get_indent_space();

    $buff[] = '<tr>';
    $buff[] = $s.'<td class="footer" colspan="'.count($this->cols).'">';
    $buff[] = $s.$s.'<form class="pull-left" action="'.$this->get_link_repeat().'">';
    $buff[] = $s.$s.$s.'<div class="input-group">';
    $buff[] = $s.$s.$s.$s.'<span class="input-group-addon"><label for="pager-pagesize-input">'.$this->pagesize_label.'</label></span>';
    $buff[] = $s.$s.$s.$s.'<input type="text" class="form-control" id="pager-pagesize-input" name="'.Model::PARAM_SIZE.'" value="'.$pagination->dget(Model::KEY_SIZE).'">';
    $buff[] = $s.$s.$s.$s.'<input type="hidden" name="'.Model::PARAM_TABLE.'" value="'.$this->get_label().'">';
    $buff[] = $s.$s.$s.$s.'<span class="input-group-btn">';
    $buff[] = $s.$s.$s.$s.$s.'<input class="btn btn-default" type="submit" value="'.$this->pagesize_button.'" data-update="1">';
    $buff[] = $s.$s.$s.$s.'</span>';
    $buff[] = $s.$s.$s.'</div>';
    $buff[] = $s.$s.'</form>';

    $buff[] = $s.$s.'<nav class="pull-right">';
    $buff[] = $s.$s.$s.'<ul class="pagination">';

    $link_num = '<li><a title="%s" href="%s" data-update="1">%s</a></li>';
    $link_glyph = '<li><a title="%s" href="%s" data-update="1"><span class="glyphicon %s"></span></a></li>';
    $span_glyph = '<li class="disabled"><span class="glyphicon %s"></span></li>';
    $params = [ Model::PARAM_TABLE => $this->get_label() ];
    $link_buff = [];

    if ($pagination->dget(Model::KEY_FIRST)) {
      $params[Model::PARAM_PAGE] = 1;
      $link_buff[] = sprintf($link_glyph, $this->first, $this->get_link_repeat($params), 'glyphicon-backward');
    } else {
      $link_buff[] = sprintf($span_glyph, 'glyphicon-backward');
    }
    if (count($pagination->dget(Model::KEY_PREV)) > 0) {
      $params[Model::PARAM_PAGE] = $page - 1;
      $link_buff[] = sprintf($link_glyph, $this->prev, $this->get_link_repeat($params), 'glyphicon-triangle-left');
    } else {
      $link_buff[] = sprintf($span_glyph, 'glyphicon-triangle-left');
    }

    foreach ($pagination->dget(Model::KEY_PREV) as $number) {
      $params[Model::PARAM_PAGE] = $number;
      $link_buff[] = sprintf($link_num, $this->page_no.$number, $this->get_link_repeat($params), $number);
    }
    $link_buff[] = '<li class="active"><span>'.$page.'</span></li>';
    foreach ($pagination->dget(Model::KEY_NEXT) as $number) {
      $params[Model::PARAM_PAGE] = $number;
      $link_buff[] = sprintf($link_num, $this->page_no.$number, $this->get_link_repeat($params), $number);
    }

    if (count($pagination->dget(Model::KEY_NEXT)) > 0) {
      $params[Model::PARAM_PAGE] = $page + 1;
      $link_buff[] = sprintf($link_glyph, $this->next, $this->get_link_repeat($params), 'glyphicon-triangle-right');
    } else {
      $link_buff[] = sprintf($span_glyph, 'glyphicon-triangle-right');
    }
    if ($pagination->dget(Model::KEY_LAST)) {
      $params[Model::PARAM_PAGE] = Model::LAST_PAGE;
      $link_buff[] = sprintf($link_glyph, $this->last, $this->get_link_repeat($params), 'glyphicon-forward');
    } else {
      $link_buff[] = sprintf($span_glyph, 'glyphicon-forward');
    }

    static::indent_buffer($link_buff, 4);
    $buff = array_merge($buff, $link_buff);

    $buff[] = $s.$s.$s.'</ul>';
    $buff[] = $s.$s.'</nav>';

    $buff[] = $s.'</td>';
    $buff[] = '</tr>';

    return $buff;
  }

  protected function output() {
    $buff = array();
    $s = self::get_indent_space();

    if ($this->get_count() == 0) {
      $this->write_buffer([ '<p class="paginated">'.$this->empty.'</p>' ]);
      return;
    }

    $count = Session::get(implode('.', [NDB::DOMAIN, Model::DOMAIN, $this->get_label(), Model::KEY_PAGINATION, Model::KEY_COUNT]));
    $buff[] = '<table class="paginated table table-bordered table-condensed table-hover'.($this->order ? ' ordered' : '').'" data-count="'.$count.'">';

    if ($this->header) {
      $buff[] = $s.'<thead>';
      $sub_buff = $this->render_header();
      static::indent_buffer($sub_buff, 2);
      $buff = array_merge($buff, $sub_buff);
      $buff[] = $s.'</thead>';
    } else {
      $buff[] = $s.'<thead><tr><th class="mini_header" colspan="'.count($this->cols).'"></td></tr></thead>';
    }

    $buff[] = $s.'<tbody>';
    $sub_buff = $this->render_rows();
    static::indent_buffer($sub_buff, 2);
    $buff = array_merge($buff, $sub_buff);
    $buff[] = $s.'</tbody>';

    if ($this->footer) {
      $buff[] = $s.'<tfoot>';
      $sub_buff = $this->render_footer();
      static::indent_buffer($sub_buff, 2);
      $buff = array_merge($buff, $sub_buff);
      $buff[] = $s.'</tfoot>';
    } else {
      $buff[] = $s.'<tfoot><tr><td class="mini_footer" colspan="'.count($this->cols).'"></td></tr></tfoot>';
    }

    $buff[] = '</table>';
    $this->write_buffer($buff);
  }

  private function postprocess_configuration() {
    if ($this->order) {
      if (!call_user_func('\\Nudlle\\Module\\'.$this->module.'::check_auth', CM::O_MOVE)) {
        $this->order = false;
      } else {
        $this->sort = false;

        if (is_null($this->order_column)) {
          foreach ($this->cols as $col_name => $info) {
            if (array_key_exists('order', $info) && $info['order']) {
              if (!is_null($this->order_column)) {
                throw new \Nudlle\Exception\App("Only one column can have the 'order' attribute.");
              }
              $this->order_column = $col_name;
            }
          }
        }
        if (is_null($this->order_column)) {
          throw new \Nudlle\Exception\App("No column with the 'order' attribute found.");
        }

        $this->request->set(Model::PARAM_COL, $this->order_column);
        $this->request->set(Model::PARAM_DIR, Model::DIR_ASC);
        $this->request->set(Model::PARAM_TABLE, $this->get_label());
      }
    }
  }

  public function render() {
    $this->configure_columns();
    $this->postprocess_configuration();
    $this->postprocess_columns();

    $this->prepare_data();
    $this->postprocess_data();

    $this->output();

    \Nudlle\Module\Js::require_lib('core');
    $this->add_css_file('bootstrap', 'Pager');
    $this->add_js_file('bootstrap', 'Pager');
  }

}

?>
