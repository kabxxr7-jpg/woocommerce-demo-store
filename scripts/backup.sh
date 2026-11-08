#!/usr/bin/env bash
# Backup: database dump + uploads, into ./backups/<date>/ (git-ignored), then a restore test of the dump into a
# scratch database so we know the backup is usable before anything risky is done.
set -euo pipefail
cd "$(dirname "$0")/.."
set -a; source .env; set +a
d="backups/$(date +%Y%m%d-%H%M%S)"; mkdir -p "$d"
docker compose exec -T db mariadb-dump -uroot -p"$DB_ROOT_PASSWORD" --single-transaction --routines store | gzip > "$d/db.sql.gz"
docker compose exec -T wordpress tar -C /var/www/html/wp-content -czf - uploads > "$d/uploads.tar.gz"
echo "--> restore test into a scratch database"
docker compose exec -T db mariadb -uroot -p"$DB_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS restore_test; CREATE DATABASE restore_test"
gunzip -c "$d/db.sql.gz" | docker compose exec -T db mariadb -uroot -p"$DB_ROOT_PASSWORD" restore_test
live=$(docker compose exec -T db mariadb -uroot -p"$DB_ROOT_PASSWORD" -N -e "SELECT COUNT(*) FROM store.pp_posts")
copy=$(docker compose exec -T db mariadb -uroot -p"$DB_ROOT_PASSWORD" -N -e "SELECT COUNT(*) FROM restore_test.pp_posts")
docker compose exec -T db mariadb -uroot -p"$DB_ROOT_PASSWORD" -e "DROP DATABASE restore_test"
[[ "$live" == "$copy" ]] || { echo "restore test FAILED: $live posts live, $copy in the copy" >&2; exit 1; }
echo "backup ok: $d ($(du -sh "$d" | cut -f1)), restore test: $copy posts in both"
