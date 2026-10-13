<?php
// Test: https://rohivira.com/ripe-market/test-mail.php?pin=YOUR_ADMIN_PIN&to=someone@example.com
// DELETE THIS FILE when email works.
require_once __DIR__ . '/mail.php';
if (!hash_equals(ADMIN_PIN, (string)($_GET['pin'] ?? ''))) { http_response_code(403); die('Forbidden'); }
$to = $_GET['to'] ?? '';
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) die('Add &to=valid@email.com to the URL');
$ok = send_code_email($to, 'Test', 'RM-TEST1', true);
echo $ok ? '<h3 style="color:green">Sent! Check inbox and spam.</h3>' : '<h3 style="color:red">Failed - read the error above.</h3>';
