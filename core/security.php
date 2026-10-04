<?php
// Sessions, CSRF, auth, brute-force throttling, headers, HTML sanitizer.

function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('X-Powered-By:');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000');
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('blogsid');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict']);
    ini_set('session.use_strict_mode', '1');
    session_start();
    // Idle timeout: 30 minutes
    if (isset($_SESSION['last']) && time() - $_SESSION['last'] > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last'] = time();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function current_user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $r = q('SELECT id,username,display_name FROM users WHERE id=?', [$_SESSION['uid']])->fetch();
            $u = $r ?: null;
        }
    }
    return $u;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function login_locked(): bool
{
    q('DELETE FROM login_attempts WHERE ts < ?', [time() - 900]);
    return (int)q('SELECT COUNT(*) FROM login_attempts WHERE ip=?', [client_ip()])->fetchColumn() >= 5;
}

function attempt_login(string $user, string $pass): bool
{
    if (login_locked()) return false;
    $r = q('SELECT id,password FROM users WHERE username=?', [$user])->fetch();
    // Constant-ish time: always verify against a hash
    $hash = $r['password'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    if ($r && password_verify($pass, $hash)) {
        q('DELETE FROM login_attempts WHERE ip=?', [client_ip()]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$r['id'];
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            q('UPDATE users SET password=? WHERE id=?', [password_hash($pass, PASSWORD_DEFAULT), $r['id']]);
        }
        return true;
    }
    q('INSERT INTO login_attempts(ip,ts) VALUES(?,?)', [client_ip(), time()]);
    return false;
}

function sanitize_html(string $html): string
{
    if (trim($html) === '') return '';
    $allowed = ['p','br','b','strong','i','em','u','s','a','ul','ol','li','blockquote','pre','code','h1','h2','h3','h4','h5','h6','img','hr','table','thead','tbody','tr','th','td','span','div','figure','figcaption'];
    $attrs = ['href','src','alt','title','width','height','class'];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $body = $dom->getElementsByTagName('body')->item(0);
    $walk = function (DOMNode $n) use (&$walk, $allowed, $attrs) {
        foreach (iterator_to_array($n->childNodes) as $c) {
            if ($c instanceof DOMElement) {
                $tag = strtolower($c->tagName);
                if (!in_array($tag, $allowed, true)) {
                    if (in_array($tag, ['script','style','iframe','object','embed','form','svg','math','link','meta'], true)) {
                        $n->removeChild($c);
                        continue;
                    }
                    $walk($c);
                    while ($c->firstChild) $n->insertBefore($c->firstChild, $c);
                    $n->removeChild($c);
                    continue;
                }
                foreach (iterator_to_array($c->attributes) as $a) {
                    $name = strtolower($a->name);
                    $val = $a->value;
                    $bad = !in_array($name, $attrs, true);
                    if (!$bad && in_array($name, ['href','src'], true)) {
                        $clean = preg_replace('/[\x00-\x20]+/', '', html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        $bad = !preg_match('#^(https?:|mailto:|/|\#|[^:]*$)#i', $clean);
                    }
                    if ($bad) $c->removeAttribute($a->name);
                }
                if ($tag === 'a' && $c->hasAttribute('href')) $c->setAttribute('rel', 'noopener noreferrer nofollow');
                $walk($c);
            } elseif ($c instanceof DOMComment || $c instanceof DOMProcessingInstruction) {
                $n->removeChild($c);
            }
        }
    };
    $walk($body);
    $out = '';
    foreach ($body->childNodes as $c) $out .= $dom->saveHTML($c);
    return $out;
}
