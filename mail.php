<?php
/** mail.php - emails the Ripe Market unique code via Gmail SMTP using PHPMailer. */
require_once __DIR__ . '/config.php';

function load_phpmailer(): bool {
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) return true;
    $auto = __DIR__ . '/vendor/autoload.php';                         // composer install
    if (file_exists($auto)) { require_once $auto; return true; }
    // manual download: works whether files are in PHPMailer/src, PHPMailer, src or this folder
    foreach ([__DIR__.'/PHPMailer/src', __DIR__.'/PHPMailer', __DIR__.'/src', __DIR__] as $dir) {
        if (file_exists("$dir/PHPMailer.php") && file_exists("$dir/SMTP.php") && file_exists("$dir/Exception.php")) {
            require_once "$dir/Exception.php";
            require_once "$dir/PHPMailer.php";
            require_once "$dir/SMTP.php";
            return true;
        }
    }
    return false;
}

function send_code_email(string $to, string $name, string $code, bool $debug = false): bool {
    if (!load_phpmailer()) {
        error_log('Ripe Market mail: PHPMailer files not found');
        if ($debug) echo 'PHPMailer files (PHPMailer.php, SMTP.php, Exception.php) not found.<br>';
        return false;
    }
    $safe = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $html = "<div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;text-align:center'>
      <h2 style='letter-spacing:2px'>TWO BROTHERS</h2>
      <p>Hi $safe, thanks for registering at our Ripe Market stall!</p><p>Your unique code:</p>
      <div style='font-size:34px;font-weight:bold;border:2px solid #111;border-radius:8px;padding:14px;margin:12px 0'>$code</div>
      <p>Show this code to our team at the stall to spin the wheel.</p></div>";
    try {
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP();
        $m->Host       = SMTP_HOST;
        $m->Port       = SMTP_PORT;
        $m->SMTPAuth   = true;
        $m->Username   = SMTP_USER;
        $m->Password   = str_replace(' ', '', SMTP_PASS);   // app password, spaces removed
        $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $m->CharSet    = 'UTF-8';
        $m->Timeout    = 15;                                // don't hang the customer's screen
        if ($debug) { $m->SMTPDebug = 2; $m->Debugoutput = 'html'; }
        $m->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $m->addAddress($to, $name);
        $m->isHTML(true);
        $m->Subject = "Your Ripe Market code: $code";
        $m->Body    = $html;
        $m->AltBody = "Hi $name, your Ripe Market code is $code. Show it at our stall to spin the wheel.";
        $m->send();
        return true;
    } catch (Throwable $e) {
        error_log('Ripe Market mail failed: ' . $e->getMessage());
        if ($debug) echo '<b>Error:</b> ' . htmlspecialchars($e->getMessage());
        return false;
    }
}
