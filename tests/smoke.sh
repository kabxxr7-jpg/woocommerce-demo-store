#!/usr/bin/env bash
# Smoke test: pages answer, the demo label is shown, and a real test order goes through WooCommerce's Store API.
set -uo pipefail
BASE=${BASE:-http://127.0.0.1:8081}
fails=0
ok() { printf 'PASS %s\n' "$1"; }
ko() { printf 'FAIL %s %s\n' "$1" "${2:-}"; fails=$((fails+1)); }
for p in / /shop/ /product-category/bowls/ /product/raspberry-garden-teapot/ /cart/ /about/ /contact/ /privacy-policy/; do
  c=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$p"); [[ $c == 200 ]] && ok "GET $p -> 200" || ko "GET $p" "-> $c"
done
home=$(curl -s "$BASE/"); grep -q 'pp-demo-bar' <<<"$home" && ok "demo label on the home page" || ko "demo label on the home page"
shop=$(curl -s "$BASE/shop/"); grep -q 'Amstel Landscape Cup' <<<"$shop" && ok "products listed on /shop/" || ko "products listed on /shop/"

# A test order through the Store API (the same API the block checkout uses).
jar=$(mktemp); trap 'rm -f $jar' EXIT
nonce=$(curl -s -c "$jar" -b "$jar" -D - -o /dev/null "$BASE/wp-json/wc/store/v1/cart" | awk -F': ' 'tolower($1)=="nonce"{print $2}' | tr -d '\r')
[[ -n $nonce ]] && ok "Store API cart nonce" || ko "Store API cart nonce"
pid=$(curl -s "$BASE/wp-json/wc/store/v1/products?sku=PP-013" | python3 -c 'import json,sys;print(json.load(sys.stdin)[0]["id"])')
curl -s -c "$jar" -b "$jar" -H "Nonce: $nonce" -H 'Content-Type: application/json' \
  -d "{\"id\":$pid,\"quantity\":1}" "$BASE/wp-json/wc/store/v1/cart/add-item" -o /dev/null -w '%{http_code}' | grep -q 201 \
  && ok "add 1 x PP-013 to cart" || ko "add to cart"
addr='{"first_name":"Ada","last_name":"Example","address_1":"1 Test Street","city":"Springfield","state":"OR","postcode":"97477","country":"US","email":"ada@example.com","phone":""}'
resp=$(curl -s -c "$jar" -b "$jar" -H "Nonce: $nonce" -H 'Content-Type: application/json' \
  -d "{\"billing_address\":$addr,\"shipping_address\":$addr,\"payment_method\":\"cod\"}" "$BASE/wp-json/wc/store/v1/checkout")
status=$(python3 -c 'import json,sys;d=json.loads(sys.argv[1]);print(d.get("status",""), d.get("order_id",""), d.get("payment_result",{}).get("payment_status",""))' "$resp" 2>/dev/null)
[[ $status == processing* || $status == on-hold* ]] && ok "test order placed (status order_id payment: $status)" || ko "test order" "$resp"
read -r oid okey < <(python3 -c 'import json,sys;d=json.loads(sys.argv[1]);print(d.get("order_id",""), d.get("order_key",""))' "$resp" 2>/dev/null)
order=$(curl -s -b "$jar" "$BASE/wp-json/wc/store/v1/order/$oid?key=$okey&billing_email=ada@example.com")
total=$(python3 -c 'import json,sys;d=json.loads(sys.argv[1]);t=d["totals"];print(int(t["total_price"])/10**t["currency_minor_unit"])' "$order" 2>/dev/null)
[[ $total == 60.0 ]] && ok "order total 54.00 (sale price) + 6.00 delivery = 60.00" || ko "order total" "got $total"
echo; [[ $fails == 0 ]] && echo "smoke: all passed" || echo "smoke: $fails failed"
exit $fails
