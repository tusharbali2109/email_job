<?php
// Read env var from all possible sources (getenv, $_ENV, $_SERVER)
function _env(string $key, string $default = ''): string {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    return $_ENV[$key] ?? ($_SERVER[$key] ?? $default);
}

$databaseUrl = _env('DATABASE_URL');

if ($databaseUrl) {
    // Regex-based parser — handles passwords that contain @ : # % etc.
    // Matches: postgres[ql]://user:pass@host[:port]/dbname[?query]
    if (preg_match(
        '#^(?:postgres(?:ql)?://)'   // scheme
      . '([^:@]+)'                    // user
      . ':(.+)'                       // :pass  (greedy — stops at last @host)
      . '@([^:@/]+)'                  // @host
      . '(?::(\d+))?'                 // optional :port
      . '/([^?]+)'                    // /dbname
      . '#',
        $databaseUrl, $m
    )) {
        $user   = rawurldecode($m[1]);
        $pass   = rawurldecode($m[2]);
        $host   = $m[3];
        $port   = (int)($m[4] ?: 5432);
        $dbname = $m[5];
    } else {
        // Fallback: try parse_url for simpler URLs without special chars
        $p      = parse_url($databaseUrl);
        $host   = $p['host']              ?? 'localhost';
        $port   = (int)($p['port']        ?? 5432);
        $user   = rawurldecode($p['user'] ?? 'postgres');
        $pass   = rawurldecode($p['pass'] ?? '');
        $dbname = ltrim($p['path']        ?? '/postgres', '/');
    }
} else {
    $host   = trim(_env('DB_HOST', 'localhost'));
    $port   = (int)trim(_env('DB_PORT', '5432'));
    $dbname = trim(_env('DB_NAME', 'postgres'));
    $user   = trim(_env('DB_USER', 'postgres'));
    $pass   = trim(_env('DB_PASS', ''));
}

$dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die(
        "❌ Database Connection Error: " . $e->getMessage() .
        "<br><br>✅ <strong>Fix:</strong> In Vercel → Project Settings → Environment Variables, add:<br>" .
        "<code>DATABASE_URL=postgresql://postgres:[YOUR_PASSWORD]@db.[PROJECT].supabase.co:5432/postgres</code><br><br>" .
        "<em>Copy the exact URI from Supabase → Project Settings → Database → Connection string → URI.</em>"
    );
}
