<?php
// Posts & pages.

function unique_slug(string $slug, int $ignoreId = 0): string
{
    $base = $slug; $i = 2;
    while (q('SELECT 1 FROM posts WHERE slug=? AND id<>?', [$slug, $ignoreId])->fetchColumn()) $slug = $base . '-' . $i++;
    return $slug;
}

function save_post(array $d, int $id = 0): int
{
    $type = ($d['type'] ?? 'post') === 'page' ? 'page' : 'post';
    $status = ($d['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $title = trim((string)($d['title'] ?? ''));
    if ($title === '') $title = 'Untitled';
    $slug = unique_slug(slugify((string)(($d['slug'] ?? '') !== '' ? $d['slug'] : $title)), $id);
    $content = apply_filters('pre_save_content', sanitize_html((string)($d['content'] ?? '')));
    $excerpt = mb_substr(trim(strip_tags((string)($d['excerpt'] ?? ''))), 0, 500);
    $cat = mb_substr(trim(strip_tags((string)($d['category'] ?? ''))), 0, 80);
    $now = date('Y-m-d H:i:s');
    $created = preg_match('/^\d{4}-\d\d-\d\d( \d\d:\d\d:\d\d)?$/', (string)($d['created_at'] ?? '')) ? $d['created_at'] : $now;
    if ($id) {
        q('UPDATE posts SET type=?,title=?,slug=?,excerpt=?,content=?,category=?,status=?,updated_at=? WHERE id=?', [$type, $title, $slug, $excerpt, $content, $cat, $status, $now, $id]);
        do_action('post_saved', $id);
        return $id;
    }
    q('INSERT INTO posts(type,title,slug,excerpt,content,category,status,author_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [$type, $title, $slug, $excerpt, $content, $cat, $status, current_user()['id'] ?? null, $created, $now]);
    $id = (int)db()->lastInsertId();
    do_action('post_saved', $id);
    return $id;
}

function get_post_by_slug(string $slug, bool $publishedOnly = true): ?array
{
    $r = q('SELECT p.*, COALESCE(NULLIF(u.display_name,\'\'),u.username) AS author FROM posts p LEFT JOIN users u ON u.id=p.author_id WHERE p.slug=?' . ($publishedOnly ? " AND p.status='published'" : ''), [$slug])->fetch();
    return $r ?: null;
}

function list_posts(int $page, string $where = '', array $args = []): array
{
    $per = max(1, (int)setting('posts_per_page', '10'));
    $sql = "FROM posts p LEFT JOIN users u ON u.id=p.author_id WHERE p.type='post' AND p.status='published'" . $where;
    $total = (int)q("SELECT COUNT(*) $sql", $args)->fetchColumn();
    $rows = q("SELECT p.*, COALESCE(NULLIF(u.display_name,''),u.username) AS author $sql ORDER BY p.created_at DESC, p.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $args)->fetchAll();
    return [$rows, (int)ceil($total / $per)];
}
