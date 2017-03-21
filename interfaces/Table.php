<?php
namespace Nudlle\Iface;

interface Table {

  public static function validate();

  public static function get_name();

  public static function get_columns();

  public static function has_column($name);

  public static function get_primary($force_array = false);

  public static function get_auto_increment($with_seq_name = false);

  public static function get_mod_rw();

  public static function get_order();

  public static function get_semantic_options($column);

  public static function get_type($column);

  public static function is_primary($column);

  public static function is_auto_increment($column);

  public static function is_encrypted($column);

  public static function is_unsigned($column);

  public static function get_length($column);

  public static function get_scale($column);

  public static function is_empty($column);

  public static function is_null($column);

  public static function is_now($column);

  public static function is_mod_rw($column);

  public static function is_mod_rw_source($column);

  public static function is_order($column);

  public static function get_order_group();

  public static function get_foreign_key($table = null);

  public static function get_many2many($table = null);

  public static function get_mod_rw_reserved();

  public static function postprocess_row(&$row);

  public static function preprocess_data($column, $value);

}

?>
