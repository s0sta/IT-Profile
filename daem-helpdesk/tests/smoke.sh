#!/bin/bash
# Daem — full HTTP smoke test. Resets the database, installs fresh, seeds, then tests every flow.
# Usage: php -S 127.0.0.1:8090 &  then:  bash tests/smoke.sh
BASE="http://127.0.0.1:8090"
PASS=0; FAIL=0

check() {
  if echo "$3" | grep -q "$2"; then PASS=$((PASS+1)); echo "  ✓ $1";
  else FAIL=$((FAIL+1)); echo "  ✗ $1 (expected: $2)"; fi
}
csrf() { grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }

echo "== 0. Fresh install + seed =="
cd "$(dirname "$0")/.."
rm -f data/config.php data/daem.sqlite data/daem.sqlite-wal data/daem.sqlite-shm
JAR=/tmp/daem-install.jar; rm -f $JAR
H=$(curl -s -c $JAR "$BASE/install.php")
T=$(echo "$H" | csrf)
curl -s -b $JAR -c $JAR -o /tmp/daem-install-out.html -w "  install: HTTP %{http_code}\n" \
  -d "csrf=$T" -d "driver=sqlite" -d "site_name=Daem" \
  -d "admin_name=Hosam Admin" -d "admin_username=admin" -d "admin_email=admin@daem.local" \
  -d "admin_password=Admin@1234" -d "timezone=Asia/Riyadh" "$BASE/install.php"
grep -o 'Database error[^<]*' /tmp/daem-install-out.html | head -2
export PATH="/opt/homebrew/bin:$PATH"
php seed.php > /dev/null && echo "  seed: OK"

echo "== 1. Login as admin =="
JAR=/tmp/daem-admin.jar; rm -f $JAR
H=$(curl -s -c $JAR "$BASE/index.php?p=login")
T=$(echo "$H" | csrf)
R=$(curl -s -b $JAR -c $JAR -o /dev/null -w "%{http_code}:%{redirect_url}" -d "csrf=$T&login=admin&password=Admin@1234" "$BASE/index.php?p=login")
check "login redirects to dashboard" "dashboard" "$R"
D=$(curl -s -b $JAR "$BASE/index.php?p=dashboard")
check "dashboard title" "IT Service Desk Overview" "$D"
check "dashboard SLA overdue card" "SLA response overdue" "$D"
check "dashboard 14-day chart" "Tickets created — last 14 days" "$D"

echo "== 2. Ticket list + filters =="
L=$(curl -s -b $JAR "$BASE/index.php?p=tickets")
check "list shows seeded ticket" "Cannot access the trading system" "$L"
check "list shows SLA flag" "sla-flag" "$L"
LF=$(curl -s -b $JAR "$BASE/index.php?p=tickets&status=open&priority=urgent&q=trading")
check "filters narrow list" "Cannot access the trading system" "$LF"

echo "== 3. Ticket detail, reply, internal note, status =="
V=$(curl -s -b $JAR "$BASE/index.php?p=ticket&id=1")
check "ticket view subject" "Cannot access the trading system" "$V"
check "SLA breach chip" "Overdue" "$V"
T=$(echo "$V" | csrf)
curl -s -b $JAR -o /dev/null -d "csrf=$T&action=reply&body=Checking the switch now — I will update you within the hour." "$BASE/index.php?p=ticket&id=1"
V2=$(curl -s -b $JAR "$BASE/index.php?p=ticket&id=1")
check "public reply appears" "Checking the switch now" "$V2"
check "first response tracked" "On time" "$V2"
T=$(echo "$V2" | csrf)
curl -s -b $JAR -o /dev/null -d "csrf=$T&action=reply&body=Internal: switch config changed last night.&internal=1" "$BASE/index.php?p=ticket&id=1"
V3=$(curl -s -b $JAR "$BASE/index.php?p=ticket&id=1")
check "internal note for staff" "Internal note" "$V3"
T=$(echo "$V3" | csrf)
curl -s -b $JAR -o /dev/null -d "csrf=$T&action=status&status=resolved" "$BASE/index.php?p=ticket&id=1"
V4=$(curl -s -b $JAR "$BASE/index.php?p=ticket&id=1")
check "status → Resolved" "badge-resolved" "$V4"

