# Security hardening checklist

What was done to the store, why, where it lives, and the automated check that proves it (`tests/security.sh`).
The checks are run twice: in the "before" state (default WordPress, most items fail) and in the "after" state
(all pass). Results: [`reports/security-before.txt`](../reports/security-before.txt) and
[`reports/security-after.txt`](../reports/security-after.txt).

| # | Item | Why | Where | Check |
|---|---|---|---|---|
| 1 | `readme.html`, `license.txt`, `wp-config.php`, `xmlrpc.php`, dotfiles, logs, `.sql`/`.bak` files refused | version disclosure, config leaks, XML-RPC brute force and pingback abuse | `.htaccess` H1 | 403/404 for each |
| 2 | No PHP runs from `wp-content/uploads` | an uploaded `image.php` cannot execute | `.htaccess` H2 | a test PHP file in uploads returns 403 |
| 3 | No directory listing | file names in uploads stay private | Apache `Options -Indexes` | `/wp-content/uploads/` shows no index |
| 4 | No user-name discovery (`?author=N`, `/wp-json/wp/v2/users`, oEmbed author, users sitemap) | user names are half of a login | `pp-security.php` S2 + `.htaccess` H3 | no `/author/` redirect, users endpoint 404 |
| 5 | Admin and login closed on the public address | nothing to guess passwords against; admin is used on the local address only | `pp-security.php` S1 | public `wp-login.php` and `wp-admin` 404, `admin-ajax.php` still works |
| 6 | Unknown `Host` header refused | host header injection (password-reset links, cache poisoning) | `config/wp-config-extra.php` | `Host: evil.example` gets 421 |
| 7 | Security headers: CSP, HSTS (on HTTPS), `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, COOP | clickjacking, MIME sniffing, script injection impact, referrer leaks | `.htaccess` H4 (on cached pages and static files too) | each header present |
| 8 | No `X-Powered-By`, no Apache version, no generator meta | do not advertise versions | `php.ini expose_php=Off`, Apache `ServerTokens Prod`, `pp-security.php` S4 | header and meta absent |
| 9 | File editor off (`DISALLOW_FILE_EDIT`) | a stolen admin session cannot write PHP from the dashboard | `wp-config-extra.php` | constant is true |
| 10 | Admin user is not `admin`; 28-character random password; salts from `.env` | the two most guessed log-ins | `scripts/setup.sh` | no administrator named `admin` |
| 11 | Login attempts limited | brute force | Limit Login Attempts Reloaded (wordpress.org) | plugin active |
| 12 | XML-RPC and application passwords off | two more ways to try passwords | `pp-security.php` S3 | `xmlrpc.php` 403; application passwords unavailable |
| 13 | Same error for wrong user and wrong password | user-name discovery through the login form | `pp-security.php` S5 | (manual) |
| 14 | Database not published on the host; web server only on `127.0.0.1` | nothing reachable from the network except through the tunnel | `docker-compose.yml` | `docker compose port` checks |
| 15 | Core files match wordpress.org checksums | detects modified core files | `wp core verify-checksums` | Success |
| 16 | Database user limited to its own database; table prefix `pp_` | limits what an SQL injection can reach; defeats scripts that assume `wp_` | `docker-compose.yml` | (by construction) |
| 17 | No outgoing email | visitors can type any address at checkout; a demo must never mail it | `pp-00-store-core.php` | `pre_wp_mail` short-circuits |
| 18 | Visitor test orders deleted after 24 hours | privacy on a public demo | `pp-00-store-core.php` + WooCommerce retention settings | (scheduled) |
| 19 | Backups with a restore test | a backup that was never restored is a hope, not a backup | `scripts/backup.sh` | restore into a scratch database, row counts compared |
| 20 | Minor core updates automatic; plugin versions recorded | known holes get closed | `WP_AUTO_UPDATE_CORE=minor`, `docs/VERSIONS.md` | (by construction) |

## What is not covered

- No web application firewall or malware scanner product is installed. Cloudflare sits in front of the public
  address (its default protections apply), but no Cloudflare rules were written for this demo.
- No two-factor login: the admin area is not reachable on the public address at all, which is stronger for this
  demo but is not an option for most real stores. On a client store, two-factor (for example the free
  "Two Factor" plugin) is part of the list.
- No penetration test. The checks above prove that each measure is in place, not that the site cannot be broken.
- `script-src` allows `'unsafe-inline'` because WordPress and WooCommerce print inline scripts. A nonce-based CSP
  needs changes in both and was not attempted.
