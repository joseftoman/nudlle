<?php
namespace Nudlle\Module\Baseview\Widget;
use Nudlle\Module\Baseview as BW;

class Screen extends \Nudlle\Core\Widget {

  protected $template = 'screen';
  protected $screen = true;
  protected $doctype = self::HTML5;

  protected function prepare_data() {
    $o = $this->request->get_operation();
    $title = '';
    $content = '';

    switch ($o) {
      case BW::O_HOMEPAGE:
        $title = 'Welcome';
        $content = 'This is Nudlle.';
        break;

      case BW::O_404:
        $title = '404';
        $content = 'Page does not exist.';
        break;

      case BW::O_ERROR:
        $title = 'System error';
        $content = 'There has been a system error.';
        break;

      case BW::O_ERROR_FILE:
        $title = 'File error';
        $content = 'There has been a file error.';
        break;
    }

    $this->head_data['title'] = "$title | Nudlle";
    $this->data['title'] = $title;
    $this->data['content'] = $content;
  }

}

?>
