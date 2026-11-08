#!/usr/bin/env python3
"""Download the product and hero photos listed in seed/catalog.json into .cache/images/ (full resolution).
All photos are public-domain (CC0) objects from the Rijksmuseum collection, found through Openverse.
Full resolution on purpose: the 'before' state uses them as an owner who uploads camera originals would."""
import json, os, sys, urllib.request, hashlib
root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
cat = json.load(open(os.path.join(root, 'seed', 'catalog.json')))
out = os.path.join(root, '.cache', 'images'); os.makedirs(out, exist_ok=True)
items = [(p['sku'], p['image']['url']) for p in cat['products']] + [('hero', cat['hero']['url'])]
for name, url in items:
    fn = os.path.join(out, name + '.jpg')
    if os.path.exists(fn) and os.path.getsize(fn) > 10000:
        continue
    req = urllib.request.Request(url + '=s0', headers={'User-Agent': 'woo-store-demo-seed/1.0'})
    with urllib.request.urlopen(req, timeout=120) as r, open(fn + '.part', 'wb') as f:
        f.write(r.read())
    os.replace(fn + '.part', fn)
    print(f'{name:7s} {os.path.getsize(fn)/1e6:5.2f} MB')
print('images ready in', out)
