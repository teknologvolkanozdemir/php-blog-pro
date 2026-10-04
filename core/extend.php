<?php
// Hooks, plugins and themes.

$GLOBALS['_hooks'] = [];

function add_action(string $name, callable $fn, int $prio = 10): void { $GLOBALS['_hooks'][$name][$prio][] = $fn; }
function add_filter(string $name, callable $fn, int $prio = 10): void { add_action($name, $fn, $prio); }

function do_action(string $name, ...$args): void
{
    $h = $GLOBALS['_hooks'][$name] ?? [];
    ksort($h);
    foreach ($h as $fns) foreach ($fns as $fn) $fn(...$args);
}

function apply_filters(string $name, $value, ...$args)
{
    $h = $GLOBALS['_hooks'][$name] ?? [];
    ksort($h);
    foreach ($h as $fns) foreach ($fns as $fn) $value = $fn($value, ...$args);
    return $value;
}

function valid_slug(string $s): bool { return (bool)preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $s); }

function read_header(string $file, string $key): string
{
    $head = (string)@file_get_contents($file, false, null, 0, 4096);
    return preg_match('/^[ \t\/*#@]*' . preg_quote($key, '/') . ':\s*(.+)$/mi', $head, $m) ? trim($m[1]) : '';
}

function all_plugins(): array
{
    $out = [];
    foreach (glob(ROOT . '/plugins/*/plugin.php') ?: [] as $f) {
        $slug = basename(dirname($f));
        if (!valid_slug($slug)) continue;
        $out[$slug] = ['slug' => $slug, 'name' => read_header($f, 'Plugin Name') ?: $slug, 'description' => read_header($f, 'Description'), 'version' => read_header($f, 'Version'), 'author' => read_header($f, 'Author')];
    }
    return $out;
}

function active_plugins(): array
{
    $a = json_decode((string)setting('active_plugins', '[]'), true);
    return is_array($a) ? array_values(array_filter($a, 'is_string')) : [];
}

function load_plugins(): void
{
    foreach (active_plugins() as $slug) {
        if (valid_slug($slug) && is_file($f = ROOT . "/plugins/$slug/plugin.php")) require_once $f;
    }
}

function set_plugin_active(string $slug, bool $on): void
{
    $a = array_diff(active_plugins(), [$slug]);
    if ($on && isset(all_plugins()[$slug])) $a[] = $slug;
    set_setting('active_plugins', json_encode(array_values($a)));
}

function all_themes(): array
{
    $out = [];
    foreach (glob(ROOT . '/themes/*/theme.php') ?: [] as $f) {
        $slug = basename(dirname($f));
        if (!valid_slug($slug)) continue;
        $out[$slug] = ['slug' => $slug, 'name' => read_header($f, 'Theme Name') ?: $slug, 'description' => read_header($f, 'Description'), 'version' => read_header($f, 'Version'), 'author' => read_header($f, 'Author')];
    }
    return $out;
}

function active_theme(): string
{
    $t = (string)setting('theme', 'classic');
    return isset(all_themes()[$t]) ? $t : (array_key_first(all_themes()) ?? 'classic');
}

function theme_url(string $file = ''): string { return url('themes/' . active_theme() . '/' . $file); }

function render(string $tpl, array $vars = []): void
{
    $dir = ROOT . '/themes/' . active_theme();
    $file = is_file("$dir/$tpl.php") ? "$dir/$tpl.php" : ROOT . "/themes/classic/$tpl.php";
    extract($vars, EXTR_SKIP);
    include $file;
}

function rrmdir(string $d): void
{
    if (is_link($d) || is_file($d)) { @unlink($d); return; }
    foreach (scandir($d) ?: [] as $f) if ($f !== '.' && $f !== '..') rrmdir("$d/$f");
    @rmdir($d);
}

/** Install a theme/plugin from an uploaded zip. $kind: plugins|themes. Returns slug or throws. */
function install_package(string $kind, string $zipPath): string
{
    $mainFile = $kind === 'plugins' ? 'plugin.php' : 'theme.php';
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) throw new RuntimeException('Invalid zip file.');
    if ($zip->numFiles > 2000) throw new RuntimeException('Archive too large.');
    $slug = null; $size = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = str_replace('\\', '/', $zip->getNameIndex($i));
        if ($n === '' || $n[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $n) || strpos($n, "\0") !== false || preg_match('/^[A-Za-z]:/', $n)) throw new RuntimeException('Unsafe path in archive.');
        $top = explode('/', $n)[0];
        if ($slug === null) $slug = $top; elseif ($slug !== $top) throw new RuntimeException('Archive must contain a single top-level folder.');
        $size += $zip->statIndex($i)['size'];
        $attr = 0; $zip->getExternalAttributesIndex($i, $opsys, $attr);
        if ($opsys === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) throw new RuntimeException('Symlinks not allowed.');
    }
    if ($size > 20 * 1024 * 1024) throw new RuntimeException('Archive too large.');
    if ($slug === null || !valid_slug($slug)) throw new RuntimeException('Invalid package folder name.');
    if ($zip->locateName("$slug/$mainFile") === false) throw new RuntimeException("Missing $slug/$mainFile.");
    $dest = ROOT . "/$kind/$slug";
    if (file_exists($dest)) throw new RuntimeException('Already installed.');
    if (!$zip->extractTo(ROOT . "/$kind")) throw new RuntimeException('Extraction failed.');
    $zip->close();
    return $slug;
}
