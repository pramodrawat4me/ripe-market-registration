<?php

declare(strict_types=1);

function envValue(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

define('DB_HOST', envValue('sql12.freesqldatabase.com'));
define('DB_NAME', envValue('sql12838807'));
define('DB_USER', envValue('sql12838807'));
define('DB_PASS', envValue('KnkcJ4pT7K'));
define('DB_PORT', (int) envValue('DB_PORT', '3306'));
define('STAFF_PIN', envValue('1234'));
define('ADMIN_PIN', envValue('9999'));

define('MAIL_FROM', envValue('pramodrawat4me@gmail.com'));
define('MAIL_FROM_NAME', envValue('Two Brothers - Ripe Market'));
define('SMTP_HOST', envValue('smtp.gmail.com'));
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', envValue('pramodrawat4me@gmail.com'));
define('SMTP_PASS', envValue('xhby vhfx fcek qnvv'));

