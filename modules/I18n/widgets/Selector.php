<?php
namespace Nudlle\Module\I18n\Widget;
use Nudlle\Module\Baseview as BW;

class Selector extends \Nudlle\Core\Widget {

  protected function output() {
    $s = self::get_indent_space();
    $buff = [];
    $current = \Nudlle\Module\I18n::get_locale();
    $available = \Nudlle\Module\I18n::get_available();
    $soft = false;

    if (count($available) <= 1) return;

    $buff[] = '<aside id="i18n_selector">';

    foreach ($available as $code => $label) {
      if ($code == $current) {
        $buff[] = $s.'<span class="i18n '.$code.'" title="'.mb_convert_case($label, MB_CASE_TITLE).'"></span>';
      } else {
        \Nudlle\Module\I18n::set_locale($code, true);
        $soft = true;
        $link = $this->get_link_repeat();
        $buff[] = $s.'<a class="i18n '.$code.'" title="'.mb_convert_case($label, MB_CASE_TITLE).'" href="'.$link.'"></a>';
      }
    }

    if ($soft) \Nudlle\Module\I18n::reset_locale();
    $buff[] = '</aside>';

    self::write_buffer($buff);
  }

}

?>
