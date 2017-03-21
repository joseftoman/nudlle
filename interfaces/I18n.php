<?php
namespace Nudlle\Iface;

interface I18n {

  public static function get_locale($use_mapping = true);
  public static function get_language();
  public static function set_locale($locale, $soft = false);
  public static function reset_locale();
  public static function get_available();

}

?>
