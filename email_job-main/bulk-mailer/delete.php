<?php
include 'db.php';
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id   = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM companies WHERE id=?");
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Delete failed']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
}
