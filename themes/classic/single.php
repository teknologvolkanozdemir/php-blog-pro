<?php include __DIR__ . '/header.php'; ?>
<article><h2><?= e($post['title']) ?></h2>
<?php if ($post['type'] === 'post'): ?><div class="meta"><?= e(date('M j, Y', strtotime($post['created_at']))) ?> · <?= e($post['author']) ?></div><?php endif; ?>
<?= $post['content'] /* sanitized on save */ ?></article>
<?php include __DIR__ . '/footer.php'; ?>
