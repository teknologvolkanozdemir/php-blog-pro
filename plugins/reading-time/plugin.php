<?php
/*
Plugin Name: Reading Time
Description: Shows an estimated reading time above every post.
Version: 1.0
Author: php-blog-pro
*/
add_filter('the_content', function (string $html, array $post): string {
    if ($post['type'] !== 'post') return $html;
    $min = max(1, (int)ceil(str_word_count(strip_tags($html)) / 200));
    return '<p class="meta">⏱ ' . $min . ' min read</p>' . $html;
});
