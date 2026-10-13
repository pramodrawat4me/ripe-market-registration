<?php
require __DIR__.'/config.php';
header('Content-Type: application/json');
function out($a){ echo json_encode($a); exit; }
function fail($m,$c=200){ http_response_code($c); out(['ok'=>false,'error'=>$m]); }
try { $db = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS,
  [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }
catch (Exception $e) { fail('Database connection failed', 500); }

$d = json_decode(file_get_contents('php://input'), true) ?: [];
$a = $d['action'] ?? '';
$pin = (string)($d['pin'] ?? '');
function need($role){ global $pin;
  $ok = $role==='admin' ? hash_equals(ADMIN_PIN,$pin) : (hash_equals(STAFF_PIN,$pin) || hash_equals(ADMIN_PIN,$pin));
  if (!$ok) fail('Invalid PIN', 401); }
function slots($db){ return $db->query('SELECT id,prize,chance,retry FROM wheel_slots ORDER BY sort_order')->fetchAll(); }

try {
switch ($a) {

case 'register':
  $n=trim($d['name']??''); $p=trim($d['phone']??''); $e=trim($d['email']??''); $ad=trim($d['address']??'');
  if (!$n||!$p||!$e||!$ad) fail('Please fill in all fields.');
  if (!filter_var($e, FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email.');
  if (strlen(preg_replace('/\D/','',$p))<7) fail('Please enter a valid phone number.');
  $chars='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  for ($t=0;$t<10;$t++) {
    $code='RM-'; for($i=0;$i<5;$i++) $code.=$chars[random_int(0,31)];
    try { $db->prepare('INSERT INTO registrations (code,name,phone,email,address) VALUES (?,?,?,?,?)')
            ->execute([$code,$n,$p,$e,$ad]); break; }
    catch (PDOException $ex) { if ($ex->errorInfo[1]!=1062 || $t==9) throw $ex; }
  }
  // Email the code (a mail failure must never block the registration)
  $emailSent = false;
  if (file_exists(__DIR__.'/mail.php')) {
    try { require_once __DIR__.'/mail.php'; $emailSent = send_code_email($e, $n, $code); }
    catch (Throwable $mx) { error_log('Ripe Market mail error: '.$mx->getMessage()); }
  }
  out(['ok'=>true,'code'=>$code,'emailSent'=>$emailSent]);

case 'config':
  need('staff');
  out(['ok'=>true,'slots'=>array_map(fn($s)=>['prize'=>$s['prize']], slots($db))]);

case 'check':
  need('staff');
  $st=$db->prepare('SELECT name,used FROM registrations WHERE code=?'); $st->execute([strtoupper(trim($d['code']??''))]);
  $r=$st->fetch();
  if (!$r) fail('Code not found. Please check and try again.');
  if ($r['used']==='yes') fail('This code has already been used.');
  out(['ok'=>true,'name'=>$r['name']]);

case 'spin':
  need('staff');
  $db->beginTransaction();
  $st=$db->prepare('SELECT * FROM registrations WHERE code=? FOR UPDATE'); $st->execute([strtoupper(trim($d['code']??''))]);
  $r=$st->fetch();
  if (!$r) { $db->rollBack(); fail('Code not found.'); }
  if ($r['used']==='yes') { $db->rollBack(); fail('This code has already been used.'); }
  $sl=slots($db); $total=array_sum(array_column($sl,'chance'));
  if (!$sl || $total<=0) { $db->rollBack(); fail('Wheel is not configured.'); }
  $x=mt_rand()/mt_getrandmax()*$total; $idx=count($sl)-1;
  foreach ($sl as $i=>$s) { $x-=$s['chance']; if ($x<0) { $idx=$i; break; } }
  $s=$sl[$idx]; $spins=$r['spins']+1;
  $another=$s['retry'] && $spins<2;
  $db->prepare('UPDATE registrations SET spins=?, prize=?, used=?, spun_at=NOW() WHERE id=?')
     ->execute([$spins,$s['prize'],$another?'no':'yes',$r['id']]);
  $db->commit();
  out(['ok'=>true,'index'=>$idx,'prize'=>$s['prize'],'another'=>$another]);

case 'list':
  need('admin');
  out(['ok'=>true,'rows'=>$db->query('SELECT id,code,name,phone,email,address,created_at,prize,used,spins FROM registrations ORDER BY id DESC')->fetchAll()]);

case 'slots':
  need('admin'); out(['ok'=>true,'slots'=>slots($db)]);

case 'save_slots':
  need('admin');
  $in=$d['slots']??[];
  if (!is_array($in)||count($in)<2||count($in)>12) fail('Wheel needs between 2 and 12 prizes.');
  $sum=0; foreach ($in as $s) { if (!trim($s['prize']??'')) fail('Every prize needs a name.'); $c=(float)($s['chance']??0); if ($c<0) fail('Chance cannot be negative.'); $sum+=$c; }
  if (abs($sum-100)>0.01) fail('Total chance must equal 100%.');
  $db->beginTransaction(); $db->exec('DELETE FROM wheel_slots');
  $ins=$db->prepare('INSERT INTO wheel_slots (sort_order,prize,chance,retry) VALUES (?,?,?,?)');
  foreach ($in as $i=>$s) $ins->execute([$i+1,trim($s['prize']),(float)$s['chance'],!empty($s['retry'])?1:0]);
  $db->commit(); out(['ok'=>true]);

case 'reset_code':
  need('admin');
  $db->prepare("UPDATE registrations SET used='no', spins=0, prize='', spun_at=NULL WHERE id=?")->execute([(int)$d['id']]); out(['ok'=>true]);

case 'delete_reg':
  need('admin');
  $db->prepare('DELETE FROM registrations WHERE id=?')->execute([(int)$d['id']]); out(['ok'=>true]);

default: fail('Unknown action.');
}
} catch (Exception $e) { if ($db->inTransaction()) $db->rollBack(); fail('Server error. Please try again.', 500); }
