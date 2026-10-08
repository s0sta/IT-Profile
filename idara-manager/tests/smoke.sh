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
check "approve shows the success message" "$(val approvals.approved_msg)" "$RAV"
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
ER=$(curl -s -L -b $JN --data-urlencode "csrf=$T" -d "action=edit" --data-urlencode "title=طلب فحص التعديل — بعد" --data-urlencode "description=بعد التعديل" -d "type_id=" -d "priority=high" -d "due_date=" -d "approvers[]=3" -d "approvers[]=2" "$BASE/index.php?p=approval&id=$EID")
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
checknot "B4 reopened task no longer shows 100%" "100%" "$RP"

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

echo "== 27. Login throttle (last — it blocks this IP for 15 minutes) =="
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
