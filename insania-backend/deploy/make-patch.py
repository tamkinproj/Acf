#!/usr/bin/env python3
"""Builds an update patch zip from two built packages (the old one and the new one).

  deploy/make-patch.py OLD_DIR NEW_DIR OUT.zip --name "Name" --version 1.2 --notes "What changed"

OLD_DIR / NEW_DIR each hold insania_app/ and public_html/ (what deploy/build-package.sh makes, plus the website).
The zip holds only files that are new or changed (inside the places a patch may touch), manifest.json, and
a "delete" list. It must then be signed with the owner's key (tools/patch-signer.html) before the app accepts it.
"""
import argparse, hashlib, json, os, sys, zipfile

ALLOWED = {
    "insania_app": ["app/", "config/", "routes/", "resources/", "database/migrations/", "database/seeders/", "vendor/composer/", "BUILD", "README.md", "HOSTINGER.md"],
    "public_html": ["app/", "server/index.php", "server/.htaccess", "index.html", ".htaccess"],
}

def allowed(root, rel):
    return any(rel == a or (a.endswith("/") and rel.startswith(a)) for a in ALLOWED[root])

def walk(base):
    out = {}
    for root in ALLOWED:
        top = os.path.join(base, root)
        for d, _, files in os.walk(top):
            for f in files:
                p = os.path.join(d, f); rel = os.path.relpath(p, top).replace(os.sep, "/")
                if allowed(root, rel): out[root + "/" + rel] = p
    return out

def sha(p):
    with open(p, "rb") as f: return hashlib.sha256(f.read()).hexdigest()

ap = argparse.ArgumentParser()
ap.add_argument("old"); ap.add_argument("new"); ap.add_argument("out")
ap.add_argument("--name", required=True); ap.add_argument("--version", required=True); ap.add_argument("--notes", default="")
a = ap.parse_args()

old, new = walk(a.old), walk(a.new)
read = lambda d: open(os.path.join(d, "insania_app", "BUILD")).read().strip()
build, from_build = read(a.new), read(a.old)
changed = sorted(k for k in new if k not in old or sha(new[k]) != sha(old[k]))
deleted = sorted(k for k in old if k not in new)
if build == from_build: sys.exit("The two packages have the same BUILD; build the new one with deploy/build-package.sh")

manifest = {"name": a.name, "version": a.version, "build": build, "from_build": from_build, "notes": a.notes, "delete": deleted}
with zipfile.ZipFile(a.out, "w", zipfile.ZIP_DEFLATED) as z:
    z.writestr("manifest.json", json.dumps(manifest, indent=1))
    for k in changed: z.write(new[k], k)
print("patch %s -> %s: %d files, %d removed, %d bytes" % (from_build, build, len(changed), len(deleted), os.path.getsize(a.out)))