echo "== 4. New ticket + KB API =="
N=$(curl -s -b $JAR "$BASE/index.php?p=new")
T=$(echo "$N" | csrf)
RN=$(curl -s -b $JAR -o /dev/null -w "%{redirect_url}" -d "csrf=$T&subject=Test ticket from smoke&description=This is a smoke test ticket with enough characters.&category_id=2&priority=medium" "$BASE/index.php?p=new")
NEWID=$(echo "$RN" | grep -o 'id=[0-9]*' | head -1 | cut -d= -f2)
check "new ticket created" "ticket" "$RN"
NV=$(curl -s -b $JAR "$BASE/index.php?p=ticket&id=$NEWID")
check "new ticket visible" "Test ticket from smoke" "$NV"
check "reference assigned" "TCK-" "$NV"
API=$(curl -s -b $JAR "$BASE/index.php?p=api&a=kb_search&q=password")
check "KB search API" "reset your password" "$API"

echo "== 5. Knowledge base + voting =="
KB=$(curl -s -b $JAR "$BASE/index.php?p=kb")
check "KB article list" "Connecting to the VPN from home" "$KB"
A=$(curl -s -b $JAR "$BASE/index.php?p=article&slug=how-to-reset-your-password")
check "article body" "Password rules" "$A"
T=$(echo "$A" | csrf)
curl -s -b $JAR -c $JAR -o /dev/null -d "csrf=$T&vote=1" "$BASE/index.php?p=article&slug=how-to-reset-your-password"
A2=$(curl -s -b $JAR -c $JAR "$BASE/index.php?p=article&slug=how-to-reset-your-password")
check "vote flash confirmed" "Thank you for your feedback" "$A2"

echo "== 6. Admin pages =="
check "admin users" "omar.ali" "$(curl -s -b $JAR "$BASE/index.php?p=admin/users")"
check "admin categories" "Hardware" "$(curl -s -b $JAR "$BASE/index.php?p=admin/categories")"
check "admin kb" "VPN" "$(curl -s -b $JAR "$BASE/index.php?p=admin/kb")"
check "admin settings" "SLA targets" "$(curl -s -b $JAR "$BASE/index.php?p=admin/settings")"
check "audit log" "login" "$(curl -s -b $JAR "$BASE/index.php?p=admin/audit")"
check "reports page" "SLA reached" "$(curl -s -b $JAR "$BASE/index.php?p=admin/reports")"
check "CSV export" "TCK-" "$(curl -s -b $JAR "$BASE/index.php?p=admin/reports&export=csv")"

echo "== 7. Requester role (Khalid) — isolation + reopen =="
JAR2=/tmp/daem-khalid.jar; rm -f $JAR2
H=$(curl -s -c $JAR2 "$BASE/index.php?p=login")
T=$(echo "$H" | csrf)
curl -s -b $JAR2 -c $JAR2 -o /dev/null -d "csrf=$T&login=khalid.salem&password=User@1234" "$BASE/index.php?p=login"
check "requester dashboard" "My Support" "$(curl -s -b $JAR2 "$BASE/index.php?p=dashboard")"
KL=$(curl -s -b $JAR2 "$BASE/index.php?p=tickets")
check "requester sees own ticket" "Cannot access the trading system" "$KL"
if echo "$KL" | grep -q "Test ticket from smoke"; then FAIL=$((FAIL+1)); echo "  ✗ isolation: admin ticket leaked"; else PASS=$((PASS+1)); echo "  ✓ requester list isolated from others' tickets"; fi
KV=$(curl -s -b $JAR2 "$BASE/index.php?p=ticket&id=3")
if echo "$KV" | grep -q "Internal note"; then FAIL=$((FAIL+1)); echo "  ✗ internal note leaked"; else PASS=$((PASS+1)); echo "  ✓ internal note hidden from requester"; fi
T=$(curl -s -b $JAR2 "$BASE/index.php?p=ticket&id=1" | csrf)
curl -s -b $JAR2 -o /dev/null -d "csrf=$T&action=reply&body=Actually it is happening again, please check." "$BASE/index.php?p=ticket&id=1"
KR=$(curl -s -b $JAR2 "$BASE/index.php?p=ticket&id=1")
check "requester reply reopens ticket" "badge-open" "$KR"

