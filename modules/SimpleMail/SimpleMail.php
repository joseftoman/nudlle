<?php
namespace Nudlle\Module;
use Nudlle\Module\SimpleMail\PHPMailer;

abstract class SimpleMail extends \Nudlle\Core\Module {

  protected static $cfg_pattern = [
    'host' => 1,
    'username' => 1,
    'password' => 1,
    'method' => 0,
    'port' => '0i',
    'redirect' => 0,
  ];

  public static function send($data) {
    static::validate($data);

    $mailer = new PHPMailer(true);
    $mailer->CharSet = \Nudlle\ENCODING;

    $mailer->isSMTP();
    $mailer->SMTPAuth = true;
    $mailer->Host = self::get_cfg('host');
    $mailer->Username = self::get_cfg('username');
    $mailer->Password = self::get_cfg('password');
    try {
      $mailer->SMTPSecure = self::get_cfg('method');
    } catch (\Nudlle\Exception\Undefined $e) {}
    try {
      $mailer->Port = self::get_cfg('port');
    } catch (\Nudlle\Exception\Undefined $e) {}

    $address_types = [ 'to', 'cc', 'bcc', 'reply-to' ];
    try {
      $mailer->addAddress(self::get_cfg('redirect'), 'REDIRECT');
      $address_types = [ 'reply-to' ];
    } catch (\Throwable $e) {}

    foreach ($address_types as $type) {
      switch ($type) {
        case 'to': $fn = 'addAddress'; break;
        case 'cc': $fn = 'addCC'; break;
        case 'bcc': $fn = 'addBCC'; break;
        case 'reply-to': $fn = 'addReplyTo'; break;
      }

      if (array_key_exists($type, $data)) {
        foreach ($data[$type] as $item) {
          if (count($item) == 2) {
            call_user_func([ $mailer, $fn ], $item[0], $item[1]);
          } else {
            call_user_func([ $mailer, $fn ], $item[0]);
          }
        }
      }
    }

    if (isset($data['from_label']) && mb_strlen($data['from_label'])) {
      $mailer->setFrom($data['from'], $data['from_label']);
    } else {
      $mailer->setFrom($data['from']);
    }
    $mailer->Subject = $data['subject'];

    if (array_key_exists('attachment', $data)) {
      foreach ($data['attachment'] as $item) {
        if (count($item) == 2 && mb_strlen($item[1])) {
          $mailer->addAttachment($item[0], $item[1]);
        } else {
          $mailer->addAttachment($item[0]);
        }
      }
    }

    if (array_key_exists('html', $data)) {
      $mailer->isHTML(true);
      $mailer->msgHTML($data['html']);
      if (array_key_exists('txt', $data)) {
        $mailer->AltBody = $data['txt'];
      }
    } else {
      $mailer->Body = $data['txt'];
    }

    $mailer->send();
  }

  protected static function process_structure($s) {
    $o = [];

    if (is_scalar($s)) {
      return [[ $s ]];
    } elseif (!is_array($s)) {
      return false;
    }

    if (is_scalar($s[0])) {
      $s = [ $s ];
    } elseif (!is_array($s)) {
      return false;
    }

    foreach ($s as $item) {
      if (!is_array($item) || count($item) == 0 || count($item) > 2) {
        return false;
      }
      if (!is_scalar($item[0])) return false;
      if (count($item) == 2 && !is_scalar($item[1])) return false;

      if (count($item) == 2 && strlen($item[1])) {
        $o[] = [ $item[0], $item[1] ];
      } else {
        $o[] = [ $item[0] ];
      }
    }

    return $o;
  }

  protected static function validate(&$data) {
    if (!array_key_exists('from', $data)) {
      throw new \Nudlle\Exception\Undefined("Sender address ('from') is missing.");
    }
    PHPMailer::validateAddress($data['from']);
    if (!array_key_exists('subject', $data) || !mb_strlen($data['subject'])) {
      throw new \Nudlle\Exception\Undefined("Subject is missing.");
    }

    $has_body = 0;
    foreach (['html', 'txt'] as $type) {
      if (array_key_exists($type, $data)) {
        if (mb_strlen($data[$type])) {
          $has_body = 1;
        } else {
          unset($data[$type]);
        }
      }
    }
    if (!$has_body) throw new \Nudlle\Exception\Undefined("Body is missing.");

    $count = 0;
    foreach ([ 'to', 'cc', 'bcc', 'reply-to' ] as $type) {
      if (array_key_exists($type, $data)) {
        $s = self::process_structure($data[$type]);
        if ($s === false) {
          throw new \Nudlle\Exception\App("Invalid structure of $type address(es).");
        }

        foreach ($s as $item) {
          PHPMailer::validateAddress($item[0]);
          if ($type != 'reply-to') $count++;
        }
        if (count($s)) {
          $data[$type] = $s;
        } else {
          unset($data[$type]);
        }
      }
    }
    if (!$count) throw new \Nudlle\Exception\Undefined('No recipient found');

    if (array_key_exists('attachment', $data)) {
      $s = self::process_structure($data['attachment']);
      if ($s === false) {
        throw new \Nudlle\Exception\App("Invalid format of the 'attachment' field in the mail data.");
      }

      if (count($s)) {
        foreach ($s as $item) {
          if (!file_exists($item[0])) {
            throw new \Nudlle\Exception\Undefined("File '".$item[0]."' does not exist.");
          }
        }
        $data['attachment'] = $s;
      } else {
        unset($data['attachment']);
      }
    }
  }

}

?>
