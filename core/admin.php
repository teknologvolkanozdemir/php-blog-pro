<?php
// Admin panel (served under the configurable admin path).

const RESERVED_PATHS = ['feed', 'search', 'category', 'themes', 'plugins', 'core', 'data', 'index', 'install', 'assets'];

function admin_layout(string $title, callable $body): void
{
    $f = flash();
    $nav = ['' => 'Dashboard', 'posts' => 'Posts', 'pages' => 'Pages', 'themes' => 'Themes', 'plugins' => 'Plugins', 'import-export' => 'Import / Export', 'settings' => 'Settings'];
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . e($title) . ' ‹ Admin</title><style>
body{margin:0;font:15px/1.5 system-ui,sans-serif;background:#f1f1f1;color:#222;display:flex;min-height:100vh}
nav{width:200px;background:#1d2327;padding:16px 0;flex-shrink:0}nav a{display:block;color:#ccc;padding:8px 20px;text-decoration:none}nav a:hover,nav a.on{background:#2271b1;color:#fff}
nav b{display:block;color:#fff;padding:0 20px 12px}main{flex:1;padding:24px;max-width:960px}
h1{margin-top:0}.card{background:#fff;border:1px solid #ddd;padding:16px;margin-bottom:16px}
table{width:100%;border-collapse:collapse;background:#fff}td,th{padding:8px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}
input[type=text],input[type=password],input[type=number],input[type=file],textarea,select{width:100%;box-sizing:border-box;padding:8px;margin:4px 0 12px;border:1px solid #8c8f94}
textarea{min-height:320px;font-family:monospace}.btn,button{background:#2271b1;color:#fff;border:0;padding:7px 14px;cursor:pointer;text-decoration:none;display:inline-block;font-size:14px}
button.danger{background:#b32d2e}button.gray{background:#646970}form.inline{display:inline}.ok{background:#d7f0dc;padding:10px;margin-bottom:12px}.err{background:#f6d7d7;padding:10px;margin-bottom:12px}.muted{color:#666}.on{font-weight:600}
</style></head><body>';
    if (current_user()) {
        echo '<nav><b>' . e(setting('site_title', 'Blog')) . '</b>';
        foreach ($nav as $k => $v) echo '<a href="' . e(admin_url($k)) . '">' . e($v) . '</a>';
        echo '<a href="' . e(url()) . '" target="_blank">View site</a><form method="post" action="' . e(admin_url('logout')) . '" style="padding:8px 20px">' . csrf_field() . '<button class="gray">Log out</button></form></nav>';
    }
    echo '<main><h1>' . e($title) . '</h1>';
    if ($f) echo '<div class="' . ($f[1] === 'err' ? 'err' : 'ok') . '">' . e($f[0]) . '</div>';
    $body();
    echo '</main></body></html>';
}

function admin_install(): void
{
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $user = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $ap = strtolower(trim((string)($_POST['admin_path'] ?? '')));
        if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $user)) $err = 'Username must be 3-32 characters (letters, digits, . _ -).';
        elseif (strlen($pass) < 10) $err = 'Password must be at least 10 characters.';
        elseif (!valid_admin_path($ap)) $err = 'Invalid admin URL (3-40 chars, a-z 0-9 -, not reserved).';
        else {
            db()->beginTransaction();
            q('INSERT INTO users(username,password,display_name) VALUES(?,?,?)', [$user, password_hash($pass, PASSWORD_DEFAULT), $user]);
            set_setting('site_title', trim((string)($_POST['site_title'] ?? '')) ?: 'My Blog');
            set_setting('tagline', 'Just another blog');
            set_setting('posts_per_page', '10');
            set_setting('theme', 'classic');
            set_setting('admin_path', $ap);
            set_setting('active_plugins', '[]');
            db()->commit();
            flash('Installed. Please log in.');
            redirect(url($ap . '/login'));
        }
    }
    admin_layout('Install', function () use ($err) {
        if ($err) echo '<div class="err">' . e($err) . '</div>';
        echo '<form method="post" class="card">' . csrf_field() . '
<label>Site title<input type="text" name="site_title" value="My Blog"></label>
<label>Admin URL slug (login at /<i>slug</i>/login)<input type="text" name="admin_path" value="panel-' . e(bin2hex(random_bytes(3))) . '"></label>
<label>Admin username<input type="text" name="username" required></label>
<label>Password (min 10 chars)<input type="password" name="password" required minlength="10"></label>
<button>Install</button></form>';
    });
}

function valid_admin_path(string $p): bool
{
    return (bool)preg_match('/^[a-z0-9][a-z0-9-]{2,39}$/', $p) && !in_array($p, RESERVED_PATHS, true) && !q('SELECT 1 FROM posts WHERE slug=?', [$p])->fetchColumn();
}

function admin_route(string $sub): void
{
    header('Cache-Control: no-store');
    $post = $_SERVER['REQUEST_METHOD'] === 'POST';
    if ($sub === 'login') {
        $err = '';
        if (current_user()) redirect(admin_url());
        if ($post) {
            csrf_check();
            if (login_locked()) $err = 'Too many attempts. Try again in 15 minutes.';
            elseif (attempt_login(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''))) redirect(admin_url());
            else $err = 'Invalid credentials.';
        }
        admin_layout('Log in', function () use ($err) {
            if ($err) echo '<div class="err">' . e($err) . '</div>';
            echo '<form method="post" class="card" style="max-width:360px">' . csrf_field() . '<label>Username<input type="text" name="username" autocomplete="username" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button>Log in</button></form>';
        });
        return;
    }
    if (!current_user()) redirect(admin_url('login'));
    if ($post) csrf_check();
    if ($sub === 'logout' && $post) {
        $_SESSION = [];
        session_destroy();
        redirect(admin_url('login'));
    }
    $parts = explode('/', $sub);
    switch ($parts[0]) {
        case '': admin_dashboard(); break;
        case 'posts': admin_list('post'); break;
        case 'pages': admin_list('page'); break;
        case 'edit': admin_edit((int)($parts[1] ?? 0), (string)($parts[2] ?? 'post')); break;
        case 'delete': if ($post) { q('DELETE FROM posts WHERE id=?', [(int)($_POST['id'] ?? 0)]); flash('Deleted.'); } redirect(admin_url()); 
        case 'themes': admin_themes($post); break;
        case 'plugins': admin_plugins($post); break;
        case 'import-export': admin_import_export($post); break;
        case 'settings': admin_settings($post); break;
        default: not_found();
    }
}

function admin_dashboard(): void
{
    $c = fn($w) => (int)q("SELECT COUNT(*) FROM posts WHERE $w")->fetchColumn();
    admin_layout('Dashboard', function () use ($c) {
        echo '<div class="card">Posts: ' . $c("type='post' AND status='published'") . ' published, ' . $c("type='post' AND status='draft'") . ' drafts · Pages: ' . $c("type='page'") . '<br>Theme: <b>' . e(active_theme()) . '</b> · Active plugins: <b>' . count(active_plugins()) . '</b></div>
<a class="btn" href="' . e(admin_url('edit/0/post')) . '">New post</a> <a class="btn gray" href="' . e(admin_url('edit/0/page')) . '">New page</a>';
    });
}

function admin_list(string $type): void
{
    $rows = q('SELECT id,title,slug,status,created_at FROM posts WHERE type=? ORDER BY created_at DESC', [$type])->fetchAll();
    admin_layout($type === 'page' ? 'Pages' : 'Posts', function () use ($rows, $type) {
        echo '<p><a class="btn" href="' . e(admin_url("edit/0/$type")) . '">Add new</a></p><table><tr><th>Title</th><th>Status</th><th>Date</th><th></th></tr>';
        foreach ($rows as $r) {
            echo '<tr><td><a href="' . e(admin_url('edit/' . $r['id'])) . '">' . e($r['title']) . '</a><div class="muted">/' . e($r['slug']) . '</div></td><td>' . e($r['status']) . '</td><td>' . e($r['created_at']) . '</td><td><form class="inline" method="post" action="' . e(admin_url('delete')) . '" onsubmit="return confirm(\'Delete?\')">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="danger">Delete</button></form></td></tr>';
        }
        echo '</table>';
    });
}

function admin_edit(int $id, string $type): void
{
    $p = $id ? q('SELECT * FROM posts WHERE id=?', [$id])->fetch() : ['type' => $type === 'page' ? 'page' : 'post', 'title' => '', 'slug' => '', 'excerpt' => '', 'content' => '', 'category' => '', 'status' => 'draft'];
    if (!$p) not_found();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = $_POST;
        $d['type'] = $p['type'];
        $id = save_post($d, $id);
        flash('Saved.');
        redirect(admin_url('edit/' . $id));
    }
    admin_layout($id ? 'Edit' : 'New ' . $p['type'], function () use ($p) {
        echo '<form method="post">' . csrf_field() . '<label>Title<input type="text" name="title" value="' . e($p['title']) . '" required></label>
<label>Slug<input type="text" name="slug" value="' . e($p['slug']) . '"></label>
<label>Content (HTML; scripts and unsafe markup are stripped)<textarea name="content">' . e($p['content']) . '</textarea></label>
<label>Excerpt<input type="text" name="excerpt" value="' . e($p['excerpt']) . '"></label>';
        if ($p['type'] === 'post') echo '<label>Category<input type="text" name="category" value="' . e($p['category']) . '"></label>';
        echo '<label>Status<select name="status"><option value="draft"' . ($p['status'] === 'draft' ? ' selected' : '') . '>Draft</option><option value="published"' . ($p['status'] === 'published' ? ' selected' : '') . '>Published</option></select></label><button>Save</button></form>';
    });
}

function admin_themes(bool $post): void
{
    if ($post) {
        $slug = (string)($_POST['slug'] ?? '');
        $act = $_POST['action'] ?? '';
        try {
            if ($act === 'upload') { $slug = install_package('themes', upload_zip()); flash("Theme '$slug' installed."); }
            elseif (!isset(all_themes()[$slug])) throw new RuntimeException('Unknown theme.');
            elseif ($act === 'activate') { set_setting('theme', $slug); flash('Theme activated.'); }
            elseif ($act === 'delete') {
                if ($slug === active_theme()) throw new RuntimeException('Cannot delete the active theme.');
                if (count(all_themes()) < 2) throw new RuntimeException('Cannot delete the last theme.');
                rrmdir(ROOT . "/themes/$slug"); flash('Theme deleted.');
            }
        } catch (RuntimeException $e) { flash($e->getMessage(), 'err'); }
        redirect(admin_url('themes'));
    }
    admin_layout('Themes', function () {
        echo '<table><tr><th>Theme</th><th></th></tr>';
        foreach (all_themes() as $t) {
            $on = $t['slug'] === active_theme();
            echo '<tr><td><b>' . e($t['name']) . '</b> ' . e($t['version']) . ($on ? ' <span class="on">(active)</span>' : '') . '<div class="muted">' . e($t['description']) . '</div></td><td>';
            if (!$on) echo pkg_form($t['slug'], 'activate', 'Activate') . ' ' . pkg_form($t['slug'], 'delete', 'Delete', true);
            echo '</td></tr>';
        }
        echo '</table>' . upload_form('Upload theme (.zip)');
    });
}

function admin_plugins(bool $post): void
{
    if ($post) {
        $slug = (string)($_POST['slug'] ?? '');
        $act = $_POST['action'] ?? '';
        try {
            if ($act === 'upload') { $slug = install_package('plugins', upload_zip()); flash("Plugin '$slug' installed (inactive)."); }
            elseif (!isset(all_plugins()[$slug])) throw new RuntimeException('Unknown plugin.');
            elseif ($act === 'activate') { set_plugin_active($slug, true); flash('Plugin activated.'); }
            elseif ($act === 'deactivate') { set_plugin_active($slug, false); flash('Plugin deactivated.'); }
            elseif ($act === 'delete') {
                set_plugin_active($slug, false);
                rrmdir(ROOT . "/plugins/$slug"); flash('Plugin deleted.');
            }
        } catch (RuntimeException $e) { flash($e->getMessage(), 'err'); }
        redirect(admin_url('plugins'));
    }
    admin_layout('Plugins', function () {
        echo '<table><tr><th>Plugin</th><th></th></tr>';
        foreach (all_plugins() as $p) {
            $on = in_array($p['slug'], active_plugins(), true);
            echo '<tr><td><b>' . e($p['name']) . '</b> ' . e($p['version']) . ' <span class="muted">' . e($p['author']) . '</span>' . ($on ? ' <span class="on">(active)</span>' : '') . '<div class="muted">' . e($p['description']) . '</div></td><td>'
                . pkg_form($p['slug'], $on ? 'deactivate' : 'activate', $on ? 'Deactivate' : 'Activate') . ' ' . (!$on ? pkg_form($p['slug'], 'delete', 'Delete', true) : '') . '</td></tr>';
        }
        echo '</table><p class="muted">Only upload plugins from sources you trust: plugins run PHP code on your server.</p>' . upload_form('Upload plugin (.zip)');
    });
}

function pkg_form(string $slug, string $action, string $label, bool $danger = false): string
{
    return '<form class="inline" method="post">' . csrf_field() . '<input type="hidden" name="slug" value="' . e($slug) . '"><input type="hidden" name="action" value="' . e($action) . '"><button' . ($danger ? ' class="danger" onclick="return confirm(\'Delete?\')"' : '') . '>' . e($label) . '</button></form>';
}

function upload_form(string $label): string
{
    return '<form class="card" method="post" enctype="multipart/form-data">' . csrf_field() . '<input type="hidden" name="action" value="upload"><label>' . e($label) . '<input type="file" name="file" accept=".zip" required></label><button>Upload</button></form>';
}

function upload_zip(): string
{
    $f = $_FILES['file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) throw new RuntimeException('Upload failed.');
    if ($f['size'] > 20 * 1024 * 1024) throw new RuntimeException('File too large.');
    return $f['tmp_name'];
}

function admin_import_export(bool $post): void
{
    if ($post && ($_POST['action'] ?? '') === 'export') {
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="blog-export-' . date('Ymd-His') . '.xml"');
        echo export_xml();
        exit;
    }
    if ($post && ($_POST['action'] ?? '') === 'import') {
        try {
            $f = $_FILES['file'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) throw new RuntimeException('Upload failed.');
            if ($f['size'] > 20 * 1024 * 1024) throw new RuntimeException('File too large.');
            [$c, $u] = import_xml((string)file_get_contents($f['tmp_name']));
            flash("Imported: $c created, $u updated.");
        } catch (Throwable $e) { flash($e instanceof RuntimeException ? $e->getMessage() : 'Import failed.', 'err'); }
        redirect(admin_url('import-export'));
    }
    admin_layout('Import / Export', function () {
        echo '<form class="card" method="post">' . csrf_field() . '<input type="hidden" name="action" value="export"><p>Download all posts, pages and public settings as an XML file.</p><button>Export XML</button></form>
<form class="card" method="post" enctype="multipart/form-data">' . csrf_field() . '<input type="hidden" name="action" value="import"><label>Import XML (existing slugs are updated)<input type="file" name="file" accept=".xml,application/xml,text/xml" required></label><button>Import</button></form>';
    });
}

function admin_settings(bool $post): void
{
    if ($post) {
        $act = $_POST['action'] ?? '';
        if ($act === 'general') {
            set_setting('site_title', mb_substr(trim((string)$_POST['site_title']), 0, 120) ?: 'My Blog');
            set_setting('tagline', mb_substr(trim((string)$_POST['tagline']), 0, 200));
            set_setting('posts_per_page', (string)max(1, min(50, (int)$_POST['posts_per_page'])));
            $ap = strtolower(trim((string)$_POST['admin_path']));
            if ($ap !== setting('admin_path')) {
                if (!valid_admin_path($ap)) { flash('Invalid admin URL (3-40 chars, a-z 0-9 -, not reserved or used by a post).', 'err'); redirect(admin_url('settings')); }
                set_setting('admin_path', $ap);
                flash('Settings saved. Admin URL changed.');
                redirect(url($ap . '/settings'));
            }
            flash('Settings saved.');
        } elseif ($act === 'password') {
            $row = q('SELECT password FROM users WHERE id=?', [current_user()['id']])->fetch();
            $new = (string)$_POST['new_password'];
            if (!password_verify((string)$_POST['current_password'], $row['password'])) flash('Current password is wrong.', 'err');
            elseif (strlen($new) < 10) flash('New password must be at least 10 characters.', 'err');
            else {
                q('UPDATE users SET password=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), current_user()['id']]);
                session_regenerate_id(true);
                flash('Password changed.');
            }
        }
        redirect(admin_url('settings'));
    }
    admin_layout('Settings', function () {
        echo '<form class="card" method="post">' . csrf_field() . '<input type="hidden" name="action" value="general">
<label>Site title<input type="text" name="site_title" value="' . e(setting('site_title')) . '"></label>
<label>Tagline<input type="text" name="tagline" value="' . e(setting('tagline')) . '"></label>
<label>Posts per page<input type="number" name="posts_per_page" min="1" max="50" value="' . e(setting('posts_per_page', '10')) . '"></label>
<label>Admin panel URL slug<input type="text" name="admin_path" value="' . e(setting('admin_path')) . '"></label>
<p class="muted">Changing this moves the login to /slug/login. Bookmark the new address.</p><button>Save</button></form>
<form class="card" method="post">' . csrf_field() . '<input type="hidden" name="action" value="password"><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><label>New password (min 10 chars)<input type="password" name="new_password" autocomplete="new-password" required minlength="10"></label><button>Change password</button></form>';
    });
}
