<?php
include 'db.php';

$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$company = trim($_POST['company'] ?? '');
$phone   = trim($_POST['phone']   ?? '');

if ($name && $email && $company) {
    $stmt = $pdo->prepare("INSERT INTO companies (name, email, company, contact, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$name, $email, $company, $phone]);
    header("Location: index.php?success=1");
} else {
    header("Location: index.php?error=1");
}
exit;
