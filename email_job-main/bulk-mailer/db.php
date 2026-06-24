<?php
$databaseUrl = getenv('DATABASE_URL');

if ($databaseUrl) {
    $p    = parse_url($databaseUrl);
    $host = $p['host'];
    $port = $p['port'] ?? 5432;
    $user = $p['user'] ?? 'postgres';
    $pass = rawurldecode($p['pass'] ?? '');
    $dbname = ltrim($p['path'] ?? '/postgres', '/');
} else {
    $host   = getenv('DB_HOST') ?: 'localhost';
    $port   = getenv('DB_PORT') ?: '5432';
    $dbname = getenv('DB_NAME') ?: 'postgres';
    $user   = getenv('DB_USER') ?: 'postgres';
    $pass   = getenv('DB_PASS') ?: '';
}

$dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("❌ Database Connection Error: " . $e->getMessage() .
        "<br><br>✅ Solution: In Vercel, set DATABASE_URL to your Supabase connection URI " .
        "(e.g. postgresql://postgres:password@db.xxx.supabase.co:5432/postgres), " .
        "OR set DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS individually.");
}
