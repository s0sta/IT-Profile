#!/bin/bash
# Idara — bilingual verification: Arabic is the default/master language, English
# is the alternative. Fresh install + seed, then check both languages end-to-end.
#
# Usage:  php -S 127.0.0.1:8105 &   then:  bash tests/i18n-smoke.sh
#         BASE=http://host:port bash tests/i18n-smoke.sh
BASE="${BASE:-http://127.0.0.1:8105}"
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
val()  { php -r "\$a=require 'lang/$1.php'; echo \$a['$2'] ?? '';"; }
dval() { php -r "\$a=require 'lang/doc-$1.php'; echo \$a['$2'] ?? '';"; }
# Count Arabic-Indic digits (٠-٩) in a saved HTML file.
arabic_digits() { php -r 'preg_match_all("/[\x{0660}-\x{0669}]/u", file_get_contents($argv[1]), $m); echo count($m[0]);' "$1"; }
get()  { curl -s -L "$@"; }
login() { # login <lang> <jar>
  rm -f "$2"
  local T
  T=$(get -c "$2" "$BASE/index.php?p=login&lang=$1" | csrf)
  curl -s -L -b "$2" -c "$2" -o /dev/null \
    -d "csrf=$T&login=sarah.qahtani&password=Manager@1234" "$BASE/index.php?p=login"
}
check_pages() { # check_pages <jar> <lang>
  local ok=1
  for page in dashboard tasks new-task approvals new-approval team correspondence new-letter meetings calendar delegations notifications profile doc; do
    local CODE
    CODE=$(curl -s -b "$1" -o /dev/null -w "%{http_code}" "$BASE/index.php?p=$page")
    if [ "$CODE" != "200" ]; then FAIL=$((FAIL+1)); ok=0; echo "  ✗ $2 $page -> HTTP $CODE"; fi
  done
  if [ "$ok" = "1" ]; then PASS=$((PASS+1)); echo "  ✓ $2: all 14 workspace pages return HTTP 200"; fi
}

cd "$(dirname "$0")/.." || exit 1

echo "== 0. Fresh install + seed =="
rm -f data/config.php data/idara.sqlite data/idara.sqlite-wal data/idara.sqlite-shm
JAR=/tmp/i18n-install.jar; rm -f $JAR
T=$(get -c $JAR "$BASE/install.php" | csrf)
curl -s -b $JAR -c $JAR -o /dev/null -w "  install: HTTP %{http_code}\n" \
  -d "csrf=$T" -d "driver=sqlite" -d "site_name=إدارة" -d "admin_name=مسؤول النظام" \
  -d "admin_username=admin" -d "admin_email=admin@idara.local" -d "admin_password=Admin@1234" \
  -d "timezone=Asia/Riyadh" "$BASE/install.php"
php seed.php > /dev/null && echo "  seed: OK"

echo "== 1. Login page defaults to Arabic (master language) =="
JA=/tmp/i18n-login-ar.jar; rm -f $JA
AR_LOGIN=$(get -c $JA "$BASE/index.php?p=login")
check "default login page: lang=ar" 'lang="ar"' "$AR_LOGIN"
check "default login page: dir=rtl" 'dir="rtl"' "$AR_LOGIN"
check "default login page: Arabic sign-in label" "$(val ar auth.sign_in)" "$AR_LOGIN"

echo "== 2. Switching to English =="
JE=/tmp/i18n-login-en.jar; rm -f $JE
EN_LOGIN=$(curl -s -L -c $JE -b $JE "$BASE/index.php?p=login&lang=en")
check "English login page: lang=en" 'lang="en"' "$EN_LOGIN"
check "English login page: dir=ltr" 'dir="ltr"' "$EN_LOGIN"
check "English login page: English sign-in label" "$(val en auth.sign_in)" "$EN_LOGIN"
checknot "English login page: no Arabic label" "$(val ar auth.sign_in)" "$EN_LOGIN"

echo "== 3. Public documentation page (no login) =="
JD_AR=/tmp/i18n-doc-ar.jar; rm -f $JD_AR
DOC_AR=$(get -c $JD_AR -b $JD_AR "$BASE/index.php?p=doc")
check "doc ar: renders doc-section blocks" "doc-section" "$DOC_AR"
check "doc ar: Arabic title" "$(dval ar title)" "$DOC_AR"
JD_EN=/tmp/i18n-doc-en.jar; rm -f $JD_EN
DOC_EN=$(get -c $JD_EN -b $JD_EN "$BASE/index.php?p=doc&lang=en")
check "doc en: renders doc-section blocks" "doc-section" "$DOC_EN"
check "doc en: English title" "$(dval en title)" "$DOC_EN"
checknot "doc en: is not the Arabic document" "$(dval ar title)" "$DOC_EN"

echo "== 4. Logged-in manager — 14 pages in both languages =="
JAR_AR=/tmp/i18n-mgr-ar.jar; login ar "$JAR_AR"
JAR_EN=/tmp/i18n-mgr-en.jar; login en "$JAR_EN"
check_pages "$JAR_AR" "ar"
check_pages "$JAR_EN" "en"

echo "== 5. Language-specific content =="
DASH_AR=$(curl -s -b "$JAR_AR" "$BASE/index.php?p=dashboard")
DASH_EN=$(curl -s -b "$JAR_EN" "$BASE/index.php?p=dashboard")
TASKS_AR=$(curl -s -b "$JAR_AR" "$BASE/index.php?p=tasks")
TASKS_EN=$(curl -s -b "$JAR_EN" "$BASE/index.php?p=tasks")
check "ar dashboard: Arabic manager title" "$(val ar dash.title_manager)" "$DASH_AR"
check "en dashboard: English manager title" "$(val en dash.title_manager)" "$DASH_EN"
check "ar tasks: Arabic page title" "$(val ar tasks.title)" "$TASKS_AR"
check "en tasks: English page title" "$(val en tasks.title)" "$TASKS_EN"
echo "$DASH_EN" > /tmp/i18n-dash-en.html
echo "$TASKS_EN" > /tmp/i18n-tasks-en.html
check "en dashboard: no Arabic-Indic digits" "^0$" "$(arabic_digits /tmp/i18n-dash-en.html)"
check "en tasks: no Arabic-Indic digits" "^0$" "$(arabic_digits /tmp/i18n-tasks-en.html)"

echo "== 6. Language key parity =="
P_MAIN=$(php -r '$ar=require "lang/ar.php"; $en=require "lang/en.php";
  echo (count($ar)>0 && count($ar)===count($en) && !array_diff(array_keys($ar),array_keys($en)) && !array_diff(array_keys($en),array_keys($ar))) ? "ok" : "BAD(".count($ar)."/".count($en).")";')
check "lang/ar.php vs lang/en.php: equal counts, empty diffs" "ok" "$P_MAIN"
P_DOC=$(php -r '$ar=require "lang/doc-ar.php"; $en=require "lang/doc-en.php";
  echo (count($ar)>0 && count($ar)===count($en) && !array_diff(array_keys($ar),array_keys($en)) && !array_diff(array_keys($en),array_keys($ar))) ? "ok" : "BAD(".count($ar)."/".count($en).")";')
check "lang/doc-ar.php vs lang/doc-en.php: equal counts, empty diffs" "ok" "$P_DOC"

echo ""
echo "I18N PASS: $PASS FAIL: $FAIL"
[ "$FAIL" -eq 0 ]
