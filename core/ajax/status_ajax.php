<?php
namespace mara\core\ajax;

use mara\core\User;
use mara\core\Status;

class status_ajax
{

/**
 * Get current runtime status
 *
 * @access public
 * @return void
 */
public function getstatus(): void
  {
    User::loggedIn();

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        [
          'success' => true,
          'status'  => Status::get()
        ],
        JSON_UNESCAPED_UNICODE
    );
  }

}
?>