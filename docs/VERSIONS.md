| Component | Version |
|---|---|
| Date | 2026-11-08 |
| WordPress image | woo-store-wordpress:local built from `wordpress:apache` |
| PHP | 8.3.36 |
| WordPress | 7.1.4 |
| MariaDB | 11.4.13-MariaDB, client 15.2 |
| Redis | 7.4.12 |
| plugin: contact-form-7 | 6.2.4.1 (active) |
| plugin: limit-login-attempts-reloaded | 3.4.2 (active) |
| plugin: redis-cache | 3.0.0 (active) |
| plugin: woocommerce | 11.2.2 (active) |
| plugin: wp-super-cache | 3.1.5 (active) |
| plugin: pp-00-store-core | 1.0.0 (must-use) |
| plugin: pp-tracking | 1.0.0 (must-use) |
| plugin: pp-security | 1.0.0 (must-use) |
| plugin: pp-performance | 1.0.0 (must-use) |
| plugin: advanced-cache.php |  (dropin) |
| plugin: object-cache.php |  (dropin) |
| theme: twentytwentyfive (parent) | 1.5 |
| WP-CLI | 2.12.0 |

Base image pinned in `docker/wordpress/Dockerfile` to `wordpress@sha256:6888208af0e3cb93af427c13a434273b07ba353ce9e2d5a29abe3031bb28ea3a` (WordPress 7.1.4, PHP 8.3). Before-state plugins (MetaSlider, Font Awesome) are installed at their latest wordpress.org version when `scripts/state-before.sh` runs.
