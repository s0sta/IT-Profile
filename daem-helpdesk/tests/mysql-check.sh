#!/bin/bash
# Verify the v1.3 fixes against MariaDB (the production engine), not just SQLite.
set -u
BASE="http://127.0.0.1:8091"
SOCK=/tmp/mdb-daem.sock
export PATH="/opt/homebrew/bin:/opt/homebrew/opt/mariadb/bin:$PATH"
PASS=0; FAIL=0
check() { if echo "$3" | grep -q "$2"; then PASS=$((PASS+1)); echo "  ✓ $1"; else FAIL=$((FAIL+1)); echo "  ✗ $1 (expected: $2)"; fi; }
csrf() { grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
cd "$(dirname "$0")/.." || exit 1   # app root, not tests/

echo "== reset the MariaDB test database =="
mysql --socket=$SOCK -u root -e "DROP DATABASE IF EXISTS daem_test; CREATE DATABASE daem_test CHARACTER SET utf8mb4;
  CREATE USER IF NOT EXISTS 'daem'@'127.0.0.1' IDENTIFIED BY 'daempass123';
  GRANT ALL PRIVILEGES ON daem_test.* TO 'daem'@'127.0.0.1'; FLUSH PRIVILEGES;" && echo "  db reset ok"

echo "== install (MySQL driver) =="
rm -f data/config.php
JAR=/tmp/mysql-install.jar; rm -f $JAR
T=$(curl -s -c $JAR "$BASE/install.php" | csrf)
curl -s -b $JAR -c $JAR -o /dev/null -w "  install HTTP %{http_code} -> %{redirect_url}\n" \
  -d "csrf=$T" -d "driver=mysql" -d "db_host=127.0.0.1" -d "db_port=3399" -d "db_name=daem_test" \
  -d "db_user=daem" -d "db_pass=daempass123" -d "site_name=Daem" -d "admin_name=Hosam Admin" \
  -d "admin_username=admin" -d "admin_email=admin@daem.local" -d "admin_password=Admin@1234" \
  -d "timezone=Asia/Riyadh" "$BASE/install.php"
php seed.php > /dev/null 2>&1 && echo "  seed ok"

echo "== schema: the new column must exist in MariaDB =="
COLS=$(mysql --socket=$SOCK -u root -N -e "SHOW COLUMNS FROM daem_test.users;" 2>/dev/null | awk '{print $1}' | tr '\n' ' ')
check "must_change_password column exists" "must_change_password" "$COLS"
TABLES=$(mysql --socket=$SOCK -u root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='daem_test';")
check "13 tables created" "13" "$TABLES"

echo "== functional checks on MariaDB =="
JAR=/tmp/mysql-admin.jar; rm -f $JAR
T=$(curl -s -c $JAR "$BASE/index.php?p=login" | csrf)
curl -s -b $JAR -c $JAR -o /dev/null -d "csrf=$T&login=admin&password=Admin@1234" "$BASE/index.php?p=login"
T=$(curl -s -b $JAR "$BASE/index.php?p=admin/categories" | csrf)
DUP=$(curl -s -b $JAR -L -d "csrf=$T&action=add&name=Hardware&description=dup" "$BASE/index.php?p=admin/categories")
check "duplicate category refused" "already exists" "$DUP"
RESET=$(curl -s -b $JAR -L -d "csrf=$T&action=reset_password&id=4" "$BASE/index.php?p=admin/users")
check "one-time password issued" "one-time password" "$RESET"
OTP=$(echo "$RESET" | grep -oE '[0-9a-f]{8}' | head -1)
JAR2=/tmp/mysql-otp.jar; rm -f $JAR2
T2=$(curl -s -c $JAR2 "$BASE/index.php?p=login" | csrf)
FLOW=$(curl -s -b $JAR2 -c $JAR2 -L -d "csrf=$T2&login=khalid.salem&password=$OTP" "$BASE/index.php?p=login")
check "forced profile after one-time login" "must choose a new password" "$FLOW"
T2=$(echo "$FLOW" | csrf)
CH=$(curl -s -b $JAR2 -c $JAR2 -L -d "csrf=$T2&current_password=$OTP&new_password=NewPass@2026&confirm_password=NewPass@2026" "$BASE/index.php?p=profile")
check "password change clears the flag" "password is changed" "$CH"
FLAG=$(mysql --socket=$SOCK -u root -N -e "SELECT must_change_password FROM daem_test.users WHERE username='khalid.salem';")
check "flag cleared in the database" "0" "$FLAG"
CODE404=$(curl -s -o /dev/null -w '%{http_code}' -b $JAR "$BASE/index.php?p=ticket&id=999999")
check "missing ticket → 404" "404" "$CODE404"
STYLED=$(curl -s -b $JAR "$BASE/index.php?p=ticket&id=999999")
check "styled error page" "error-card" "$STYLED"

echo ""
echo "========================================="
echo "MARIADB PASS: $PASS   FAIL: $FAIL"
echo "========================================="
