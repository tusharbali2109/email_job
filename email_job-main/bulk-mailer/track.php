<?php
require_once 'db.php';

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $pdo->prepare("UPDATE companies SET opened=1, opened_at=NOW() WHERE id=? AND opened=0")->execute([$id]);

    $ip        = $_SERVER['REMOTE_ADDR']     ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $row       = $pdo->prepare("SELECT email FROM companies WHERE id=?");
    $row->execute([$id]);
    $emailVal  = $row->fetchColumn() ?? '';

    $stmt = $pdo->prepare("INSERT INTO email_tracking (company_id, email, ip, user_agent) VALUES (?,?,?,?)");
    $stmt->execute([$id, $emailVal, $ip, $userAgent]);
}

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
exit;
