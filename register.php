<?php
require_once __DIR__ . '/config.php';   // must come first, mail.php needs these settings
require_once __DIR__ . '/mail.php';

// ... validate name, phone, email, address ...
// ... generate $code and INSERT into the database ...

// Add the email code here, after the insert:
$emailSent = send_code_email($email, $name, $code);
echo json_encode(['ok'=>true, 'code'=>$code, 'emailSent'=>$emailSent]);