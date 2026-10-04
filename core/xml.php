<?php
// XML import / export.

const EXPORT_SETTINGS = ['site_title', 'tagline', 'posts_per_page'];

function export_xml(): string
{
    $d = new DOMDocument('1.0', 'UTF-8');
    $d->formatOutput = true;
    $root = $d->appendChild($d->createElement('blog'));
    $root->setAttribute('version', '1.0');
    $root->setAttribute('exported', date('c'));
    $s = $root->appendChild($d->createElement('settings'));
    foreach (EXPORT_SETTINGS as $k) {
        $el = $s->appendChild($d->createElement('setting'));
        $el->setAttribute('name', $k);
        $el->appendChild($d->createCDATASection(xml_clean((string)setting($k))));
    }
    $ps = $root->appendChild($d->createElement('posts'));
    foreach (q('SELECT * FROM posts ORDER BY id')->fetchAll() as $p) {
        $el = $ps->appendChild($d->createElement('post'));
        foreach (['type', 'title', 'slug', 'excerpt', 'content', 'category', 'status', 'created_at', 'updated_at'] as $f) {
            $c = $el->appendChild($d->createElement($f));
            $c->appendChild($d->createCDATASection(xml_clean((string)$p[$f])));
        }
    }
    return $d->saveXML();
}

function xml_clean(string $s): string
{
    $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s) ?? '';
    return str_replace(']]>', ']]]]><![CDATA[>', $s);
}

/** Returns [created, updated]. Throws on invalid input. */
function import_xml(string $xml): array
{
    if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) throw new RuntimeException('DOCTYPE/ENTITY declarations are not allowed.');
    $prev = libxml_use_internal_errors(true);
    $d = new DOMDocument();
    $ok = $d->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok || !$d->documentElement || $d->documentElement->nodeName !== 'blog') throw new RuntimeException('Not a valid blog export file.');
    $x = new DOMXPath($d);
    $created = $updated = 0;
    db()->beginTransaction();
    try {
        foreach ($x->query('/blog/settings/setting') as $n) {
            $k = $n->getAttribute('name');
            if (in_array($k, EXPORT_SETTINGS, true)) set_setting($k, $k === 'posts_per_page' ? (string)max(1, min(50, (int)$n->textContent)) : mb_substr(trim($n->textContent), 0, 255));
        }
        foreach ($x->query('/blog/posts/post') as $n) {
            $f = [];
            foreach (['type', 'title', 'slug', 'excerpt', 'content', 'category', 'status', 'created_at'] as $k) {
                $c = $x->query($k, $n)->item(0);
                $f[$k] = $c ? $c->textContent : '';
            }
            $existing = $f['slug'] !== '' ? q('SELECT id FROM posts WHERE slug=?', [slugify($f['slug'])])->fetchColumn() : false;
            save_post($f, $existing ? (int)$existing : 0);
            $existing ? $updated++ : $created++;
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    return [$created, $updated];
}
