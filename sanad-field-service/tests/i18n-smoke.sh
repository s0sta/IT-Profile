#!/bin/bash
# Sanad — multilingual verification: fresh install + seed, then check English, German and Arabic.
# Usage: php -S 127.0.0.1:8096 &   then:  bash tests/i18n-smoke.sh
BASE="http://127.0.0.1:8096"
PASS=0; FAIL=0
export PATH="/opt/homebrew/bin:$PATH"

check() {
  if echo "$3" | grep -q "$2"; then PASS=$((PASS+1)); echo "  ✓ $1";
  else FAIL=$((FAIL+1)); echo "  ✗ $1 (expected: $2)"; fi
}
checknot() {
  if echo "$3" | grep -q "$2"; then FAIL=$((FAIL+1)); echo "  ✗ $1 (must NOT contain: $2)";
  else PASS=$((PASS+1)); echo "  ✓ $1"; fi
}
csrf() { grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
val()  { php -r "\$a=require 'lang/$1.php'; echo \$a['$2'];"; }
dval() { php -r "\$a=require 'lang/doc-$1.php'; echo \$a['$2'];"; }
login() { # login <lang> <jar>
  rm -f "$2"
  local T; T=$(curl -sL -c "$2" "$BASE/index.php?p=login&lang=$1" | csrf)
  curl -s -L -b "$2" -c "$2" -o /dev/null -d "csrf=$T&login=admin&password=Admin@1234" "$BASE/index.php?p=login"
}

cd "$(dirname "$0")/.."

echo "== 0. fresh install + seed =="
rm -f data/config.php data/sanad.sqlite*
JAR=/tmp/sanad-i18n-install.jar; rm -f $JAR
T=$(curl -s -c $JAR "$BASE/install.php" | csrf)
curl -s -L -b $JAR -c $JAR -o /dev/null -w "  install: HTTP %{http_code}\n" \
  -d "csrf=$T" -d "driver=sqlite" -d "site_name=Sanad" -d "admin_name=Hosam Admin" \
  -d "admin_username=admin" -d "admin_email=admin@sanad.local" -d "admin_password=Admin@1234" \
  -d "timezone=Asia/Riyadh" "$BASE/install.php"
php seed.php > /dev/null && echo "  seed: OK"

echo "== 1. language files =="
for L in de ar; do
  PHPOUT=$(php -r '$a=array_keys(require "lang/en.php"); $b=array_keys(require "lang/'"$L"'.php"); printf("missing:%d extra:%d", count(array_diff($a,$b)), count(array_diff($b,$a)));')
  check "$L has the same keys as English" "missing:0 extra:0" "$PHPOUT"
done

echo "== 2. public documentation page =="
for L in en de ar; do
  JD=/tmp/sanad-doc-$L.jar; rm -f $JD
  BODY=$(curl -sL -c $JD -b $JD "$BASE/index.php?p=doc&lang=$L")
  check "doc $L: own title" "$(dval $L title)" "$BODY"
  check "doc $L: renders sections" "doc-section" "$BODY"
  check "doc $L: flow diagram" "doc-flow" "$BODY"
done

echo "== 3. login page per language =="
for L in en de ar; do
  JL=/tmp/sanad-login-$L.jar; rm -f $JL
  HTML=$(curl -sL -c $JL "$BASE/index.php?p=login&lang=$L")
  check "$L login: lang attribute" "lang=\"$L\"" "$HTML"
  check "$L login: translated sign-in button" "$(val $L auth.sign_in)" "$HTML"
  if [ "$L" = "ar" ]; then check "ar login: rtl" 'dir="rtl"' "$HTML"; fi
  if [ "$L" = "de" ]; then checknot "de login: no English 'Sign in'" '>Sign in<' "$HTML"; fi
done

echo "== 4. every page in every language =="
for L in en de ar; do
  JAR2=/tmp/sanad-sess-$L.jar
  login "$L" "$JAR2"
  DASH=$(curl -s -b "$JAR2" -L "$BASE/index.php?p=dashboard")
  check "$L dashboard: title" "$(val $L dash.title)" "$DASH"
  check "$L dashboard: lang attribute" "lang=\"$L\"" "$DASH"
  if [ "$L" != "en" ]; then checknot "$L dashboard: no English leftover" "Operations overview" "$DASH"; fi
  PAGES_OK=1
  for page in dashboard board jobs "job&id=2" job_new "job_edit&id=2" customers "customer&id=1" contracts "contract&id=1" parts invoices "invoice&id=1" reports notifications profile admin/users admin/services admin/settings admin/audit doc; do
    CODE=$(curl -s -b "$JAR2" -o /dev/null -w "%{http_code}" "$BASE/index.php?p=$page")
    if [ "$CODE" != "200" ]; then FAIL=$((FAIL+1)); PAGES_OK=0; echo "  ✗ $L $page -> HTTP $CODE"; fi
  done
  if [ "$PAGES_OK" = "1" ]; then PASS=$((PASS+1)); echo "  ✓ $L: all 22 pages return HTTP 200"; fi
done

echo "== 5. Arabic RTL + content =="
JAR3=/tmp/sanad-ar.jar; login ar "$JAR3"
AR_DASH=$(curl -s -b "$JAR3" -L "$BASE/index.php?p=dashboard")
AR_BOARD=$(curl -s -b "$JAR3" -L "$BASE/index.php?p=board")
check "ar dashboard: dir=rtl" 'dir="rtl"' "$AR_DASH"
check "ar dashboard: Arabic nav" "$(val ar nav.jobs)" "$AR_DASH"
check "ar board: Arabic title" "$(val ar board.today)" "$AR_BOARD"
check "ar board: Arabic unassigned" "$(val ar job.unassigned)" "$AR_BOARD"
checknot "ar dashboard: no English 'Dashboard'" ">Dashboard<" "$AR_DASH"

echo "== 6. German content =="
JAR4=/tmp/sanad-de.jar; login de "$JAR4"
check "de jobs: title" "$(val de jobs.title)" "$(curl -s -b "$JAR4" -L "$BASE/index.php?p=jobs")"
check "de jobs: filter button" "$(val de common.filter)" "$(curl -s -b "$JAR4" -L "$BASE/index.php?p=jobs")"
check "de board: title part" "$(val de board.today)" "$(curl -s -b "$JAR4" -L "$BASE/index.php?p=board")"
check "de parts: title" "$(val de parts.title)" "$(curl -s -b "$JAR4" -L "$BASE/index.php?p=parts")"
check "de invoices: title" "$(val de invoices.title)" "$(curl -s -b "$JAR4" -L "$BASE/index.php?p=invoices")"
check "de contracts: title" "$(val de contracts.title)" "$(curl -s -b "$JAR4" -L "$BASE/index.php?p=contracts")"
check "de settings: general heading" "$(val de as.general)" "$(curl -s -b "$JAR4" -L "$BASE/index.php?p=admin/settings")"

echo ""
echo "========================================="
echo "SANAD I18N — PASS: $PASS   FAIL: $FAIL"
echo "========================================="
