<?php
namespace Nudlle\Iface;

interface Auth {

  public static function identify(\Nudlle\Core\Request $request, \Nudlle\Module\Database\Wrapper $db);
  public static function get_roles();
  public static function has_role($role);
  public static function add_role($profile, $role, \Nudlle\Module\Database\Wrapper $db);
  public static function remove_role($profile, $role, \Nudlle\Module\Database\Wrapper $db);
  public static function change_password($record, $password, $old_password = null);
  public static function is_anonymous();
  public static function allow_anonymous();
  public static function get_anonymous_fallback();

}

?>
