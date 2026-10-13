<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $status = 200): void
{
    out(['ok' => false, 'error' => $message], $status);
}

function slots(PDO $db): array
{
    return $db->query(
        'SELECT id, prize, chance, retry
         FROM wheel_slots
         ORDER BY sort_order'
    )->fetchAll();
}

function need(string $role): void
{
    $pin = (string)($GLOBALS['pin'] ?? '');

    $staffPin = STAFF_PIN;
    $adminPin = ADMIN_PIN;

    $isAdmin = $adminPin !== ''
        && hash_equals($adminPin, $pin);

    $isStaff = $staffPin !== ''
        && hash_equals($staffPin, $pin);

    if ($role === 'admin' && !$isAdmin) {
        fail('Invalid PIN', 401);
    }

    if ($role === 'staff' && !$isStaff && !$isAdmin) {
        fail('Invalid PIN', 401);
    }
}

$db = null;

try {
    if (DB_HOST === '' || DB_NAME === '' ||
        DB_USER === '' || DB_PASS === '') {
        throw new RuntimeException('Database configuration missing');
    }

    $db = new PDO(
        'mysql:host=' . DB_HOST .
        ';dbname=' . DB_NAME .
        ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (Throwable $e) {
    error_log('Ripe Market DB connection error: ' . $e->getMessage());
    fail('Database connection failed. Please try again.', 500);
}

$raw = file_get_contents('php://input');
$d = json_decode($raw ?: '{}', true);

if (!is_array($d)) {
    fail('Invalid request data.', 400);
}

$a = (string)($d['action'] ?? '');
$GLOBALS['pin'] = (string)($d['pin'] ?? '');

try {
    switch ($a) {

        case 'register':
            $n = trim((string)($d['name'] ?? ''));
            $p = trim((string)($d['phone'] ?? ''));
            $e = trim((string)($d['email'] ?? ''));
            $ad = trim((string)($d['address'] ?? ''));

            if ($n === '' || $p === '' ||
                $e === '' || $ad === '') {
                fail('Please fill in all fields.');
            }

            if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
                fail('Please enter a valid email.');
            }

            if (strlen(preg_replace('/\D/', '', $p)) < 7) {
                fail('Please enter a valid phone number.');
            }

            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $code = '';
            $inserted = false;

            for ($t = 0; $t < 10; $t++) {
                $code = 'RM-';

                for ($i = 0; $i < 5; $i++) {
                    $code .= $chars[random_int(0, strlen($chars) - 1)];
                }

                try {
                    $st = $db->prepare(
                        'INSERT INTO registrations
                         (code, name, phone, email, address)
                         VALUES (?, ?, ?, ?, ?)'
                    );

                    $st->execute([$code, $n, $p, $e, $ad]);
                    $inserted = true;
                    break;

                } catch (PDOException $ex) {
                    $duplicate = (int)($ex->errorInfo[1] ?? 0) === 1062;

                    if (!$duplicate || $t === 9) {
                        throw $ex;
                    }
                }
            }

            if (!$inserted) {
                throw new RuntimeException('Registration code generation failed');
            }

            $emailSent = false;

            // mail.php remains in the project root.
            $mailFile = __DIR__ . '/../mail.php';

            if (is_file($mailFile)) {
                try {
                    require_once $mailFile;

                    if (function_exists('send_code_email')) {
                        $emailSent = (bool)send_code_email($e, $n, $code);
                    }
                } catch (Throwable $mx) {
                    error_log('Ripe Market mail error: ' . $mx->getMessage());
                }
            }

            out([
                'ok' => true,
                'code' => $code,
                'emailSent' => $emailSent
            ]);

        case 'config':
            need('staff');

            out([
                'ok' => true,
                'slots' => array_map(
                    static fn($s) => ['prize' => $s['prize']],
                    slots($db)
                )
            ]);

        case 'check':
            need('staff');

            $st = $db->prepare(
                'SELECT name, used
                 FROM registrations
                 WHERE code = ?'
            );

            $st->execute([
                strtoupper(trim((string)($d['code'] ?? '')))
            ]);

            $r = $st->fetch();

            if (!$r) {
                fail('Code not found. Please check and try again.');
            }

            if ($r['used'] === 'yes') {
                fail('This code has already been used.');
            }

            out([
                'ok' => true,
                'name' => $r['name']
            ]);

        case 'spin':
            need('staff');

            $db->beginTransaction();

            $st = $db->prepare(
                'SELECT *
                 FROM registrations
                 WHERE code = ?
                 FOR UPDATE'
            );

            $st->execute([
                strtoupper(trim((string)($d['code'] ?? '')))
            ]);

            $r = $st->fetch();

            if (!$r) {
                $db->rollBack();
                fail('Code not found.');
            }

            if ($r['used'] === 'yes') {
                $db->rollBack();
                fail('This code has already been used.');
            }

            $allSlots = slots($db);
            $total = array_sum(array_map(
                static fn($s) => (float)$s['chance'],
                $allSlots
            ));

            if (!$allSlots || $total <= 0) {
                $db->rollBack();
                fail('Wheel is not configured.');
            }

            $x = (mt_rand() / mt_getrandmax()) * $total;
            $idx = count($allSlots) - 1;

            foreach ($allSlots as $i => $slot) {
                $x -= (float)$slot['chance'];

                if ($x < 0) {
                    $idx = $i;
                    break;
                }
            }

            $chosen = $allSlots[$idx];
            $spins = (int)$r['spins'] + 1;

            $another = !empty($chosen['retry']) && $spins < 2;

            $st = $db->prepare(
                'UPDATE registrations
                 SET spins = ?, prize = ?, used = ?, spun_at = NOW()
                 WHERE id = ?'
            );

            $st->execute([
                $spins,
                $chosen['prize'],
                $another ? 'no' : 'yes',
                $r['id']
            ]);

            $db->commit();

            out([
                'ok' => true,
                'index' => $idx,
                'prize' => $chosen['prize'],
                'another' => $another
            ]);

        case 'list':
            need('admin');

            out([
                'ok' => true,
                'rows' => $db->query(
                    'SELECT id, code, name, phone, email, address,
                            created_at, prize, used, spins
                     FROM registrations
                     ORDER BY id DESC'
                )->fetchAll()
            ]);

        case 'slots':
            need('admin');

            out([
                'ok' => true,
                'slots' => slots($db)
            ]);

        case 'save_slots':
            need('admin');

            $in = $d['slots'] ?? [];

            if (!is_array($in) || count($in) < 2 || count($in) > 12) {
                fail('Wheel needs between 2 and 12 prizes.');
            }

            $sum = 0.0;

            foreach ($in as $s) {
                if (!is_array($s) ||
                    trim((string)($s['prize'] ?? '')) === '') {
                    fail('Every prize needs a name.');
                }

                $c = (float)($s['chance'] ?? 0);

                if (!is_finite($c) || $c < 0) {
                    fail('Chance cannot be negative.');
                }

                $sum += $c;
            }

            if (abs($sum - 100) > 0.01) {
                fail('Total chance must equal 100%.');
            }

            $db->beginTransaction();
            $db->exec('DELETE FROM wheel_slots');

            $ins = $db->prepare(
                'INSERT INTO wheel_slots
                 (sort_order, prize, chance, retry)
                 VALUES (?, ?, ?, ?)'
            );

            foreach ($in as $i => $s) {
                $ins->execute([
                    $i + 1,
                    trim((string)$s['prize']),
                    (float)$s['chance'],
                    !empty($s['retry']) ? 1 : 0
                ]);
            }

            $db->commit();

            out(['ok' => true]);

        case 'reset_code':
            need('admin');

            $st = $db->prepare(
                "UPDATE registrations
                 SET used = 'no', spins = 0, prize = '', spun_at = NULL
                 WHERE id = ?"
            );

            $st->execute([(int)($d['id'] ?? 0)]);

            out(['ok' => true]);

        case 'delete_reg':
            need('admin');

            $st = $db->prepare(
                'DELETE FROM registrations WHERE id = ?'
            );

            $st->execute([(int)($d['id'] ?? 0)]);

            out(['ok' => true]);

        default:
            fail('Unknown action.');
    }

} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Ripe Market API error: ' . $e->getMessage());

    fail('Server error. Please try again.', 500);
}