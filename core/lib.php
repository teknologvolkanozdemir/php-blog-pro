<?php
// Core helpers: DB, settings, escaping, URLs.

function db(): PDO
{
    static $pdo = null;
    if (!$pdo) {
        if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0750, true);
        $pdo = new PDO('sqlite:' . DATA_DIR . '/blog.sqlite', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec("CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY, username TEXT UNIQUE NOT NULL, password TEXT NOT NULL, display_name TEXT NOT NULL DEFAULT '')");
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings(k TEXT PRIMARY KEY, v TEXT)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS posts(id INTEGER PRIMARY KEY, type TEXT NOT NULL DEFAULT 'post', title TEXT NOT NULL, slug TEXT UNIQUE NOT NULL, excerpt TEXT NOT NULL DEFAULT '', content TEXT NOT NULL DEFAULT '', category TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'draft', author_id INTEGER, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts(id INTEGER PRIMARY KEY, ip TEXT NOT NULL, ts INTEGER NOT NULL)");
    }
    return $pdo;
}

function q(string $sql, array $args = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st;
}

function setting(string $k, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT k,v FROM settings')->fetchAll() as $r) $cache[$r['k']] = $r['v'];
    }
    return $cache[$k] ?? $default;
}

function set_setting(string $k, string $v): void
{
    q('INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v', [$k, $v]);
}

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    $d = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $d;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}

function admin_url(string $path = ''): string
{
    return url(setting('admin_path', 'admin') . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function slugify(string $s): string
{
    $s = strtr($s, ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'Ç' => 'c', 'Ğ' => 'g', 'İ' => 'i', 'I' => 'i', 'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u']);
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'n-' . bin2hex(random_bytes(3));
}

function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
