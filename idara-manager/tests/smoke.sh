#!/bin/bash
# Idara — Arabic-first end-to-end smoke test.
# Resets the database, installs fresh, seeds, then tests the manager workspace,
# the delegate/employee views, the admin area and the security negatives.
#
# Usage:  php -S 127.0.0.1:8105 &   then:  bash tests/smoke.sh
#         BASE=http://host:port bash tests/smoke.sh   to point at another server
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
# Arabic (master language) string for a key.
val() { php -r "\$a=require 'lang/ar.php'; echo \$a['$1'] ?? '';"; }
# Master string with {placeholder} substitutions: valt key name value [...]
valt() { php -r '$a=require "lang/ar.php"; $s=$a[$argv[1]] ?? ""; for($i=2;$i<count($argv);$i+=2){$s=str_replace("{".$argv[$i]."}",$argv[$i+1],$s);} echo $s;' "$@"; }
# Read the numeric value of a dashboard stat card whose label matches $2
# (the label may be followed by extra text, e.g. the overdue counter).
statval() { php -r '$h=file_get_contents($argv[1]); $l=$argv[2]; if(preg_match("/stat-value\">([0-9]+)<\/span>\s*<span class=\"stat-label\">".preg_quote($l,"/")."/u",$h,$m)) echo $m[1]; else echo "NA";' "$1" "$2"; }
login() { # login <username> <password> <jar>
  rm -f "$3"
  local T
  T=$(curl -s -L -c "$3" "$BASE/index.php?p=login" | csrf)
  curl -s -b "$3" -c "$3" -L -o /dev/null -d "csrf=$T&login=$1&password=$2" "$BASE/index.php?p=login"
}

cd "$(dirname "$0")/.." || exit 1

echo "== 0. Fresh install + seed =="
rm -f data/config.php data/idara.sqlite data/idara.sqlite-wal data/idara.sqlite-shm
JAR_INSTALL=/tmp/idara-install.jar; rm -f $JAR_INSTALL
H=$(curl -s -c $JAR_INSTALL "$BASE/install.php")
T=$(echo "$H" | csrf)
curl -s -b $JAR_INSTALL -c $JAR_INSTALL -o /tmp/idara-install-out.html -w "  install: HTTP %{http_code}\n" \
  -d "csrf=$T" -d "driver=sqlite" -d "site_name=إدارة" \
  -d "admin_name=مسؤول النظام" -d "admin_username=admin" -d "admin_email=admin@idara.local" \
  -d "admin_password=Admin@1234" -d "timezone=Asia/Riyadh" "$BASE/install.php"
grep -o 'Database error[^<]*' /tmp/idara-install-out.html | head -2
php seed.php > /dev/null && echo "  seed: OK"

echo "== 1. Manager (sarah.qahtani) — dashboard =="
JAR=/tmp/idara-sarah.jar; login sarah.qahtani 'Manager@1234' $JAR
CODE=$(curl -s -b $JAR -o /tmp/idara-dash.html -w "%{http_code}" "$BASE/index.php?p=dashboard")
check "dashboard HTTP 200" "200" "$CODE"
D=$(cat /tmp/idara-dash.html)
check "manager dashboard title (from lang/ar.php)" "$(val dash.title_manager)" "$D"
N=$(statval /tmp/idara-dash.html "$(val dash.awaiting_me)")
check "awaiting-my-approval stat is non-zero" "^[1-9][0-9]*$" "$N"

echo "== 2. Manager — approvals inbox =="
CODE=$(curl -s -b $JAR -o /tmp/idara-inbox.html -w "%{http_code}" "$BASE/index.php?p=approvals&inbox=1")
check "approvals inbox HTTP 200" "200" "$CODE"
INBOX=$(cat /tmp/idara-inbox.html)
check "inbox tab label" "$(val approvals.inbox)" "$INBOX"
check "inbox lists a pending request" "اعتماد خطاب رسمي إلى وزارة المالية" "$INBOX"
check "inbox rows link to approval pages" "p=approval&id=" "$INBOX"

echo "== 3. Manager — tasks list + filter =="
CODE=$(curl -s -b $JAR -o /tmp/idara-tasks.html -w "%{http_code}" "$BASE/index.php?p=tasks")
check "tasks list HTTP 200" "200" "$CODE"
TL=$(cat /tmp/idara-tasks.html)
check "tasks list shows a seeded task" "مراجعة طلبات الترخيص المتأخرة" "$TL"
TF=$(curl -s -b $JAR -G --data-urlencode "q=طلبات الترخيص" "$BASE/index.php?p=tasks")
check "task filter narrows to one result" "$(val tasks.count_one)" "$TF"
check "filtered row is the matching task" "مراجعة طلبات الترخيص المتأخرة" "$TF"
checknot "unfiltered list is not the one-result view" "$(val tasks.count_one)" "$TL"

echo "== 4. Manager — create task, checklist, follow-up, status =="
N=$(curl -s -b $JAR "$BASE/index.php?p=new-task")
T=$(echo "$N" | csrf)
RN=$(curl -s -b $JAR -o /dev/null -w "%{redirect_url}" \
  --data-urlencode "csrf=$T" --data-urlencode "title=مهمة فحص الدخان" \
  --data-urlencode "description=وصف تجريبي لفحص الدخان" \
  -d "priority=high&assignee_id=&department_id=&start_date=&due_date=" "$BASE/index.php?p=new-task")
check "new task redirects to its page" "p=task&id=" "$RN"
TID=$(echo "$RN" | grep -o 'id=[0-9]*' | tail -1 | cut -d= -f2)
TV=$(curl -s -b $JAR "$BASE/index.php?p=task&id=$TID")
check "task page shows the title" "مهمة فحص الدخان" "$TV"
check "new task status is new" "badge-task badge-task-new" "$TV"
check "new task progress is 0%" 'progress-num">0%' "$TV"

