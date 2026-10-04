<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?><?= $title !== setting('site_title') ? ' – ' . e(setting('site_title')) : '' ?></title>
<link rel="stylesheet" href="<?= e(theme_url('style.css')) ?>">
<link rel="alternate" type="application/rss+xml" href="<?= e(url('feed.xml')) ?>">
<?php do_action('head'); ?>
</head><body><div class="wrap">
<header class="site"><h1><a href="<?= e(url()) ?>"><?= e(setting('site_title')) ?></a></h1><p><?= e(setting('tagline')) ?></p>
<form action="<?= e(url('search')) ?>"><input name="q" placeholder="Search…"></form></header>
