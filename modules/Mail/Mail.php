<?php
namespace Nudlle\Module;
use Nudlle\Module\Database as NDB;
use Nudlle\Module\Session;

// TODO:
// OLD AND DEPRECATED CODE. PROBABLY NOT FUNCTIONAL.
// COMPLETE REVIEW (AND REWRITE, POSSIBLY) IS NECESSARY.

class Mail extends \Nudlle\Core\ContentModule {

  const O_SEND = 'send';
  const DOMAIN = 'has_mail';
  const REDIRECT = null; //'lingvista@razdva.cz';

  private static function check_session() {
    if (!\Nudlle\has_module('Session')) {
      throw new \Nudlle\Exception\App("Required module 'Session' not found.");
    }
  }

  public static function set_flag() {
    self::check_session();
    Session::set(self::DOMAIN, true);
  }

  public static function has_flag() {
    self::check_session();
    return Session::is_set(self::DOMAIN);
  }

  public static function clear_flag() {
    self::check_session();
    Session::clear(self::DOMAIN);
  }

  protected function run_model() {
    if ($this->request->get_operation() != self::O_SEND) return;

    $lock = $this->request->get_id();

    $manager = NDB::get_manager($this->get_table(), $this->db);
    $filter = $manager->derive_filter()->extend('rid', 'IS NULL');
    $manager->update([ 'rid' => $lock, 'time' => [ 'NOW()', [] ] ], $filter);

    $filter = $manager->derive_filter()->extend('rid', '=', $lock);
    $list = [];
    foreach ($manager->get_list(null, null, $filter) as $row) {
      $list[$row['id']] = $row;
    }

    if ($list) {
      $manager_a = NDB::get_manager($this->get_table('Attachment'), $this->db);
      $filter = $manager_a->derive_filter()->extend('id_mail', 'IN', array_keys($list));
      foreach ($manager_a->get_list(null, null, $filter) as $item) {
        $list[$item['id_mail']]['attachment'][] = [ $item['file'], $item['rename'] ];
      }
    }

    set_time_limit(0);
    $sent = [];

    foreach ($list as $id => $data) {
      try {
        $this->_send($data);
        $sent[] = $id;
      } catch (\Throwable $e) {
        ErrorHandler::process_exception($e, true);
      }
    }

    if ($sent) {
      $this->db->begin();
      try {
        $filter = $manager_a->derive_filter()->extend('id_mail', 'IN', $sent);
        $manager_a->delete($filter);
        $manager->delete($sent);
        $this->db->commit();
      } catch (\Throwable $e) {
        $this->db->rollback();
        throw $e;
      }
    }
  }

  protected function _send($data) {
    $mailer = new Mail\PHPMailer(true);
    $mailer->IsHTML(true);
    $mailer->CharSet = \Nudlle\ENCODING;

    $mailer->IsSMTP();
    $mailer->SMTPAuth = true;
    $mailer->Host = 'wedos.kybli.net';
    $mailer->Username = 'lingvik@kybli.net';
    $mailer->Password = 'sto69opic.';

    if (self::REDIRECT) {
      $mailer->AddAddress(self::REDIRECT);
    } else {
      foreach ([ 'to', 'cc', 'bcc' ] as $type) {
        switch ($type) {
          case 'to': $fn = 'AddAddress'; break;
          case 'cc': $fn = 'AddCC'; break;
          case 'bcc': $fn = 'AddBCC'; break;
        }

        if (isset($data[$type])) {
          foreach (explode(',', $data[$type]) as $address) {
            call_user_func([ $mailer, $fn ], $address);
          }
        }
      }
    }

    if (isset($data['from_label']) && mb_strlen($data['from_label'])) {
      $mailer->SetFrom($data['from'], $data['from_label']);
    } else {
      $mailer->SetFrom($data['from']);
    }
    $mailer->Subject = $data['subject'];

    if (array_key_exists('attachment', $data)) {
      foreach ($attach as $item) {
        if (!file_exists($item[0])) {
          throw new \Nudlle\Exception\Undefined("File '".$item[0]."' does not exist.");
        }

        if (count($item) == 2 && mb_strlen($item[1])) {
          $mailer->AddAttachment($item[0], $item[1]);
        } else {
          $mailer->AddAttachment($item[0]);
        }
      }
    }

    $mailer->MsgHTML($data['body']);
    if (isset($data['txt']) && mb_strlen($data['txt'])) {
      $mailer->AltBody = $data['txt'];
    }

    $mailer->send();
  }

  protected function validate(&$data) {
    if (!array_key_exists('from', $data)) {
      throw new \Nudlle\Exception\Undefined("Sender address ('from') is missing.");
    }
    NDB\Validate::email($data['from']);
    if (!array_key_exists('subject', $data) || !mb_strlen($data['subject'])) {
      throw new \Nudlle\Exception\Undefined("Subject is missing.");
    }
    if (!array_key_exists('body', $data) || !mb_strlen($data['body'])) {
      throw new \Nudlle\Exception\Undefined("Body is missing.");
    }

    $count = 0;
    foreach ([ 'to', 'cc', 'bcc' ] as $type) {
      if (isset($data[$type])) {
        foreach (explode(',', $data[$type]) as $address) {
          NDB\Validate::email($address);
          $count++;
        }
      }
    }
    if (!$count) throw new \Nudlle\Exception\Undefined('No recipient found');

    if (array_key_exists('attachment', $data)) {
      $attach = [];
      $error = false;

      if (is_scalar($data['attachment'])) {
        $attach = [ [ $data['attachment'] ] ];
      } elseif (is_array($data['attachment'])) {
        foreach ($data['attachment'] as $item) {
          if (is_scalar($item)) {
            $attach[] = [ $item ];
          } elseif (is_array($item)) {
            if (!count($item) || count($item) > 2) {
              $error = true;
            } elseif (!is_string($item[0]) || (count($item) == 2 && !is_string($item[1]))) {
              $error = true;
            } else {
              $attach[] = $item;
            }
          } else {
            $error = true;
          }
        }
      } else {
        $error = true;
      }

      if ($error) throw new \Nudlle\Exception\App("Invalid format of the 'attachment' field in the mail data.");
      if (count($attach)) {
        $data['attachment'] = $attach;
      } else {
        unset($data['attachment']);
      }
    }
  }

  public function send($data) {
    $this->validate($data);

    try {
      $this->_send($data);
      return true;
    } catch (\Throwable $e) {
      \Nudlle\Core\ErrorHandler::process_exception($e, true);

      $this->db->begin();
      try {
        $mail = new NDB\Record($this->db, self::get_table());

        foreach ([ 'from', 'subject', 'body' ] as $item) {
          $mail->set($item, $data[$item]);
        }
        foreach ([ 'to', 'cc', 'bcc', 'from_label', 'txt' ] as $item) {
          if (isset($data[$item])) $mail->set($item, $data[$item]);
        }
        $mail->set('rid', $this->request->get_id());
        $mail->set('time', time());
        $mail->save();

        foreach ($data['attachment'] as $item) {
          $attachment = new NDB\Record($this->db, self::get_table('Attachment'));
          $attachment->set('id_mail', $mail->get('id'));
          $attachment->set('file', $item[0]);
          if (count($item) == 2) $attachment->set('rename', $item[1]);
          $attachment->save();
        }

        $this->db->commit();
      } catch (\Throwable $e) {
        $this->db->fallback();
        throw $e;
      }

      return false;
    }
  }

}

?>
