<?php
include 'db.php';
header('Content-Type: application/json');

$limit = isset($_GET['limit']) && (int)$_GET['limit'] > 0 ? (int)$_GET['limit'] : 0;

if ($limit > 0) {
    $stmt = $pdo->prepare("SELECT id, name, email, company FROM companies WHERE status='pending' ORDER BY id ASC LIMIT ?");
    $stmt->execute([$limit]);
} else {
    $stmt = $pdo->query("SELECT id, name, email, company FROM companies WHERE status='pending' ORDER BY id ASC");
}

echo json_encode($stmt->fetchAll());
