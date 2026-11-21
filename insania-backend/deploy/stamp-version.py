#!/usr/bin/env python3
"""Puts the release id on every script and stylesheet address of the staff app and the website (assets/app.js?v=ID),
so phones, browsers and the host's cache always fetch the new files after an update instead of mixing old and new.

  deploy/stamp-version.py PUBLIC_HTML_DIR RELEASE_ID
"""
import re, sys, os, glob
root, rid = sys.argv[1], sys.argv[2]
pat = re.compile(r'((?:src|href)="(?:assets/[^"?]+\.(?:js|css)|site\.js))(?:\?v=[^"]*)?"')
files = glob.glob(os.path.join(root, "app", "*.html")) + [os.path.join(root, "index.html")]
n = 0
for f in files:
    if not os.path.isfile(f): continue
    s = open(f, encoding="utf-8").read()
    t = pat.sub(lambda m: m.group(1) + "?v=" + rid + '"', s)
    if t != s: open(f, "w", encoding="utf-8").write(t); n += 1
sw = os.path.join(root, "app", "sw.js")
if os.path.isfile(sw):
    s = open(sw, encoding="utf-8").read()
    t = re.sub(r'var VERSION = "[^"]*";', 'var VERSION = "insania-app-' + rid + '";', s)
    if t != s: open(sw, "w", encoding="utf-8").write(t); n += 1
print("stamped", n, "files with", rid)
