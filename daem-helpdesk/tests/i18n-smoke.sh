#!/bin/bash
# Daem — multilingual verification: fresh install, then check English, German and Arabic.
# Usage: php -S 127.0.0.1:8090 &  then:  bash tests/i18n-smoke.sh
BASE="http://127.0.0.1:8090"
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
get()  { curl -s -L "$@"; }
login() { # login <lang> <jar>
  rm -f "$2"
  local T
  T=$(get -c "$2" "$BASE/index.php?p=login&lang=$1" | csrf)
  curl -s -L -b "$2" -c "$2" -o /dev/null \
    -d "csrf=$T&login=admin&password=Admin@1234" "$BASE/index.php?p=login"
}

cd "$(dirname "$0")/.."

echo "== 0. fresh install =="
rm -f data/config.php data/daem.sqlite*
JAR=/tmp/i18n-install.jar; rm -f $JAR
T=$(get -c $JAR "$BASE/install.php" | csrf)
curl -s -L -b $JAR -c $JAR -o /dev/null -w "  install: HTTP %{http_code}\n" \
  -d "csrf=$T" -d "driver=sqlite" -d "site_name=Daem" -d "admin_name=Hosam Admin" \
  -d "admin_username=admin" -d "admin_email=admin@daem.local" -d "admin_password=Admin@1234" \
  -d "timezone=Asia/Riyadh" "$BASE/install.php"

echo "== 1. public documentation page (no login) =="
for L in en de ar; do
  JD=/tmp/i18n-doc-$L.jar; rm -f $JD
  BODY=$(get -c $JD -b $JD "$BASE/index.php?p=doc&lang=$L")
  check "doc $L: own title" "$(dval $L title)" "$BODY"
  check "doc $L: renders sections" "doc-section" "$BODY"
  check "doc $L: flow diagram" "doc-flow" "$BODY"
done
checknot "doc en is not the Arabic version" "$(dval ar title)" "$(get -c /tmp/i18n-doc-en.jar -b /tmp/i18n-doc-en.jar "$BASE/index.php?p=doc&lang=en")"

echo "== 2. login page per language =="
for L in en de ar; do
  JL=/tmp/i18n-login-$L.jar
  HTML=$(get -c $JL "$BASE/index.php?p=login&lang=$L")
  check "$L login: lang attribute" "lang=\"$L\"" "$HTML"
  check "$L login: translated sign-in button" "$(val $L auth.sign_in)" "$HTML"
  if [ "$L" = "ar" ]; then check "ar login: rtl" 'dir="rtl"' "$HTML"; fi
  if [ "$L" = "de" ]; then checknot "de login: no English 'Sign in'" '>Sign in<' "$HTML"; fi
done

echo "== 3. logged-in pages per language =="
for L in en de ar; do
  JAR2=/tmp/i18n-sess-$L.jar
  login "$L" "$JAR2"
  DASH=$(curl -s -b "$JAR2" -L "$BASE/index.php?p=dashboard")
  check "$L dashboard: title" "$(val $L dash.title_staff)" "$DASH"
  check "$L dashboard: stat label" "$(val $L dash.open)" "$DASH"
  check "$L dashboard: lang attribute" "lang=\"$L\"" "$DASH"
  if [ "$L" != "en" ]; then
    checknot "$L dashboard: no English 'Open tickets'" "Open tickets" "$DASH"
  fi
  PAGES_OK=1
  for page in tickets new kb notifications profile admin/users admin/categories admin/kb admin/settings admin/audit admin/reports doc; do
    CODE=$(curl -s -b "$JAR2" -o /dev/null -w "%{http_code}" "$BASE/index.php?p=$page")
    if [ "$CODE" != "200" ]; then FAIL=$((FAIL+1)); PAGES_OK=0; echo "  ✗ $L $page -> HTTP $CODE"; fi
  done
  if [ "$PAGES_OK" = "1" ]; then PASS=$((PASS+1)); echo "  ✓ $L: all 12 pages return HTTP 200"; fi
done

echo "== 4. Arabic layout + content =="
JAR3=/tmp/i18n-ar2.jar
login ar "$JAR3"
AR_DASH=$(curl -s -b "$JAR3" -L "$BASE/index.php?p=dashboard")
AR_TICKETS=$(curl -s -b "$JAR3" -L "$BASE/index.php?p=tickets")
check "ar dashboard: dir=rtl" 'dir="rtl"' "$AR_DASH"
check "ar dashboard: Arabic nav" "$(val ar nav.knowledge_base)" "$AR_DASH"
check "ar tickets: Arabic status filter" "$(val ar tickets.all_statuses)" "$AR_TICKETS"
check "ar tickets: Arabic priority filter" "$(val ar tickets.all_priorities)" "$AR_TICKETS"
checknot "ar dashboard: no English label" ">Dashboard<" "$AR_DASH"

echo "== 5. German page content =="
JAR4=/tmp/i18n-de2.jar
login de "$JAR4"
check "de tickets: page title" "$(val de tickets.title)" "$(curl -s -b "$JAR4" -L "$BASE/index.php?p=tickets")"
check "de tickets: filter button" "$(val de common.filter)" "$(curl -s -b "$JAR4" -L "$BASE/index.php?p=tickets")"
check "de new ticket: heading" "$(val de new.heading)" "$(curl -s -b "$JAR4" -L "$BASE/index.php?p=new")"
check "de settings: general heading" "$(val de as.general)" "$(curl -s -b "$JAR4" -L "$BASE/index.php?p=admin/settings")"
check "de kb: search placeholder" "$(val de kb.search_placeholder)" "$(curl -s -b "$JAR4" -L "$BASE/index.php?p=kb")"
check "de notifications: title" "$(val de notif.title)" "$(curl -s -b "$JAR4" -L "$BASE/index.php?p=notifications")"

echo ""
echo "========================================="
echo "I18N PASS: $PASS   FAIL: $FAIL"
echo "========================================="
