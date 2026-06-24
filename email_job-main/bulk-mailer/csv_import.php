<?php
include 'db.php';

if ($_FILES['csv']['tmp_name']) {
    $file = fopen($_FILES['csv']['tmp_name'], "r");
    fgetcsv($file); // skip header

    $stmt = $pdo->prepare("INSERT INTO companies (name, email, company, contact) VALUES (?, ?, ?, ?)");
    while (($data = fgetcsv($file, 1000, ",")) !== false) {
        $name    = trim($data[0] ?? '');
        $email   = trim($data[1] ?? '');
        $company = trim($data[2] ?? '');
        $phone   = trim($data[3] ?? '');
        if ($name && $email) {
            $stmt->execute([$name, $email, $company, $phone]);
        }
    }
    fclose($file);
}
header("Location: index.php");