echo "== 8. Attachments =="
echo "smoke attachment content" > /tmp/daem-att.txt
T=$(curl -s -b $JAR "$BASE/index.php?p=ticket&id=$NEWID" | csrf)
curl -s -b $JAR -o /dev/null -F "csrf=$T" -F "action=reply" -F "body=Attaching evidence." -F "attachments[]=@/tmp/daem-att.txt;type=text/plain" "$BASE/index.php?p=ticket&id=$NEWID"
AV=$(curl -s -b $JAR "$BASE/index.php?p=ticket&id=$NEWID")
check "attachment listed" "daem-att.txt" "$AV"
ATTID=$(echo "$AV" | grep -o 'download&id=[0-9]*' | head -1 | cut -d= -f2)
check "attachment downloads" "smoke attachment content" "$(curl -s -b $JAR "$BASE/index.php?p=download&id=$ATTID")"

echo "== 9. Notifications =="
NOTIF=$(curl -s -b $JAR "$BASE/index.php?p=notifications")
check "reopen notification for admin" "opened again by the requester" "$NOTIF"
check "notif count API" '"unread"' "$(curl -s -b $JAR "$BASE/index.php?p=api&a=notif_count")"

echo "== 10. Profile password change =="
T=$(curl -s -b $JAR "$BASE/index.php?p=profile" | csrf)
curl -s -b $JAR -o /dev/null -d "csrf=$T&current_password=Admin@1234&new_password=Admin@5678&confirm_password=Admin@5678" "$BASE/index.php?p=profile"
JAR3=/tmp/daem-admin2.jar; rm -f $JAR3
H=$(curl -s -c $JAR3 "$BASE/index.php?p=login")
T=$(echo "$H" | csrf)
R=$(curl -s -b $JAR3 -c $JAR3 -o /dev/null -w "%{redirect_url}" -d "csrf=$T&login=admin&password=Admin@5678" "$BASE/index.php?p=login")
check "re-login with new password" "dashboard" "$R"

echo "== 11. CSRF rejection =="
JAR4=/tmp/daem-csrf.jar; rm -f $JAR4
curl -s -c $JAR4 "$BASE/index.php?p=login" > /dev/null
H=$(curl -s -b $JAR4 -c $JAR4 "$BASE/index.php?p=login")
T=$(echo "$H" | csrf)
curl -s -b $JAR4 -c $JAR4 -o /dev/null -d "csrf=$T&login=admin&password=Admin@5678" "$BASE/index.php?p=login"
C=$(curl -s -b $JAR4 -o /dev/null -w "%{http_code}" -d "csrf=WRONG&action=reply&body=x" "$BASE/index.php?p=ticket&id=$NEWID")
check "bad CSRF → 403" "403" "$C"

echo "== 12. Login throttling =="
JAR5=/tmp/daem-throttle.jar; rm -f $JAR5
H=$(curl -s -c $JAR5 "$BASE/index.php?p=login")
T=$(echo "$H" | csrf)
for i in 1 2 3 4 5 6; do
  curl -s -b $JAR5 -c $JAR5 -o /dev/null -d "csrf=$T&login=nobody&password=wrongpass" "$BASE/index.php?p=login"
done
TH=$(curl -s -b $JAR5 "$BASE/index.php?p=login")
check "throttle notice on GET" "locked for 15 minutes" "$TH"

echo ""
echo "========================================="
echo "PASS: $PASS   FAIL: $FAIL"
echo "========================================="
