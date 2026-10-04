<?php
function request_path(): string
{
    $p = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $b = base_path();
    if ($b !== '' && strpos($p, $b) === 0) $p = substr($p, strlen($b));
    $p = trim($p, '/');
    if ($p === 'index.php') $p = '';
    return $p;
}

function not_found(): never
{
    http_response_code(404);
    render('notfound', ['title' => 'Not found']);
    exit;
}

function route(): void
{
    security_headers();
    start_session();
    $path = request_path();
    $installed = file_exists(DATA_DIR . '/blog.sqlite') && (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    if (!$installed) { require __DIR__ . '/admin.php'; admin_install(); return; }
    load_plugins();
    if (is_file($tf = ROOT . '/themes/' . active_theme() . '/theme.php')) require_once $tf;
    do_action('init');
    $adminPath = setting('admin_path', 'admin');
    if ($path === $adminPath || strpos($path, $adminPath . '/') === 0) {
        require __DIR__ . '/admin.php';
        admin_route(trim(substr($path, strlen($adminPath)), '/'));
        return;
    }
    $page = max(1, (int)($_GET['page'] ?? 1));
    if ($path === '') {
        [$posts, $pages] = list_posts($page);
        render('index', ['posts' => $posts, 'page' => $page, 'pages' => $pages, 'title' => setting('site_title', 'My Blog'), 'heading' => '', 'base' => url()]);
    } elseif (preg_match('#^category/([^/]+)$#', $path, $m)) {
        [$posts, $pages] = list_posts($page, ' AND p.category=?', [$m[1]]);
        render('index', ['posts' => $posts, 'page' => $page, 'pages' => $pages, 'title' => $m[1], 'heading' => 'Category: ' . $m[1], 'base' => url($path)]);
    } elseif ($path === 'search') {
        $s = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
        $like = '%' . addcslashes($s, '%_\\') . '%';
        [$posts, $pages] = $s === '' ? [[], 0] : list_posts($page, " AND (p.title LIKE ? ESCAPE '\\' OR p.content LIKE ? ESCAPE '\\')", [$like, $like]);
        render('index', ['posts' => $posts, 'page' => $page, 'pages' => $pages, 'title' => 'Search', 'heading' => 'Search: ' . $s, 'base' => url('search') . '?q=' . rawurlencode($s) . '&']);
    } elseif ($path === 'feed.xml') {
        header('Content-Type: application/rss+xml; charset=UTF-8');
        [$posts] = list_posts(1);
        $o = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>' . e(setting('site_title')) . '</title><link>' . e(url()) . '</link><description>' . e(setting('tagline')) . '</description>';
        foreach ($posts as $p) $o .= '<item><title>' . e($p['title']) . '</title><link>' . e(url($p['slug'])) . '</link><guid>' . e(url($p['slug'])) . '</guid><pubDate>' . date(DATE_RSS, strtotime($p['created_at'])) . '</pubDate><description>' . e($p['excerpt'] ?: mb_substr(strip_tags($p['content']), 0, 300)) . '</description></item>';
        echo $o . '</channel></rss>';
    } elseif (preg_match('#^[a-z0-9-]+$#', $path) && ($post = get_post_by_slug($path))) {
        $post['content'] = apply_filters('the_content', $post['content'], $post);
        render('single', ['post' => $post, 'title' => $post['title']]);
    } else {
        not_found();
    }
}
