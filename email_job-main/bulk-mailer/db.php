<?php
$host   = getenv('DB_HOST')   ?: 'localhost';
$port   = getenv('DB_PORT')   ?: '5432';
$dbname = getenv('DB_NAME')   ?: 'postgres';
$user   = getenv('DB_USER')   ?: 'postgres';
$pass   = getenv('DB_PASS')   ?: '';

$dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("❌ Database Connection Error: " . $e->getMessage() .
        "<br><br>✅ Solution: Set DB_HOST, DB_USER, DB_PASS, DB_NAME environment variables in Vercel.");
}
