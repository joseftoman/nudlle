<?php
namespace Nudlle\Module;

class Baseview extends \Nudlle\Core\ContentModule {

  protected $widget_map = [
    self::O_ERROR => [ self::DEFAULT_WIDGET, null ],
    self::O_ERROR_FILE => [ self::DEFAULT_WIDGET, null ],
    self::O_404 => [ self::DEFAULT_WIDGET, null ],

    self::O_HOMEPAGE => [ self::DEFAULT_WIDGET, null ],
  ];

}

?>
