<?php include __DIR__ . '/header.php'; ?>
<?php if ($heading): ?><h2><?= e($heading) ?></h2><?php endif; ?>
<?php foreach ($posts as $p): ?>
<article><h2><a href="<?= e(url($p['slug'])) ?>"><?= e($p['title']) ?></a></h2>
<div class="meta"><?= e(date('M j, Y', strtotime($p['created_at']))) ?> · <?= e($p['author']) ?><?php if ($p['category']): ?> · <a href="<?= e(url('category/' . $p['category'])) ?>"><?= e($p['category']) ?></a><?php endif; ?></div>
<p><?= e($p['excerpt'] ?: mb_substr(strip_tags($p['content']), 0, 250) . '…') ?></p></article>
<?php endforeach; ?>
<?php if (!$posts): ?><p>No posts found.</p><?php endif; ?>
<?php if ($pages > 1): ?><div class="pager">
<?php $sep = strpos($base, '?') === false ? '?' : ''; if ($page > 1): ?><a href="<?= e($base . $sep . 'page=' . ($page - 1)) ?>">&larr; Newer</a><?php endif; ?>
<?php if ($page < $pages): ?><a href="<?= e($base . $sep . 'page=' . ($page + 1)) ?>">Older &rarr;</a><?php endif; ?></div><?php endif; ?>
<?php include __DIR__ . '/footer.php'; ?>