curl -s -b $JAR -o /dev/null -d "csrf=$T&action=add_item&item_title=الخطوة الأولى" "$BASE/index.php?p=task&id=$TID"
curl -s -b $JAR -o /dev/null -d "csrf=$T&action=add_item&item_title=الخطوة الثانية" "$BASE/index.php?p=task&id=$TID"
TV1=$(curl -s -b $JAR "$BASE/index.php?p=task&id=$TID")
check "checklist item added (1)" "الخطوة الأولى" "$TV1"
check "checklist item added (2)" "الخطوة الثانية" "$TV1"
check "progress unchanged before toggle" 'progress-num">0%' "$TV1"
IID=$(echo "$TV1" | grep -o 'name="item_id" value="[0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b $JAR -o /dev/null -d "csrf=$T&action=toggle_item&item_id=$IID" "$BASE/index.php?p=task&id=$TID"
TV2=$(curl -s -b $JAR "$BASE/index.php?p=task&id=$TID")
check "toggling a checklist item changes progress" 'progress-num">50%' "$TV2"

curl -s -b $JAR -o /dev/null --data-urlencode "csrf=$T" --data-urlencode "action=add_update" \
  --data-urlencode "body=متابعة فحص الدخان" "$BASE/index.php?p=task&id=$TID"
TV3=$(curl -s -b $JAR "$BASE/index.php?p=task&id=$TID")
check "follow-up appears on the task" "متابعة فحص الدخان" "$TV3"

curl -s -b $JAR -o /dev/null -d "csrf=$T&action=status&status=completed" "$BASE/index.php?p=task&id=$TID"
TV4=$(curl -s -b $JAR "$BASE/index.php?p=task&id=$TID")
check "task status → completed badge" "badge-task badge-task-completed" "$TV4"
check "completed task progress is 100%" 'progress-num">100%' "$TV4"

echo "== 5. Manager — decide an approval =="
AID=""
for id in $(echo "$INBOX" | grep -o 'p=approval&id=[0-9]*' | grep -o '[0-9]*$' | sort -un); do
  AP=$(curl -s -b $JAR "$BASE/index.php?p=approval&id=$id")
  if [ "$(echo "$AP" | grep -c 'class="chain-step')" -ge 2 ]; then AID=$id; break; fi
done
check "found a multi-step approval in the inbox" "^[0-9][0-9]*$" "$AID"
AV=$(curl -s -b $JAR "$BASE/index.php?p=approval&id=$AID")
check "decision button: approve" 'name="decision" value="approved"' "$AV"
check "decision button: reject" 'name="decision" value="rejected"' "$AV"
check "decision button: return" 'name="decision" value="returned"' "$AV"
check "approval shows 'you decide' badge" "$(val approvals.you_decide)" "$AV"

T2=$(echo "$AV" | csrf)
RAV=$(curl -s -L -b $JAR --data-urlencode "csrf=$T2" --data-urlencode "action=decide" \
  -d "decision=approved" --data-urlencode "note=موافقة فحص الدخان" "$BASE/index.php?p=approval&id=$AID")
check "approve shows the step-message (chain continues)" "$(val approvals.step_approved_msg)" "$RAV"
check "approve: her step becomes approved" "badge-step badge-step-approved" "$RAV"
check "approve: the next step becomes pending" "badge-step badge-step-pending" "$RAV"
check "approve: approval itself stays pending" "badge-appr badge-appr-pending" "$RAV"
checknot "approve: decision buttons are gone for her" 'name="decision" value="approved"' "$RAV"

echo "== 6. Manager — rejection with an empty note is refused =="
RQID=""
for id in $(echo "$INBOX" | grep -o 'p=approval&id=[0-9]*' | grep -o '[0-9]*$' | sort -un); do
  if [ "$id" != "$AID" ]; then RQID=$id; break; fi
done
RV=$(curl -s -b $JAR "$BASE/index.php?p=approval&id=$RQID")
T3=$(echo "$RV" | csrf)
RRV=$(curl -s -L -b $JAR -d "csrf=$T3&action=decide&decision=rejected&note=" "$BASE/index.php?p=approval&id=$RQID")
check "reject with empty note shows the error" "$(val approvals.err_note)" "$RRV"
check "reject with empty note keeps it pending" "badge-appr badge-appr-pending" "$RRV"

echo "== 7. Manager — new 2-step approval request =="
NP=$(curl -s -b $JAR "$BASE/index.php?p=new-approval")
T=$(echo "$NP" | csrf)
RNP=$(curl -s -b $JAR -o /dev/null -w "%{redirect_url}" \
  --data-urlencode "csrf=$T" --data-urlencode "title=اعتماد فحص الدخان" \
  --data-urlencode "description=طلب اعتماد تجريبي" \
  --data-urlencode "type_id=1" -d "priority=medium&due_date=&related_task_id=" \
  --data-urlencode "approver_ids[]=2" --data-urlencode "approver_ids[]=4" --data-urlencode "approver_ids[]=" \
  "$BASE/index.php?p=new-approval")
check "new approval redirects to its page" "p=approval&id=" "$RNP"
NAID=$(echo "$RNP" | grep -o 'id=[0-9]*' | tail -1 | cut -d= -f2)
NAV=$(curl -s -b $JAR "$BASE/index.php?p=approval&id=$NAID")
check "2-step approval: title" "اعتماد فحص الدخان" "$NAV"
check "2-step approval: current step 1 of 2" "$(valt approvals.step_of a 1 b 2)" "$NAV"
check "2-step approval: first step pending" "badge-step badge-step-pending" "$NAV"
check "2-step approval: second step waiting" "badge-step badge-step-waiting" "$NAV"

echo "== 8. Manager — incoming letter =="
NL=$(curl -s -b $JAR "$BASE/index.php?p=new-letter")
T=$(echo "$NL" | csrf)
# Assign the letter to the manager herself so she may edit/advance it.
SID=$(echo "$NL" | grep -o '<option value="[0-9]*"[^>]*>سارة القحطاني' | grep -o '[0-9]*' | head -1)
SID=${SID:-3}
RL=$(curl -s -b $JAR -o /dev/null -w "%{redirect_url}" \
  --data-urlencode "csrf=$T" --data-urlencode "direction=incoming" \
  --data-urlencode "subject=خطاب فحص الدخان" --data-urlencode "summary=ملخص تجريبي" \
  --data-urlencode "party=جهة فحص الدخان" -d "reference_no=SMOKE-1&priority=high" \
  -d "assignee_id=$SID&department_id=&received_at=&due_date=" "$BASE/index.php?p=new-letter")
check "new letter redirects to its page" "p=letter&id=" "$RL"
LID=$(echo "$RL" | grep -o 'id=[0-9]*' | tail -1 | cut -d= -f2)
LV=$(curl -s -b $JAR "$BASE/index.php?p=letter&id=$LID")
check "letter page shows the subject" "خطاب فحص الدخان" "$LV"
check "new letter status is new" "badge-corr badge-corr-new" "$LV"
T=$(echo "$LV" | csrf)
curl -s -b $JAR -o /dev/null -d "csrf=$T&action=status&status=under_review" "$BASE/index.php?p=letter&id=$LID"
LV2=$(curl -s -b $JAR "$BASE/index.php?p=letter&id=$LID")
check "letter status change works" "badge-corr badge-corr-under_review" "$LV2"

echo "== 9. Manager — meeting =="
MT=$(curl -s -b $JAR "$BASE/index.php?p=meetings")
T=$(echo "$MT" | csrf)
STARTS=$(php -r 'echo date("Y-m-d\TH:i", strtotime("+3 days 10:00"));')
ENDS=$(php -r 'echo date("Y-m-d\TH:i", strtotime("+3 days 11:00"));')
RM=$(curl -s -b $JAR -o /dev/null -w "%{redirect_url}" \
  --data-urlencode "csrf=$T" --data-urlencode "title=اجتماع فحص الدخان" \
  --data-urlencode "agenda=جدول أعمال تجريبي" --data-urlencode "location=قاعة الاختبار" \
  -d "starts_at=$STARTS&ends_at=$ENDS" -d "attendees[]=3&attendees[]=6&attendees[]=7" \
  "$BASE/index.php?p=meetings")
check "new meeting redirects to its page" "p=meeting&id=" "$RM"
MID=$(echo "$RM" | grep -o 'id=[0-9]*' | tail -1 | cut -d= -f2)
MV=$(curl -s -b $JAR "$BASE/index.php?p=meeting&id=$MID")
check "meeting page shows the title" "اجتماع فحص الدخان" "$MV"
check "meeting page shows the attendee count" "$(valt meetings.attendees_count n 3)" "$MV"

echo "== 10. Manager — delegation =="
DG=$(curl -s -b $JAR "$BASE/index.php?p=delegations")
T=$(echo "$DG" | csrf)
KID=$(echo "$DG" | grep -o '<option value="[0-9]*">خالد الغامدي' | grep -o '[0-9]*' | head -1)
KID=${KID:-9}
TODAY=$(php -r 'echo date("Y-m-d");')
WEEK=$(php -r 'echo date("Y-m-d", strtotime("+7 days"));')
RD=$(curl -s -L -b $JAR --data-urlencode "csrf=$T" -d "action=create&delegator_id=3" \
  -d "delegate_id=$KID&starts_at=$TODAY&ends_at=$WEEK" \
  --data-urlencode "reason=تفويض فحص الدخان" "$BASE/index.php?p=delegations")
check "delegation creation is confirmed" "$(val deleg.created_ok)" "$RD"
check "delegation appears in the list" "تفويض فحص الدخان" "$RD"

echo "== 11. Manager — remaining workspace pages =="
for page in calendar meetings team correspondence notifications profile doc; do
  CODE=$(curl -s -b $JAR -o /dev/null -w "%{http_code}" "$BASE/index.php?p=$page")
  check "$page HTTP 200" "200" "$CODE"
done

echo "== 12. Employee + active delegate (noura.shammari) =="
JN=/tmp/idara-noura.jar; login noura.shammari 'User@1234' $JN
NI=$(curl -s -b $JN "$BASE/index.php?p=approvals&inbox=1")
check "delegate inbox lists the delegator's approvals" "p=approval&id=" "$NI"
check "delegate inbox shows a delegated request" "اعتماد خطاب رسمي إلى وزارة المالية" "$NI"
NDID=$(echo "$NI" | grep -o 'p=approval&id=[0-9]*' | grep -o '[0-9]*$' | head -1)
NV=$(curl -s -b $JN "$BASE/index.php?p=approval&id=$NDID")
check "delegate sees the delegation note" "$(val approvals.delegated_note)" "$NV"
check "delegate gets the decision buttons" 'name="decision" value="approved"' "$NV"

echo "== 13. Plain employee (fahad.dosari) — nothing to approve =="
JF=/tmp/idara-fahad.jar; login fahad.dosari 'User@1234' $JF
FL=$(curl -s -b $JF "$BASE/index.php?p=approvals")
check "employee approvals list is empty" "$(val approvals.empty)" "$FL"
checknot "employee list leaks no pending request" "اعتماد خطاب رسمي إلى وزارة المالية" "$FL"
FI=$(curl -s -b $JF "$BASE/index.php?p=approvals&inbox=1")
check "employee inbox is empty" "$(val approvals.empty)" "$FI"

echo "== 14. Admin pages are 403 for the manager =="
for page in admin/users admin/departments admin/categories admin/types admin/settings admin/audit admin/reports; do
  CODE=$(curl -s -b $JAR -o /dev/null -w "%{http_code}" "$BASE/index.php?p=$page")
  check "manager → $page is 403" "403" "$CODE"
done

echo "== 15. Admin (admin) — all admin pages + exports + audit =="
JA=/tmp/idara-admin.jar; login admin 'Admin@1234' $JA
for page in admin/users admin/departments admin/categories admin/types admin/settings admin/audit admin/reports; do
  CODE=$(curl -s -b $JA -o /dev/null -w "%{http_code}" "$BASE/index.php?p=$page")
  check "admin → $page is 200" "200" "$CODE"
done
CSV=$(curl -s -b $JA "$BASE/index.php?p=admin/reports&export=csv")
check "reports CSV export contains a task reference" "TSK-" "$CSV"
AUDIT=$(curl -s -b $JA "$BASE/index.php?p=admin/audit")
check "audit log records the login" "login" "$AUDIT"
check "audit log records the created task" "task created" "$AUDIT"

echo "== 16. Security negatives =="
C=$(curl -s -b $JAR -o /dev/null -w "%{http_code}" -d "csrf=WRONG&action=add_update&body=x" "$BASE/index.php?p=task&id=$TID")
check "POST with a wrong CSRF token → 403" "403" "$C"

echo "== 17. v1.1 fixes — model-level regressions =="

# B2 — the overdue inbox counter (parameter order was broken → always 0)
# Seed a deterministic overdue approval addressed to the manager so the check
# is independent of what earlier sections decided.
php -r 'define("APP_ROOT", __DIR__); require "includes/db.php"; Database::init((require "data/config.php")["db"]);
$id = Database::insert("INSERT INTO approvals (title, description, type_id, requester_id, department_id, priority, status, current_step, due_date, related_task_id, created_at, updated_at) VALUES (?, ?, NULL, ?, NULL, ?, \"pending\", 1, ?, NULL, ?, ?)", ["فحص الاعتماد المتأخر", "x", 6, "high", date("Y-m-d", strtotime("-2 days")), date("Y-m-d H:i:s"), date("Y-m-d H:i:s")]);
Database::exec("UPDATE approvals SET ref = ? WHERE id = ?", ["APR-TEST-OVERDUE", $id]);
Database::exec("INSERT INTO approval_steps (approval_id, step_order, approver_id, status, note, created_at) VALUES (?, 1, 3, \"pending\", \"\", ?)", [$id, date("Y-m-d H:i:s")]);'
OV=$(php -r 'define("APP_ROOT", __DIR__); require "includes/db.php"; Database::init((require "data/config.php")["db"]); require "includes/i18n.php"; require "includes/helpers.php"; require "includes/models.php"; $_SESSION=["uid"=>0]; $me=Database::one("SELECT * FROM users WHERE username=?", ["sarah.qahtani"]); echo Approvals::overdueInboxCount($me);')
check "B2 overdueInboxCount >= 1 (parameter order fixed)" "^[1-9][0-9]*$" "$OV"

# B5 / B9 — static style + security checks
check "B5 role-badge CSS for executive exists" "badge-role-executive" "$(cat assets/css/style.css)"
check "B5 role-badge CSS for manager exists" "badge-role-manager" "$(cat assets/css/style.css)"
check "B9 .htaccess denies diag.php" "diag" "$(cat .htaccess)"
check "B9 .htaccess denies integrity.php" "integrity" "$(cat .htaccess)"
check "B9 .htaccess unsets X-Powered-By" "X-Powered-By" "$(cat .htaccess)"

echo "== 18. B1 — requester withdraws a pending approval =="
AP=$(curl -s -b $JN "$BASE/index.php?p=new-approval")
T=$(echo "$AP" | csrf)
APP1=$(curl -s -b $JN --data-urlencode "csrf=$T" --data-urlencode "title=طلب فحص السحب" --data-urlencode "description=وصف فحص السحب" -d "type_id=1" -d "priority=medium" -d "due_date=" -d "approver_ids[]=3" -o /dev/null -w "%{redirect_url}" "$BASE/index.php?p=new-approval")
WID=$(echo "$APP1" | grep -o '[0-9]*$'); WID=${WID:-0}
WV=$(curl -s -b $JN "$BASE/index.php?p=approval&id=$WID")
T=$(echo "$WV" | csrf)
WR=$(curl -s -L -b $JN --data-urlencode "csrf=$T" -d "action=withdraw" "$BASE/index.php?p=approval&id=$WID")
check "B1 withdrawal is confirmed" "$(val approvals.withdrawn_ok)" "$WR"
check "B1 withdrawn request shows the cancelled status" "$(val astatus.cancelled)" "$WR"
check "B1 withdrawal marks the step as skipped" "$(val sstatus.skipped)" "$WR"

echo "== 19. B7 — requester edits a pending approval (chain intact) =="
AP=$(curl -s -b $JN "$BASE/index.php?p=new-approval")
T=$(echo "$AP" | csrf)
EID=$(curl -s -b $JN --data-urlencode "csrf=$T" --data-urlencode "title=طلب فحص التعديل" --data-urlencode "description=قبل التعديل" -d "type_id=1" -d "priority=high" -d "due_date=" -d "approver_ids[]=3" -d "approver_ids[]=2" -o /dev/null -w "%{redirect_url}" "$BASE/index.php?p=new-approval" | grep -o '[0-9]*$')
EV=$(curl -s -b $JN "$BASE/index.php?p=approval&id=$EID")
T=$(echo "$EV" | csrf)
ER=$(curl -s -L -b $JN --data-urlencode "csrf=$T" -d "action=edit" --data-urlencode "title=طلب فحص التعديل — بعد" --data-urlencode "description=بعد التعديل" -d "type_id=" -d "priority=high" -d "due_date=" -d "approver_ids[]=3" -d "approver_ids[]=2" "$BASE/index.php?p=approval&id=$EID")
check "B7 approval edit is confirmed" "$(val approvals.edit_ok)" "$ER"
check "B7 edited title is shown" "طلب فحص التعديل — بعد" "$ER"
STEPS=$(php -r 'define("APP_ROOT", __DIR__); require "includes/db.php"; Database::init((require "data/config.php")["db"]); echo (int) Database::value("SELECT COUNT(*) FROM approval_steps WHERE approval_id = ?", [(int) $argv[1]]);' "$EID")
check "B7 chain intact after a text-only edit (2 steps)" "^2$" "$STEPS"

echo "== 20. B3 — letter notification links to the letter page =="
LV=$(curl -s -b $JAR "$BASE/index.php?p=new-letter")
T=$(echo "$LV" | csrf)
LID=$(curl -s -b $JAR --data-urlencode "csrf=$T" -d "direction=incoming" --data-urlencode "subject=خطاب فحص الإشعار" --data-urlencode "summary=فحص" --data-urlencode "party=جهة فحص" -d "reference_no=TEST-B3" -d "priority=medium" -d "assignee_id=6" -d "department_id=" -d "received_at=" -d "due_date=" -o /dev/null -w "%{redirect_url}" "$BASE/index.php?p=new-letter" | grep -o '[0-9]*$')
LNK=$(php -r 'define("APP_ROOT", __DIR__); require "includes/db.php"; Database::init((require "data/config.php")["db"]); echo (string) Database::value("SELECT link FROM notifications WHERE link LIKE ? ORDER BY id DESC LIMIT 1", ["%letter&id=" . (int) $argv[1]]);' "$LID")
check "B3 notification link points at the letter page" "letter&id=$LID" "$LNK"

echo "== 21. B4 — reopening a completed task drops the 100 % =="
N2=$(curl -s -b $JAR "$BASE/index.php?p=new-task")
T=$(echo "$N2" | csrf)
RID=$(curl -s -b $JAR --data-urlencode "csrf=$T" --data-urlencode "title=مهمة فحص الإعادة" --data-urlencode "description=x" -d "category_id=" -d "priority=medium" -d "assignee_id=3" -d "department_id=" -d "start_date=" -d "due_date=" -o /dev/null -w "%{redirect_url}" "$BASE/index.php?p=new-task" | grep -o '[0-9]*$')
PV=$(curl -s -b $JAR "$BASE/index.php?p=task&id=$RID")
T=$(echo "$PV" | csrf)
curl -s -b $JAR --data-urlencode "csrf=$T" -d "action=status" -d "status=completed" -o /dev/null "$BASE/index.php?p=task&id=$RID"
PV=$(curl -s -b $JAR "$BASE/index.php?p=task&id=$RID")
T=$(echo "$PV" | csrf)
RP=$(curl -s -L -b $JAR --data-urlencode "csrf=$T" -d "action=status" -d "status=in_progress" "$BASE/index.php?p=task&id=$RID")
check "B4 reopened task shows the in-progress badge" "$(val tstatus.in_progress)" "$RP"
# the progress bar must not read 100% after reopening (assert the bar value, not any "100%" on the page)
checknot "B4 reopened progress bar is no longer 100%" 'progress-num">100%' "$RP"
check "B4 reopened progress bar shows a value below 100%" 'progress-num">' "$RP"

echo "== 22. B7 — organizer edits a meeting =="
MV=$(curl -s -b $JAR "$BASE/index.php?p=meetings")
T=$(echo "$MV" | csrf)
STARTS=$(php -r 'echo date("Y-m-d\TH:i", strtotime("+5 days 09:00"));')
MID=$(curl -s -b $JAR --data-urlencode "csrf=$T" --data-urlencode "title=اجتماع فحص التعديل" -d "agenda=" -d "location=قاعة فحص" -d "starts_at=$STARTS" -d "ends_at=" -d "attendees[]=6" -o /dev/null -w "%{redirect_url}" "$BASE/index.php?p=meetings" | grep -o '[0-9]*$')
MV2=$(curl -s -b $JAR "$BASE/index.php?p=meeting&id=$MID")
T=$(echo "$MV2" | csrf)
MR=$(curl -s -L -b $JAR --data-urlencode "csrf=$T" -d "action=edit" --data-urlencode "title=اجتماع فحص التعديل — بعد" --data-urlencode "agenda=جدول معدل" --data-urlencode "location=قاعة فحص" -d "starts_at=$STARTS" -d "ends_at=" "$BASE/index.php?p=meeting&id=$MID")
check "B7 meeting edit is confirmed" "$(val meetings.edit_ok)" "$MR"
check "B7 edited meeting title is shown" "اجتماع فحص التعديل — بعد" "$MR"

echo "== 23. B6 — a cancelled delegation shows the cancelled label =="
DG=$(curl -s -b $JAR "$BASE/index.php?p=delegations")
T=$(echo "$DG" | csrf)
TODAY2=$(php -r 'echo date("Y-m-d");')
WEEK2=$(php -r 'echo date("Y-m-d", strtotime("+7 days"));')
curl -s -b $JAR --data-urlencode "csrf=$T" -d "action=create" -d "delegator_id=3" -d "delegate_id=9" -d "starts_at=$TODAY2" -d "ends_at=$WEEK2" --data-urlencode "reason=تفويض فحص الإلغاء" -o /dev/null "$BASE/index.php?p=delegations"
DLID=$(php -r 'define("APP_ROOT", __DIR__); require "includes/db.php"; Database::init((require "data/config.php")["db"]); echo (int) Database::value("SELECT id FROM delegations WHERE reason LIKE ? ORDER BY id DESC LIMIT 1", ["%فحص الإلغاء%"]);')
DG2=$(curl -s -b $JAR "$BASE/index.php?p=delegations")
T=$(echo "$DG2" | csrf)
CR=$(curl -s -L -b $JAR --data-urlencode "csrf=$T" -d "action=cancel" -d "id=$DLID" "$BASE/index.php?p=delegations")
check "B6 cancellation is confirmed" "$(val deleg.cancelled_ok)" "$CR"
check "B6 cancelled delegation shows the cancelled label" "$(val deleg.cancelled)" "$CR"

echo "== 24. B8 — meeting status change is written with detail in the audit log =="
MV3=$(curl -s -b $JAR "$BASE/index.php?p=meeting&id=$MID")
T=$(echo "$MV3" | csrf)
curl -s -b $JAR --data-urlencode "csrf=$T" -d "action=status" -d "status=done" -o /dev/null "$BASE/index.php?p=meeting&id=$MID"
AU=$(php -r 'define("APP_ROOT", __DIR__); require "includes/db.php"; Database::init((require "data/config.php")["db"]); echo (int) Database::value("SELECT COUNT(*) FROM audit_log WHERE action = ? AND details LIKE ?", ["meeting_updated", "status=done%"]);')
check "B8 audit row carries status=done detail" "^[1-9][0-9]*$" "$AU"

echo "== 25. B10 — random reset password forces a change at next login =="
RU=$(curl -s -b $JA "$BASE/index.php?p=admin/users")
T=$(echo "$RU" | csrf)
curl -s -b $JA --data-urlencode "csrf=$T" -d "action=reset_password" -d "id=6" -o /dev/null "$BASE/index.php?p=admin/users"
RF=$(curl -s -b $JA "$BASE/index.php?p=admin/users")
TEMP=$(echo "$RF" | grep -o 'المؤقتة: [0-9a-f]\{8\}' | grep -o '[0-9a-f]\{8\}' | tail -1)
check "B10 reset flash contains an 8-hex temporary password" "^[0-9a-f]\{8\}$" "$TEMP"
JNT=/tmp/idara-noura-temp.jar; rm -f $JNT
TP=$(curl -s -L -c $JNT "$BASE/index.php?p=login")
TT=$(echo "$TP" | csrf)
curl -s -b $JNT -c $JNT -o /dev/null -d "csrf=$TT&login=noura.shammari&password=$TEMP" "$BASE/index.php?p=login"
GATE=$(curl -s -b $JNT -L "$BASE/index.php?p=dashboard")
check "B10 login with the temp password → forced to the profile page" "$(val profile.change_password)" "$GATE"
PT=$(echo "$GATE" | csrf)
curl -s -b $JNT --data-urlencode "csrf=$PT" -d "current_password=$TEMP" -d "new_password=Noura@2026" -d "confirm_password=Noura@2026" -o /dev/null "$BASE/index.php?p=profile"
FREE=$(curl -s -b $JNT -L "$BASE/index.php?p=dashboard")
check "B10 after changing the password → dashboard is reachable" "$(val dash.awaiting_me)" "$FREE"

echo "== 26. B11/B12/B13 — admin wording, CSV link, attendee hint =="
CU=$(curl -s -b $JA "$BASE/index.php?p=admin/categories")
T=$(echo "$CU" | csrf)
TC=$(curl -s -L -b $JA --data-urlencode "csrf=$T" -d "action=toggle" -d "id=1" "$BASE/index.php?p=admin/categories")
check "B11 category toggle uses the status wording" "$(val ac.toggled)" "$TC"
curl -s -b $JA -o /dev/null --data-urlencode "csrf=$T" -d "action=toggle" -d "id=1" "$BASE/index.php?p=admin/categories"
RP=$(curl -s -b $JA "$BASE/index.php?p=admin/reports")
checknot "B12 no doubled && in the CSV link" "reports&&export=csv" "$RP"
check "B12 CSV export link is present" "export=csv" "$RP"
MF=$(curl -s -b $JAR "$BASE/index.php?p=meetings")
check "B13 attendee hint is shown" "$(val meetings.hint_attendees)" "$MF"

echo "== 27. v1.2 fixes — live-test notes =="

# static: the 20 built-in avatars ship
AV=$(ls assets/avatars/*.webp 2>/dev/null | wc -l | tr -d ' ')
check "32 built-in avatar photos ship" "^32$" "$AV"

# item 9: a requester cannot approve their own request
AP=$(curl -s -b $JN "$BASE/index.php?p=new-approval")
T=$(echo "$AP" | csrf)
SELF=$(curl -s -L -b $JN --data-urlencode "csrf=$T" --data-urlencode "title=طلب ذاتي" -d "type_id=1" -d "priority=low" -d "due_date=" -d "approver_ids[]=6" "$BASE/index.php?p=new-approval")
check "item 9 self-approver is refused" "$(val approvals.err_self)" "$SELF"

# item 6: duplicate department name/code is refused
DU=$(curl -s -b $JA "$BASE/index.php?p=admin/departments")
T=$(echo "$DU" | csrf)
DD=$(curl -s -L -b $JA --data-urlencode "csrf=$T" -d "action=add" --data-urlencode "name_ar=الإدارة العامة" -d "name_en=Head Office" -d "code=HQ" -d "manager_id=" "$BASE/index.php?p=admin/departments")
check "item 6 duplicate department is refused" "$(val ad.err_dup)" "$DD"

# item 1: return-for-revision then resubmit
FIRST=$(curl -s -b $JAR "$BASE/index.php?p=approvals&inbox=1" | grep -o 'p=approval&id=[0-9]*' | grep -o '[0-9]*' | head -1)
RV=$(curl -s -b $JAR "$BASE/index.php?p=approval&id=$FIRST")
T=$(echo "$RV" | csrf)
curl -s -L -b $JAR --data-urlencode "csrf=$T" -d "action=decide" -d "decision=returned" --data-urlencode "note=عدّل التفاصيل وأعد الإرسال" "$BASE/index.php?p=approval&id=$FIRST" -o /tmp/idara-ret.html
check "item 1 return decision is confirmed" "$(val approvals.returned_msg)" "$(cat /tmp/idara-ret.html)"
NV=$(curl -s -b $JN "$BASE/index.php?p=approval&id=$FIRST")
check "item 1 returned request shows the resubmit hint" "$(val approvals.resubmit_hint)" "$NV"
T=$(echo "$NV" | csrf)
RR=$(curl -s -L -b $JN --data-urlencode "csrf=$T" -d "action=edit" --data-urlencode "title=اعتماد إصدار ترخيص استثماري — بعد التعديل" -d "type_id=1" -d "priority=high" -d "due_date=" -d "approver_ids[]=3" -d "approver_ids[]=2" "$BASE/index.php?p=approval&id=$FIRST")
check "item 1 resubmission is confirmed" "$(val approvals.resubmitted_ok)" "$RR"
PEN=$(php -r 'define("APP_ROOT", __DIR__); require "includes/db.php"; Database::init((require "data/config.php")["db"]); echo Database::value("SELECT status FROM approvals WHERE id = ?", [(int) $argv[1]]);' "$FIRST")
check "item 1 resubmitted request is pending again" "^pending$" "$PEN"

# item 2: a future delegation shows "Scheduled"
DG=$(curl -s -b $JAR "$BASE/index.php?p=delegations")
T=$(echo "$DG" | csrf)
FUT1=$(php -r 'echo date("Y-m-d", strtotime("+5 days"));')
FUT2=$(php -r 'echo date("Y-m-d", strtotime("+10 days"));')
curl -s -b $JAR --data-urlencode "csrf=$T" -d "action=create" -d "delegator_id=3" -d "delegate_id=9" -d "starts_at=$FUT1" -d "ends_at=$FUT2" --data-urlencode "reason=تفويض مستقبلي" -o /dev/null "$BASE/index.php?p=delegations"
SCH=$(curl -s -b $JAR "$BASE/index.php?p=delegations")
check "item 2 future delegation shows Scheduled" "$(val deleg.scheduled)" "$SCH"

# item 4: letters appear on the calendar
CAL=$(curl -s -b $JAR "$BASE/index.php?p=calendar")
check "item 4 calendar shows letters" "cal-letter" "$CAL"
check "item 4 calendar legend mentions letters" "$(val cal.letters)" "$CAL"

# item 10: dashboard numbers are links
DASH=$(curl -s -b $JAR "$BASE/index.php?p=dashboard")
check "item 10 inbox card links to the inbox" "p=approvals&inbox=1" "$DASH"
check "item 10 my-tasks card links to my tasks" "p=tasks&mine=1" "$DASH"

# item 11: tasks page has My / Team tabs
TS=$(curl -s -b $JAR "$BASE/index.php?p=tasks")
check "item 11 tasks page shows My-tasks tab" "$(val tasks.my_tab)" "$TS"
check "item 11 tasks page shows Team-tasks tab" "$(val tasks.team_tab)" "$TS"

# item 14: "1 participant" plural + item 15: attendance "Not set"
MV=$(curl -s -b $JAR "$BASE/index.php?p=meetings")
T=$(echo "$MV" | csrf)
ST=$(php -r 'echo date("Y-m-d\TH:i", strtotime("+6 days 09:00"));')
MID1=$(curl -s -b $JAR --data-urlencode "csrf=$T" --data-urlencode "title=اجتماع منفرد" -d "agenda=" -d "location=قاعة" -d "starts_at=$ST" -d "ends_at=" -d "attendees[]=9" -o /dev/null -w "%{redirect_url}" "$BASE/index.php?p=meetings" | grep -o '[0-9]*$')
MVAL=$(curl -s -b $JAR "$BASE/index.php?p=meeting&id=$MID1")
check "item 14 single attendee shows 1 participant" "$(val meetings.attendees_one)" "$MVAL"
check "item 15 future meeting shows Not set" "$(val meetings.not_set)" "$MVAL"

# item 16: styled 404 on a detail page
N4=$(curl -s -b $JAR "$BASE/index.php?p=task&id=999999")
check "item 16 404 page has the full layout + back button" "$(val e404.back)" "$N4"

# item 17: audit log shows the user name for user entities
AUD=$(curl -s -b $JA "$BASE/index.php?p=admin/audit")
check "item 17 audit shows the target user name" "نورة الشمري" "$AUD"

# user deletion: fresh user is deletable, a user with history is not
PU=$(curl -s -b $JA "$BASE/index.php?p=admin/users")
T=$(echo "$PU" | csrf)
curl -s -b $JA --data-urlencode "csrf=$T" -d "action=add" --data-urlencode "name=مستخدم حذف" -d "name_en=Delete Test" -d "username=zz.delete.test" -d "email=zz.delete.test@idara.local" -d "role=member" -d "department_id=" -d "job_title=" -d "phone=" -d "manager_id=" -d "password=Delete@123" -o /dev/null "$BASE/index.php?p=admin/users"
DELID=$(php -r 'define("APP_ROOT", __DIR__); require "includes/db.php"; Database::init((require "data/config.php")["db"]); echo (int) Database::value("SELECT id FROM users WHERE username = ?", ["zz.delete.test"]);')
PU2=$(curl -s -b $JA "$BASE/index.php?p=admin/users")
T=$(echo "$PU2" | csrf)
DR=$(curl -s -L -b $JA --data-urlencode "csrf=$T" -d "action=delete" -d "id=$DELID" "$BASE/index.php?p=admin/users")
check "a fresh user can be deleted" "$(val au.deleted_ok)" "$DR"
GONE=$(php -r 'define("APP_ROOT", __DIR__); require "includes/db.php"; Database::init((require "data/config.php")["db"]); echo (int) Database::value("SELECT COUNT(*) FROM users WHERE id = ?", [(int) $argv[1]]);' "$DELID")
check "the deleted user is gone" "^0$" "$GONE"
PU3=$(curl -s -b $JA "$BASE/index.php?p=admin/users")
T=$(echo "$PU3" | csrf)
DH=$(curl -s -L -b $JA --data-urlencode "csrf=$T" -d "action=delete" -d "id=3" "$BASE/index.php?p=admin/users")
check "a user with history cannot be deleted" "$(val au.err_has_history)" "$DH"

# profile: avatar picker, bio, birthdate
PR=$(curl -s -b $JAR "$BASE/index.php?p=profile")
T=$(echo "$PR" | csrf)
PF=$(curl -s -L -b $JAR --data-urlencode "csrf=$T" -d "action=profile" -d "avatar=builtin:03.webp" --data-urlencode "bio=نبذة فحص" -d "birthdate=1990-05-20" "$BASE/index.php?p=profile")
check "profile update is confirmed" "$(val profile.profile_updated)" "$PF"
AV2=$(php -r 'define("APP_ROOT", __DIR__); require "includes/db.php"; Database::init((require "data/config.php")["db"]); echo (string) Database::value("SELECT avatar FROM users WHERE id = 3");')
check "profile avatar persisted" "^builtin:03.webp$" "$AV2"
check "profile page shows the chosen avatar" "assets/avatars/03.webp" "$(curl -s -b $JAR "$BASE/index.php?p=profile")"

echo "== 28. v1.4 fixes — photo upload, flashes, audit, field naming =="

# N1: a real PNG upload is stored and served
python3 -c "import base64; open('/tmp/idara-real.png','wb').write(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='))"
python3 -c "open('/tmp/idara-fake.png','w').write('this is not an image')"
PR=$(curl -s -b $JAR "$BASE/index.php?p=profile")
T=$(echo "$PR" | csrf)
UP=$(curl -s -L -b $JAR -F "csrf=$T" -F "action=profile" -F "avatar=builtin:01.webp" -F "photo=@/tmp/idara-real.png;type=image/png" -F "bio=" -F "birthdate=" "$BASE/index.php?p=profile")
check "N1 valid photo upload is confirmed" "$(val profile.profile_updated)" "$UP"
UPV=$(php -r 'define("APP_ROOT", __DIR__); require "includes/db.php"; Database::init((require "data/config.php")["db"]); echo (string) Database::value("SELECT avatar FROM users WHERE id = 3");')
check "N1 uploaded photo is stored (up: prefix)" "^up:" "$UPV"
AVNAME=$(echo "$UPV" | sed 's/^up://')
check "N1 uploaded photo is served as an image" "^image/png$" "$(curl -s -b $JAR -o /dev/null -w '%{content_type}' "$BASE/index.php?p=download&avatar=$AVNAME")"
# N1: a fake image is refused with the right message
T2=$(curl -s -b $JAR "$BASE/index.php?p=profile" | csrf)
FUP=$(curl -s -L -b $JAR -F "csrf=$T2" -F "action=profile" -F "avatar=builtin:01.webp" -F "photo=@/tmp/idara-fake.png;type=image/png" -F "bio=" -F "birthdate=" "$BASE/index.php?p=profile")
check "N1 a non-image file is refused" "$(val profile.err_avatar_invalid)" "$FUP"

# N3: step-1 approve flashes the honest message on a 2-step chain
AP=$(curl -s -b $JN "$BASE/index.php?p=new-approval")
T=$(echo "$AP" | csrf)
SID=$(curl -s -b $JN --data-urlencode "csrf=$T" --data-urlencode "title=فحص رسالة الخطوة" -d "type_id=1" -d "priority=low" -d "due_date=" -d "approver_ids[]=3" -d "approver_ids[]=2" -o /dev/null -w "%{redirect_url}" "$BASE/index.php?p=new-approval" | grep -o '[0-9]*$')
SV=$(curl -s -b $JAR "$BASE/index.php?p=approval&id=$SID")
T=$(echo "$SV" | csrf)
SR=$(curl -s -L -b $JAR --data-urlencode "csrf=$T" -d "action=decide" -d "decision=approved" --data-urlencode "note=خطوة أولى" "$BASE/index.php?p=approval&id=$SID")
check "N3 approver flash says the request moves to the next step" "$(val approvals.step_approved_msg)" "$SR"

# N3b: a 1-step chain still flashes the final approve message
AP2=$(curl -s -b $JN "$BASE/index.php?p=new-approval")
T=$(echo "$AP2" | csrf)
OID=$(curl -s -b $JN --data-urlencode "csrf=$T" --data-urlencode "title=فحص خطوة واحدة" -d "type_id=1" -d "priority=low" -d "due_date=" -d "approver_ids[]=3" -o /dev/null -w "%{redirect_url}" "$BASE/index.php?p=new-approval" | grep -o '[0-9]*$')
OV=$(curl -s -b $JAR "$BASE/index.php?p=approval&id=$OID")
T=$(echo "$OV" | csrf)
OR=$(curl -s -L -b $JAR --data-urlencode "csrf=$T" -d "action=decide" -d "decision=approved" --data-urlencode "note=اعتماد نهائي" "$BASE/index.php?p=approval&id=$OID")
check "N3 1-step approve flashes the final message" "$(val approvals.approved_msg)" "$OR"

# N5: the edit panel uses the same field name as the create form
EV=$(curl -s -b $JN "$BASE/index.php?p=approval&id=$SID")
check "N5 edit panel uses approver_ids[]" 'name="approver_ids' "$EV"

# N4: the audit log keeps the deleted user's name
AUD=$(curl -s -b $JA "$BASE/index.php?p=admin/audit")
check "N4 audit shows the deleted user's name" "مستخدم حذف" "$AUD"
checknot "N4 no raw entity mix-up on the audit page" "user deleted user #" "$AUD"

echo "== 28b. v1.5 fixes — task attachments, upload errors, asset versioning =="
# a task with an update that carries an attachment
T=$(curl -s -b $JAR "$BASE/index.php?p=new-task" | csrf)
curl -s -b $JAR -o /dev/null -d "csrf=$T" -d "title=ATT attachment task" -d "category_id=1" -d "priority=low" \
  -d "assignee_id=1" -d "department_id=1" -d "start_date=2026-10-10" -d "due_date=2026-10-30" "$BASE/index.php?p=new-task"
ATID=$(curl -s -b $JAR "$BASE/index.php?p=tasks" | grep -oE 'task&id=[0-9]+' | head -1 | grep -o '[0-9]*')
printf 'attachment smoke test\n' > /tmp/idara-att.txt
T=$(curl -s -b $JAR "$BASE/index.php?p=task&id=$ATID" | csrf)
RESP=$(curl -s -L -b $JAR -F "csrf=$T" -F "action=add_update" -F "body=Update with attachment" \
  -F "attachments[]=@/tmp/idara-att.txt;type=text/plain" "$BASE/index.php?p=task&id=$ATID")
# the top bar also has a ?p=download&avatar=… link, so match the attachment form (&id=)
ATURL=$(echo "$RESP" | grep -oE 'index.php\?p=download&(amp;)?id=[0-9]+' | head -1)
ATURL=${ATURL//&amp;/&}   # HTML-escaped ampersands must be unescaped before curling
check "attachment upload is stored and linked" "p=download&id=" "$ATURL"
if [ -n "$ATURL" ]; then
  check "attachment downloads as a file" "200" "$(curl -s -b $JAR -o /dev/null -w '%{http_code}' "$BASE/$ATURL")"
  check "attachment content type is text/plain" "text/plain" "$(curl -s -b $JAR -o /dev/null -w '%{content_type}' "$BASE/$ATURL")"
fi
# a blocked extension must produce a visible warning instead of silence
printf 'MZ fake exe\n' > /tmp/idara-bad.exe
T=$(curl -s -b $JAR "$BASE/index.php?p=task&id=$ATID" | csrf)
BAD=$(curl -s -L -b $JAR -F "csrf=$T" -F "action=add_update" -F "body=Update with bad file" \
  -F "attachments[]=@/tmp/idara-bad.exe;type=application/octet-stream" "$BASE/index.php?p=task&id=$ATID")
check "blocked file type shows an error" "$(val common.type_not_allowed)" "$BAD"
# asset cache-busting
LOGINHTML=$(curl -s "$BASE/index.php?p=login")
check "stylesheet is versioned v2" "style.css?v=2" "$LOGINHTML"
check "script is versioned v2" "app.js?v=2" "$LOGINHTML"
# a healthy install shows no storage warning
SETTINGS=$(curl -s -b $JA "$BASE/index.php?p=admin/settings")
check "admin settings page loads for the storage check" "$(val as.general)" "$SETTINGS"
checknot "no storage warning on a healthy install" "$(val common.storage_warning | cut -c1-20)" "$SETTINGS"
# v1.5b: pagination links must not carry an empty parameter ("tasks&&page=2")
# reuse the admin session created earlier in the suite (no extra login → no throttle risk)
# fixture: 25 extra tasks so the list really has a second page (admin sees all departments)
FIXN=$(php -r 'define("APP_ROOT", __DIR__); $c = require "data/config.php"; require "includes/db.php"; Database::init($c["db"]);
for ($i = 1; $i <= 25; $i++) { $id = 9000 + $i;
  Database::exec("INSERT INTO tasks (ref, title, description, category_id, priority, status, progress, creator_id, assignee_id, department_id, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?, 0, 1, 1, 1, ?, ?)",
    ["TSK-2026-" . $id, "PAGE fixture " . $i, "pagination fixture", "medium", "new", "2026-10-10 09:00:00", "2026-10-10 09:00:00"]); }
$r = Database::all("SELECT COUNT(*) c FROM tasks WHERE title LIKE ?", ["PAGE fixture%"]);
echo $r[0]["c"];' 2>/dev/null)
check "pagination fixture inserted (25 rows)" "25" "$FIXN"
PAGEHTML=$(curl -s -b $JA "$BASE/index.php?p=tasks")
checknot "pagination has no empty query parameter" "&&page=" "$PAGEHTML"
check "pagination renders a second page link" "page=2" "$PAGEHTML"
FILTHTML=$(curl -s -b $JA "$BASE/index.php?p=tasks&status=new&page=1")
check "pager keeps the active filter" "status=new&amp;page=2" "$FILTHTML"
check "explicit page 2 renders" "200" "$(curl -s -b $JA -o /dev/null -w '%{http_code}' "$BASE/index.php?p=tasks&page=2")"

# clean up the attachment task
T=$(curl -s -b $JAR "$BASE/index.php?p=task&id=$ATID" | csrf)
curl -s -b $JAR -o /dev/null -d "csrf=$T" -d "action=delete" "$BASE/index.php?p=task&id=$ATID"

echo "== 29. v1.6 branding — version + s0sta.com link =="
DASH=$(curl -s -b $JAR "$BASE/index.php?p=dashboard")
check "footer links to s0sta.com" 'href="https://s0sta.com"' "$DASH"
check "version label shows 1.6" "$(val app.version)" "$DASH"
checknot "old duplicated branding is gone" "prepared by s0sta" "$DASH"

echo "== 30. v1.7 hardening — MFA, policy, print, import, hijri, pagination =="

# TOTP code generator (RFC 6238, SHA-1, 6 digits) — independent of the app
totp() { php -r '
$s=$argv[1]; $b32="ABCDEFGHIJKLMNOPQRSTUVWXYZ234567"; $bin="";
foreach(str_split(strtoupper(preg_replace("/[^A-Z2-7]/","",$s))) as $c){ $bin.=str_pad(decbin(strpos($b32,$c)),5,"0",STR_PAD_LEFT); }
$bytes=""; foreach(str_split($bin,8) as $ch){ if(strlen($ch)<8){$ch=str_pad($ch,8,"0");} $bytes.=chr(bindec($ch)); }
$ct=pack("N*",(int)floor(time()/30)); $h=hash_hmac("sha1",$ct,$bytes,true);
$o=ord($h[19])&0x0F; $v=((ord($h[$o])&0x7F)<<24)|((ord($h[$o+1])&0xFF)<<16)|((ord($h[$o+2])&0xFF)<<8)|(ord($h[$o+3])&0xFF);
echo str_pad((string)($v%1000000),6,"0",STR_PAD_LEFT);' "$1"; }

# password policy: a weak password is refused
PU=$(curl -s -b $JA "$BASE/index.php?p=admin/users")
T=$(echo "$PU" | csrf)
WP=$(curl -s -L -b $JA --data-urlencode "csrf=$T" -d "action=add" --data-urlencode "name=مستخدم ضعيف" -d "name_en=Weak" -d "username=zz.weak.pw" -d "email=zz.weak.pw@idara.local" -d "role=member" -d "department_id=" -d "job_title=" -d "phone=" -d "manager_id=" -d "password=short1" "$BASE/index.php?p=admin/users")
check "password policy rejects a weak password" "$(val profile.err_policy)" "$WP"

# hijri chip on the top bar (Arabic master: contains the AH marker)
DASH=$(curl -s -b $JAR "$BASE/index.php?p=dashboard")
check "top bar shows the Hijri date" "هـ" "$DASH"

# pagination toolbar: per-page + jump on a long list (audit)
AU=$(curl -s -b $JA "$BASE/index.php?p=admin/audit&pp=20")
check "audit toolbar offers the 50-per-page link" "pp=50" "$AU"
check "audit toolbar offers the jump box" "$(val common.go)" "$AU"

# print views
PL=$(curl -s -b $JAR "$BASE/index.php?p=print-letter&id=1")
check "print letter view renders" "$(val print.title_letter)" "$PL"
PM=$(curl -s -b $JAR "$BASE/index.php?p=print-meeting&id=1")
check "print meeting view renders" "$(val print.title_minutes)" "$PM"
check "print view has the print stylesheet" "print.css" "$PL"

# CSV import: 2 valid rows + 1 invalid
python3 - << 'PYEOF'
import io
rows = [
 "title,description,priority,assignee_username,department_code,due_date,start_date",
 "مهمة استيراد ١,وصف,high,sarah.qahtani,HQ,2026-12-01,2026-11-20",
 "مهمة استيراد ٢,,متوسطة,,,",
 ",bad row without title,,,,",
]
io.open('/tmp/idara-import.csv','w',encoding='utf-8',newline='').write("\n".join(rows))
PYEOF
IP=$(curl -s -b $JA "$BASE/index.php?p=admin/import")
T=$(echo "$IP" | csrf)
IR=$(curl -s -L -b $JA -F "csrf=$T" -F "csv=@/tmp/idara-import.csv;type=text/csv" "$BASE/index.php?p=admin/import")
check "import confirms 2 tasks" "$(val imp.ok | sed 's/{n}/2/')" "$IR"
check "import reports the skipped row" "$(val imp.skipped | sed 's/{n}/1/')" "$IR"
IMP=$(php -r 'define("APP_ROOT", __DIR__); require "includes/db.php"; Database::init((require "data/config.php")["db"]); echo (int) Database::value("SELECT COUNT(*) FROM tasks WHERE title LIKE ?", ["مهمة استيراد%"]);')
check "the imported tasks exist" "^2$" "$IMP"

# two-step verification (MFA) — full lifecycle for the admin
PR=$(curl -s -b $JA "$BASE/index.php?p=profile")
T=$(echo "$PR" | csrf)
curl -s -b $JA --data-urlencode "csrf=$T" -d "action=mfa_enable" -o /dev/null "$BASE/index.php?p=profile"
PR2=$(curl -s -b $JA "$BASE/index.php?p=profile")
SECRET=$(echo "$PR2" | grep -oE '[A-Z2-7]{32}' | head -1)
check "MFA secret is shown (base32)" '^[A-Z2-7]' "$SECRET"
CODE=$(totp "$SECRET")
T=$(echo "$PR2" | csrf)
MR=$(curl -s -L -b $JA --data-urlencode "csrf=$T" -d "action=mfa_verify" -d "code=$CODE" "$BASE/index.php?p=profile")
check "MFA enable is confirmed" "$(val profile.mfa_enabled_ok)" "$MR"
# fresh login now requires the second factor
JMF=/tmp/idara-mfa.jar; rm -f $JMF
LT=$(curl -s -L -c $JMF "$BASE/index.php?p=login" | csrf)
curl -s -b $JMF -c $JMF -o /dev/null -d "csrf=$LT&login=admin&password=Admin@1234" "$BASE/index.php?p=login"
L2=$(curl -s -b $JMF "$BASE/index.php?p=login")
check "MFA login shows the code prompt" "$(val auth.mfa_prompt)" "$L2"
T=$(echo "$L2" | csrf)
BAD=$(curl -s -L -b $JMF --data-urlencode "csrf=$T" --data-urlencode "mfa_code=000000" "$BASE/index.php?p=login")
check "a wrong TOTP code is refused" "$(val auth.mfa_bad)" "$BAD"
CODE2=$(totp "$SECRET")
T2=$(curl -s -b $JMF "$BASE/index.php?p=login" | csrf)
OK=$(curl -s -L -b $JMF -c $JMF --data-urlencode "csrf=$T2" --data-urlencode "mfa_code=$CODE2" "$BASE/index.php?p=login")
check "the correct TOTP code completes the login" "$(val dash.awaiting_me)" "$OK"
# disable MFA again (keeps the demo accounts simple)
PR3=$(curl -s -b $JA "$BASE/index.php?p=profile")
T=$(echo "$PR3" | csrf)
DR=$(curl -s -L -b $JA --data-urlencode "csrf=$T" -d "action=mfa_disable" -d "password=Admin@1234" "$BASE/index.php?p=profile")
check "MFA disable is confirmed" "$(val profile.mfa_disabled_ok)" "$DR"

echo "== 31. Login throttle (last — it blocks this IP for 15 minutes) =="
JTH=/tmp/idara-throttle.jar; rm -f $JTH
T=$(curl -s -c $JTH "$BASE/index.php?p=login" | csrf)
for i in 1 2 3 4 5 6; do
  curl -s -b $JTH -c $JTH -o /dev/null -d "csrf=$T&login=nobody&password=wrongpass" "$BASE/index.php?p=login"
done
LOCK=$(curl -s -b $JTH "$BASE/index.php?p=login")
check "six failed logins → lockout notice" "$(val auth.locked)" "$LOCK"

echo ""
echo "PASS: $PASS FAIL: $FAIL"
[ "$FAIL" -eq 0 ]
