#!/bin/bash
# Rebuilds the live setup (first FINAL package + Update 2) on local Apache + MariaDB and runs every browser check.
SP=/tmp/claude-0/-home-user-Acf/5caf8229-4202-59b1-b469-674b794f63c4/scratchpad; A=/srv/apxtest; B=http://127.0.0.1:8090; NP=$(npm root -g)
pgrep mariadbd >/dev/null || { (nohup mariadbd --user=root --datadir=$SP/mysql/data --socket=$SP/mysql/run/m.sock --port=3307 --bind-address=127.0.0.1 --pid-file=$SP/mysql/run/m.pid >$SP/mysql/log 2>&1 &); sleep 6; }
mariadb -uroot -S $SP/mysql/run/m.sock -e "drop database if exists insania; create database insania character set utf8mb4"
apache2 -f $A/httpd.conf -k stop 2>/dev/null; sleep 1; rm -rf $A/site; mkdir $A/site; cp -r $SP/old/. $A/site/; chown -R www-data:www-data $A; chmod -R a+rX $A; apache2 -f $A/httpd.conf -k start; sleep 2
echo "== 1. install the first FINAL package with the browser installer"; cd $SP/e2e && NODE_PATH=$NP node install.js $SP/e2e 2>&1 | grep -E "^[0-9]\." 
echo "== 2. extract Update 2 (the zip you have) and run /server/upgrade"; unzip -qo /home/user/Acf/dist/alinsania-UPDATE-2.zip -d $A/site; rm -f $A/site/UPDATE-READ-ME.md
python3 - <<PY
import re
p="$A/site/insania_app/config/updates.php"; s=open(p).read(); pub=open("$SP/keys/test.public.pem").read().strip()
open(p,"w").write(re.sub(r"<<<'PEM'\n.*?\nPEM,", "<<<'PEM'\n"+pub+"\nPEM,", s, flags=re.S))
PY
chown -R www-data:www-data $A/site; chmod -R a+rX $A/site; sleep 3
curl -s $B/server/upgrade | grep -o "Updates waiting: <strong>[0-9]*" | sed 's/<strong>//'; TOK=$(cat $A/site/insania_app/storage/app/install/token); curl -s $B/server/upgrade -d token=$TOK | grep -o "Done ✓"; echo "build: $(cat $A/site/insania_app/BUILD)"
echo "== 3. first-run setup"; NODE_PATH=$NP node wizard.js 2>&1 | grep -E "FAIL|CHECKS"
(cd $A/site/insania_app && APP_ENV=local php artisan db:seed --class=DemoSeeder --force >/dev/null 2>&1); echo "   demo accounts added (local copy only)"; sleep 61
echo "== 4. role matrix in the browser"; NODE_PATH=$NP node roles.js 2>&1 | grep -E "FAIL|ROLES OK|FAILURES"; sleep 61
echo "== 5. offline"; NODE_PATH=$NP node offline.js 2>&1 | grep -E "FAIL|OFFLINE CHECKS|SOME"; sleep 61
echo "== 6. layout, languages, errors"; NODE_PATH=$NP node layout.js 2>&1 | grep -E "PASS|FAIL"
