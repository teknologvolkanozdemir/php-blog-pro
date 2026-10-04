# php-blog-pro

A lightweight, WordPress-style blog CMS in plain PHP (8.1+, PDO SQLite, no Composer needed).

## Features
- Posts, pages, categories, search, RSS (`/feed.xml`), pagination
- **Themes**: add (zip upload), activate, delete — `themes/<slug>/theme.php` (+ `header.php`, `footer.php`, `index.php`, `single.php`, `notfound.php`)
- **Plugins**: add (zip upload), activate, deactivate, delete — `plugins/<slug>/plugin.php`; hooks via `add_action` / `add_filter` (`init`, `head`, `footer`, `post_saved`, `the_content`, `pre_save_content`)
- **XML import / export** of posts, pages and public settings
- **Custom admin URL** (Settings → Admin panel URL slug); the default `/admin` does not exist unless you choose it
- **Security**: password_hash, CSRF tokens on every POST, login throttling (5 failures / 15 min / IP), session hardening (HttpOnly, SameSite=Strict, regeneration, idle timeout), CSP & security headers, prepared statements, output escaping, HTML sanitizer for content, zip-slip/symlink checks on uploads, XXE-safe XML import (DOCTYPE rejected), `data/` and `core/` blocked from the web

## Run
```
php -S localhost:8000 index.php
```
Open the site and complete the installer (site title, admin URL slug, admin account). On Apache, `.htaccess` provides rewriting (needs `mod_rewrite`); for nginx route everything non-static to `index.php` and deny `/core` and `/data`. Serve over HTTPS in production. Only install plugins/themes you trust — they execute PHP.
