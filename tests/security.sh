#!/usr/bin/env bash
# Security hardening checks for the running store. Each line is one item of docs/SECURITY.md.
#   tests/security.sh            -> PASS/FAIL table, exit code = number of failures
# Run it in the "before" state to see the items fail, and in the "after" state to see them pass.
# Requests that should look like they come through the public tunnel are sent to 127.0.0.1:8081 with the
# public Host header and X-Forwarded-Proto: https, which is exactly what cloudflared sends.
set -uo pipefail
cd "$(dirname "$0")/.."
BASE=${BASE:-http://127.0.0.1:8081}
PUB=${STORE_PUBLIC_HOST:-shop.kabiirlabs.com}
fails=0; n=0
row() { n=$((n+1)); printf '%-4s %-62s %s\n' "$1" "$2" "${3:-}"; [[ "$1" == FAIL ]] && fails=$((fails+1)); }
check() { local name="$1"; shift; if "$@"; then row PASS "$name"; else row FAIL "$name" "($last)"; fi; }
code() { last=$(curl -s -o /dev/null -w '%{http_code}' "$@"); echo "$last"; }
pubcode() { code -H "Host: $PUB" -H "X-Forwarded-Proto: https" "$@"; }
hdrs=$(curl -s -D - -o /dev/null "$BASE/")
pubhdrs=$(curl -s -D - -o /dev/null -H "Host: $PUB" -H "X-Forwarded-Proto: https" "$BASE/")
home=$(curl -s "$BASE/")
has_hdr() { last="missing"; grep -qi "^$1:" <<<"$2"; }
not_in() { last="found"; ! grep -qi "$1" <<<"$2"; }
in_range() { last=$1; [[ "$1" =~ ^($2)$ ]]; }

printf '%-4s %-62s\n' "" "Check (base $BASE, public host $PUB)"
check "readme.html not served (version disclosure)"               in_range "$(code "$BASE/readme.html")" "403|404"
check "license.txt not served"                                    in_range "$(code "$BASE/license.txt")" "403|404"
check "wp-config.php refused"                                     in_range "$(code "$BASE/wp-config.php")" "403|404"
check "xmlrpc.php refused (brute force / pingback abuse)"         in_range "$(code -X POST -d '<methodCall/>' "$BASE/xmlrpc.php")" "403|404"
check ".htaccess and dotfiles refused"                            in_range "$(code "$BASE/.htaccess")" "403|404"
check "debug.log not served"                                      in_range "$(code "$BASE/wp-content/debug.log")" "403|404"
check "no directory listing in uploads"                           eval '! curl -s "$BASE/wp-content/uploads/" | grep -qi "Index of"; last=listing'
check "?author=1 does not reveal a user name"                     eval 'loc=$(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" "$BASE/?author=1"); last=$loc; ! grep -q "/author/" <<<"$loc"'
check "REST API does not list users"                              in_range "$(code "$BASE/wp-json/wp/v2/users")" "401|403|404"
check "wp-login.php closed on the public address"                 in_range "$(pubcode "$BASE/wp-login.php")" "403|404"
check "wp-admin closed on the public address"                     in_range "$(pubcode "$BASE/wp-admin/")" "403|404"
check "admin-ajax.php still works on the public address"          in_range "$(pubcode -X POST -d action=pp_none "$BASE/wp-admin/admin-ajax.php")" "400|200"
check "unknown Host header refused (host header injection)"       in_range "$(code -H 'Host: evil.example' "$BASE/")" "421|400|403"
check "header: Content-Security-Policy"                           has_hdr Content-Security-Policy "$hdrs"
check "header: X-Content-Type-Options"                            has_hdr X-Content-Type-Options "$hdrs"
check "header: X-Frame-Options"                                   has_hdr X-Frame-Options "$hdrs"
check "header: Referrer-Policy"                                   has_hdr Referrer-Policy "$hdrs"
check "header: Permissions-Policy"                                has_hdr Permissions-Policy "$hdrs"
check "header: Strict-Transport-Security on the HTTPS address"    has_hdr Strict-Transport-Security "$pubhdrs"
check "no X-Powered-By (PHP version)"                             not_in '^X-Powered-By' "$hdrs"
check "Server header without version"                             not_in '^Server: Apache/' "$hdrs"
check "no generator meta tag (WordPress/WooCommerce version)"     not_in 'name="generator"' "$home"

# Checks that need the container
w() { scripts/wp.sh "$@" 2>/dev/null; }
check "PHP in uploads cannot run"                                 eval 'w bash -c "echo \"<?php echo 1;\" > wp-content/uploads/pp-sec-test.php"; c=$(code "$BASE/wp-content/uploads/pp-sec-test.php"); w rm -f wp-content/uploads/pp-sec-test.php; last=$c; [[ $c == 403 || $c == 404 ]]'
check "file editor off (DISALLOW_FILE_EDIT)"                      eval 'last=$(w wp eval "echo (int) DISALLOW_FILE_EDIT;"); [[ $last == 1 ]]'
check "no administrator called admin"                             eval 'last=$(w wp user list --role=administrator --field=user_login | tr "\n" " "); ! grep -qw admin <<<"$last"'
check "application passwords off"                                 eval 'last=$(w wp eval "echo (int) wp_is_application_passwords_available();"); [[ $last == 0 ]]'
check "login attempts limited (plugin active)"                    eval 'last=$(w wp plugin is-active limit-login-attempts-reloaded && echo active || echo inactive); [[ $last == active ]]'
check "outgoing email suppressed (demo store)"                    eval 'last=$(w wp eval "var_export( apply_filters( \"pre_wp_mail\", null, array( \"to\" => \"x@example.com\", \"subject\" => \"t\" ) ) );"); [[ $last == true ]]'
check "database port not published on the host"                   eval 'last=$(docker compose port db 3306 2>/dev/null || true); [[ -z $last || $last == ":0" ]]'
check "only 127.0.0.1 can reach the web container"                eval 'last=$(docker compose port wordpress 80); [[ $last == 127.0.0.1:* ]]'
check "WordPress core files match wordpress.org checksums"        eval 'last=$(w wp core verify-checksums 2>&1 | tail -1); grep -q "Success" <<<"$last"'

echo
echo "$((n-fails)) of $n checks passed"
exit $fails
