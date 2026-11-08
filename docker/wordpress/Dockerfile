# WordPress (Apache + PHP) with what this store needs on top of the official image:
#  - WP-CLI, for scripted setup, the before/after states and checks
#  - the phpredis extension, for the Redis object cache in the "after" state
#  - Apache modules for compression, cache headers and security headers (.htaccess)
# Pinned to an exact digest in docker-compose.yml after the first pull (see docs/VERSIONS.md).
ARG WP_IMAGE=wordpress@sha256:6888208af0e3cb93af427c13a434273b07ba353ce9e2d5a29abe3031bb28ea3a
FROM ${WP_IMAGE}

RUN set -eux; \
    pecl install redis; docker-php-ext-enable redis; rm -rf /tmp/pear; \
    a2enmod rewrite expires headers deflate remoteip; \
    curl -fsSL -o /usr/local/bin/wp https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar; \
    chmod +x /usr/local/bin/wp; \
    apt-get update; apt-get install -y --no-install-recommends mariadb-client less; rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/zz-store.ini
COPY apache-store.conf /etc/apache2/conf-enabled/zz-store.conf
