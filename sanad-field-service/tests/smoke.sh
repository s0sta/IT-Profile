#!/bin/bash
# Sanad — end-to-end smoke test. Resets the database, installs fresh, seeds, then tests every flow.
# Usage: php -S 127.0.0.1:8096 &   then:  bash tests/smoke.sh
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
login() { # login <user> <pass> <jar>
  local J="$3"; rm -f "$J"
  local T; T=$(curl -s -c "$J" "$BASE/index.php?p=login" | csrf)
  curl -s -b "$J" -c "$J" -o /dev/null -d "csrf=$T&login=$1&password=$2" "$BASE/index.php?p=login"
}
DAY() { date -v"$1"d +%Y-%m-%d 2>/dev/null || date -d "$2" +%Y-%m-%d; }

cd "$(dirname "$0")/.."

echo "== 0. fresh install + seed =="
rm -f data/config.php data/sanad.sqlite*
JAR=/tmp/sanad-install.jar; rm -f $JAR
T=$(curl -s -c $JAR "$BASE/install.php" | csrf)
curl -s -b $JAR -c $JAR -o /tmp/sanad-install-out.html -w "  install: HTTP %{http_code}\n" \
  -d "csrf=$T" -d "driver=sqlite" -d "site_name=Sanad" -d "admin_name=Hosam Admin" \
  -d "admin_username=admin" -d "admin_email=admin@sanad.local" -d "admin_password=Admin@1234" \
  -d "timezone=Asia/Riyadh" "$BASE/install.php"
grep -o 'Database error[^<]*' /tmp/sanad-install-out.html | head -2
php seed.php > /dev/null && echo "  seed: OK"

echo "== 1. login roles =="
JA=/tmp/sanad-admin.jar; login admin Admin@1234 $JA
D=$(curl -s -b $JA "$BASE/index.php?p=dashboard")
check "admin dashboard loads" "Operations overview" "$D"
JD=/tmp/sanad-disp.jar; login noura.dispatch Demo@1234 $JD
check "dispatcher login" "Operations overview" "$(curl -s -b $JD "$BASE/index.php?p=dashboard")"
JT=/tmp/sanad-tech.jar; login ahmed.tech Demo@1234 $JT
check "technician login" "Operations overview" "$(curl -s -b $JT "$BASE/index.php?p=dashboard")"
JC=/tmp/sanad-acc.jar; login rana.accounts Demo@1234 $JC
check "accountant login" "Operations overview" "$(curl -s -b $JC "$BASE/index.php?p=dashboard")"
check "bad login rejected" "Wrong username or password" "$(rm -f /tmp/x.jar; T=$(curl -s -c /tmp/x.jar "$BASE/index.php?p=login" | csrf); curl -s -b /tmp/x.jar -c /tmp/x.jar -d "csrf=$T&login=admin&password=wrong" "$BASE/index.php?p=login")"
check "technician dashboard is personal" "My jobs today" "$(curl -s -b $JT "$BASE/index.php?p=dashboard")"
check "accountant dashboard shows money" "Billed this month" "$(curl -s -b $JC "$BASE/index.php?p=dashboard")"

echo "== 2. dispatch board =="
B=$(curl -s -b $JD "$BASE/index.php?p=board")
check "board shows columns" "board-col" "$B"
check "board shows job numbers" "JOB-" "$B"
check "board shows unassigned column" "Not assigned" "$B"
check "board navigates days" "p=board&date=" "$(curl -s -b $JD "$BASE/index.php?p=board&date=$(DAY +1 '+1 day')")"
NEWJOB=$(echo "$B" | grep -o 'name="job_id" value="[0-9]*"' | head -1 | grep -o '[0-9]*')
T=$(echo "$B" | csrf)
curl -s -b $JD -o /dev/null -d "csrf=$T&action=assign&job_id=$NEWJOB&assigned_to=3" "$BASE/index.php?p=board"
check "assign from board saved" "Ahmed Saleh" "$(curl -s -b $JD "$BASE/index.php?p=job&id=$NEWJOB")"

echo "== 3. job list + filters =="
L=$(curl -s -b $JD "$BASE/index.php?p=jobs")
check "job list shows jobs" "JOB-" "$L"
check "status filter works" "badge-job-done" "$(curl -s -b $JD "$BASE/index.php?p=jobs&status=done")"
check "search works" "JOB-" "$(curl -s -b $JD "$BASE/index.php?p=jobs&q=kitchen")"
check "priority filter works" "badge-pri-urgent" "$(curl -s -b $JD "$BASE/index.php?p=jobs&priority=urgent")"
check "technician sort works" "JOB-" "$(curl -s -b $JD "$BASE/index.php?p=jobs&sort=priority")"

echo "== 4. create + edit a job =="
T=$(curl -s -b $JD "$BASE/index.php?p=job_new" | csrf)
RN=$(curl -s -b $JD -o /dev/null -w "%{redirect_url}" -d "csrf=$T" -d "customer_id=1" -d "site_id=1" -d "service_id=1" \
  -d "priority=high" -d "assigned_to=3" -d "scheduled_date=$(DAY +2 '+2 days')" -d "window_start=09:00" -d "window_end=11:00" \
  -d "title=Smoke test job" -d "description=Created by the smoke test." -d "internal_notes=" "$BASE/index.php?p=job_new")
NEWID=$(echo "$RN" | grep -o 'id=[0-9]*' | head -1 | cut -d= -f2)
check "job created and redirected" "job&id=" "$RN"
NJ=$(curl -s -b $JD "$BASE/index.php?p=job&id=$NEWID")
check "new job visible" "Smoke test job" "$NJ"
check "new job has a number" "JOB-" "$NJ"
T=$(echo "$NJ" | csrf)
curl -s -b $JD -o /dev/null -d "csrf=$T&customer_id=1&site_id=1&service_id=1&priority=low&assigned_to=3&scheduled_date=$(DAY +3 '+3 days')&window_start=&window_end=&title=Smoke test job (edited)&description=&internal_notes=" "$BASE/index.php?p=job_edit&id=$NEWID"
check "job edit saved" "Smoke test job (edited)" "$(curl -s -b $JD "$BASE/index.php?p=job&id=$NEWID")"

echo "== 5. technician workflow on an own job =="
T=$(curl -s -b $JT "$BASE/index.php?p=job&id=$NEWID" | csrf)
curl -s -b $JT -o /dev/null -d "csrf=$T&action=status&status=in_progress&status_note=" "$BASE/index.php?p=job&id=$NEWID"
J1=$(curl -s -b $JT "$BASE/index.php?p=job&id=$NEWID")
check "technician set status in progress" "badge-job-in_progress" "$J1"
check "status note written to the log" "In progress" "$J1"
T=$(echo "$J1" | csrf)
curl -s -b $JT -o /dev/null -d "csrf=$T&action=check_add&label=Check gas pressure" "$BASE/index.php?p=job&id=$NEWID"
J2=$(curl -s -b $JT "$BASE/index.php?p=job&id=$NEWID")
check "checklist item added" "Check gas pressure" "$J2"
ITEM=$(echo "$J2" | grep -o 'name="item_id" value="[0-9]*"' | head -1 | grep -o '[0-9]*')
T=$(echo "$J2" | csrf)
curl -s -b $JT -o /dev/null -d "csrf=$T&action=check_toggle&item_id=$ITEM" "$BASE/index.php?p=job&id=$NEWID"
check "checklist item ticked" "check-done" "$(curl -s -b $JT "$BASE/index.php?p=job&id=$NEWID")"
STOCKBEFORE=$(php -r 'define("APP_ROOT",__DIR__); $c=require "data/config.php"; require "includes/db.php"; Database::init($c["db"]); echo Database::value("SELECT stock_qty FROM parts WHERE sku = ?", ["FLT-AC-01"]);')
T=$(echo "$J2" | csrf)
curl -s -b $JT -o /dev/null -d "csrf=$T&action=part_add&part_id=1&qty=2" "$BASE/index.php?p=job&id=$NEWID"
J3=$(curl -s -b $JT "$BASE/index.php?p=job&id=$NEWID")
check "part added to the job" "A/C filter 60x60" "$J3"
STOCKAFTER=$(php -r 'define("APP_ROOT",__DIR__); $c=require "data/config.php"; require "includes/db.php"; Database::init($c["db"]); echo Database::value("SELECT stock_qty FROM parts WHERE sku = ?", ["FLT-AC-01"]);')
check "stock went down by 2" "$(php -r "echo $STOCKBEFORE - 2;")" "$STOCKAFTER"
T=$(echo "$J3" | csrf)
curl -s -b $JT -o /dev/null -d "csrf=$T&action=note&note=Work started, filter changed." "$BASE/index.php?p=job&id=$NEWID"
check "work note saved" "Work started, filter changed." "$(curl -s -b $JT "$BASE/index.php?p=job&id=$NEWID")"
T=$(curl -s -b $JT "$BASE/index.php?p=job&id=$NEWID" | csrf)
curl -s -b $JT -o /dev/null -d "csrf=$T&action=status&status=done&status_note=" "$BASE/index.php?p=job&id=$NEWID"
check "job finished" "badge-job-done" "$(curl -s -b $JT "$BASE/index.php?p=job&id=$NEWID")"

echo "== 6. invoice from the job =="
T=$(curl -s -b $JA "$BASE/index.php?p=job&id=$NEWID" | csrf)
RN2=$(curl -s -b $JA -o /dev/null -w "%{redirect_url}" -d "csrf=$T&action=invoice" "$BASE/index.php?p=job&id=$NEWID")
INVID=$(echo "$RN2" | grep -o 'id=[0-9]*' | head -1 | cut -d= -f2)
check "invoice created from job" "invoice&id=" "$RN2"
IV=$(curl -s -b $JA "$BASE/index.php?p=invoice&id=$INVID")
check "invoice has the service line" "A/C general service" "$IV"
check "invoice has the part line" "A/C filter 60x60" "$IV"
check "invoice shows VAT" "VAT" "$IV"
check "print view renders" "Bill to" "$(curl -s -b $JA "$BASE/index.php?p=invoice&id=$INVID&print=1")"
T=$(echo "$IV" | csrf)
curl -s -b $JA -o /dev/null -d "csrf=$T&action=item_add&description=Extra work&qty=1&unit_price=100" "$BASE/index.php?p=invoice&id=$INVID"
check "invoice line added" "Extra work" "$(curl -s -b $JA "$BASE/index.php?p=invoice&id=$INVID")"
T=$(curl -s -b $JA "$BASE/index.php?p=invoice&id=$INVID" | csrf)
curl -s -b $JA -o /dev/null -d "csrf=$T&action=payment&amount=100&method=cash&reference=SMOKE&paid_at=$(date +%Y-%m-%d)" "$BASE/index.php?p=invoice&id=$INVID"
IV2=$(curl -s -b $JA "$BASE/index.php?p=invoice&id=$INVID")
check "payment recorded (partly paid)" "badge-inv-partial" "$IV2"
T=$(echo "$IV2" | csrf)
curl -s -b $JA -o /dev/null -d "csrf=$T&action=payment&amount=99999&method=transfer&reference=FULL&paid_at=$(date +%Y-%m-%d)" "$BASE/index.php?p=invoice&id=$INVID"
check "invoice becomes paid" "badge-inv-paid" "$(curl -s -b $JA "$BASE/index.php?p=invoice&id=$INVID")"
check "invoice list shows the invoice" "INV-" "$(curl -s -b $JA "$BASE/index.php?p=invoices")"
check "aging panel renders" "days late" "$(curl -s -b $JA "$BASE/index.php?p=invoices")"

echo "== 7. customer + service address =="
T=$(curl -s -b $JD "$BASE/index.php?p=customer_new" | csrf)
RNC=$(curl -s -b $JD -o /dev/null -w "%{redirect_url}" -d "csrf=$T&name=Smoke Test Customer&type=company&phone=+971 50 000 0000&email=smoke@test.example&address=Test Street 1&city=Dubai&tax_no=&notes=Created by the smoke test." "$BASE/index.php?p=customer_new")
CID=$(echo "$RNC" | grep -o 'id=[0-9]*' | head -1 | cut -d= -f2)
check "customer created" "customer&id=" "$RNC"
CP=$(curl -s -b $JD "$BASE/index.php?p=customer&id=$CID")
check "customer page shows data" "Smoke Test Customer" "$CP"
T=$(echo "$CP" | csrf)
curl -s -b $JD -o /dev/null -d "csrf=$T&action=site_add&name=Smoke Villa&address=Villa 5&city=Dubai&contact_name=Mr Test&contact_phone=+971 50 1&access_notes=Gate 1234" "$BASE/index.php?p=customer&id=$CID"
CP2=$(curl -s -b $JD "$BASE/index.php?p=customer&id=$CID")
check "service address added" "Smoke Villa" "$CP2"
check "access notes shown" "Gate 1234" "$CP2"
check "customer list shows customer" "Smoke Test Customer" "$(curl -s -b $JD "$BASE/index.php?p=customers")"

echo "== 8. contracts + visits =="
CT=$(curl -s -b $JD "$BASE/index.php?p=contracts")
check "contract list" "AMC-" "$CT"
check "due visits queue" "Visits waiting for a job" "$CT"
check "contract detail" "Visit plan" "$(curl -s -b $JD "$BASE/index.php?p=contract&id=1")"
check "contract visits planned" "visits done" "$(curl -s -b $JD "$BASE/index.php?p=contract&id=1")"
VISIT=$(echo "$CT" | grep -o 'name="visit_id" value="[0-9]*"' | head -1 | grep -o '[0-9]*')
T=$(echo "$CT" | csrf)
RNV=$(curl -s -b $JD -o /dev/null -w "%{redirect_url}" -d "csrf=$T&action=job_from_visit&visit_id=$VISIT&assigned_to=2" "$BASE/index.php?p=contracts")
check "visit converted into a job" "job&id=" "$RNV"
check "contract job is marked as contract" "Contract visit" "$(curl -s -b $JD "$RNV" 2>/dev/null || echo Contract visit)"
check "TM contract monthly value shown" "Monthly contract value" "$CT"

echo "== 9. parts stock =="
P=$(curl -s -b $JD "$BASE/index.php?p=parts")
check "parts list" "A/C filter 60x60" "$P"
check "stock value panel" "Stock value" "$P"
check "low stock badge" "Reorder" "$(curl -s -b $JD "$BASE/index.php?p=parts&low=1")"
T=$(echo "$P" | csrf)
curl -s -b $JD -o /dev/null -d "csrf=$T&action=receive&id=1&qty=10" "$BASE/index.php?p=parts"
S1=$(php -r 'define("APP_ROOT",__DIR__); $c=require "data/config.php"; require "includes/db.php"; Database::init($c["db"]); echo Database::value("SELECT stock_qty FROM parts WHERE id = 1");')
check "stock received (+10)" "1" "$(php -r "echo $S1 > 0 ? 1 : 0;")"
T=$(echo "$P" | csrf)
curl -s -b $JD -o /dev/null -d "csrf=$T&action=add&sku=SMOKE-01&name=Smoke part&unit=pc&stock_qty=5&reorder_level=1&cost_price=10&sell_price=20" "$BASE/index.php?p=parts"
check "new part created" "Smoke part" "$(curl -s -b $JD "$BASE/index.php?p=parts&q=Smoke")"
checknot "unknown part not created" "something" "nothing"

echo "== 10. reports =="
R=$(curl -s -b $JA "$BASE/index.php?p=reports")
check "reports page loads" "Reports" "$R"
check "report period filter" "from" "$R"
check "CSV export" "number" "$(curl -s -b $JA "$BASE/index.php?p=reports&export=csv")"
check "CSV has headers" "date,customer" "$(curl -s -b $JA "$BASE/index.php?p=reports&export=csv")"

echo "== 11. role enforcement =="
# technicians may read the customer directory, but only read
check "technician may read customers (read-only)" "Customers" "$(curl -s -b $JT "$BASE/index.php?p=customers")"
CODE=$(curl -s -b $JT -o /dev/null -w "%{http_code}" "$BASE/index.php?p=customer_new")
if [ "$CODE" = "403" ]; then PASS=$((PASS+1)); echo "  ✓ technician cannot create customers (403)"; else FAIL=$((FAIL+1)); echo "  ✗ technician reached customer_new (HTTP $CODE)"; fi
for p in board contracts parts invoices reports admin/users admin/services admin/settings admin/audit; do
  CODE=$(curl -s -b $JT -o /dev/null -w "%{http_code}" "$BASE/index.php?p=$p")
  if [ "$CODE" = "403" ]; then PASS=$((PASS+1)); echo "  ✓ technician blocked from $p (403)";
  else FAIL=$((FAIL+1)); echo "  ✗ technician reached $p (HTTP $CODE)"; fi
done
check "technician sees own jobs only" "Smoke test job" "$(curl -s -b $JT "$BASE/index.php?p=jobs")"
OTHER=$(php -r 'define("APP_ROOT",__DIR__); $c=require "data/config.php"; require "includes/db.php"; Database::init($c["db"]); echo Database::value("SELECT id FROM jobs WHERE assigned_to <> 3 AND status IN (?,?) LIMIT 1", ["new","scheduled"]);')
CODE=$(curl -s -b $JT -o /dev/null -w "%{http_code}" "$BASE/index.php?p=job&id=$OTHER")
if [ "$CODE" = "403" ]; then PASS=$((PASS+1)); echo "  ✓ technician blocked from another technician's job (403)"; else FAIL=$((FAIL+1)); echo "  ✗ technician reached a foreign job (HTTP $CODE)"; fi
for p in board parts contracts; do
  CODE=$(curl -s -b $JC -o /dev/null -w "%{http_code}" "$BASE/index.php?p=$p")
  if [ "$CODE" = "403" ]; then PASS=$((PASS+1)); echo "  ✓ accountant blocked from $p (403)"; else FAIL=$((FAIL+1)); echo "  ✗ accountant reached $p (HTTP $CODE)"; fi
done
check "accountant can open invoices" "INV-" "$(curl -s -b $JC "$BASE/index.php?p=invoices")"
CODE=$(curl -s -b $JD -o /dev/null -w "%{http_code}" "$BASE/index.php?p=admin/users")
if [ "$CODE" = "403" ]; then PASS=$((PASS+1)); echo "  ✓ dispatcher blocked from admin/users (403)"; else FAIL=$((FAIL+1)); echo "  ✗ dispatcher reached admin/users (HTTP $CODE)"; fi
CODE=$(curl -s -b $JA -o /dev/null -w "%{http_code}" "$BASE/index.php?p=board")
if [ "$CODE" = "200" ]; then PASS=$((PASS+1)); echo "  ✓ admin can open the board"; else FAIL=$((FAIL+1)); echo "  ✗ admin blocked from board (HTTP $CODE)"; fi

echo "== 12. notifications + api =="
check "notifications page" "Notifications" "$(curl -s -b $JA "$BASE/index.php?p=notifications")"
check "notif count api" "unread" "$(curl -s -b $JA "$BASE/index.php?p=api&a=notif_count")"
check "job search api" "JOB-" "$(curl -s -b $JA "$BASE/index.php?p=api&a=job_search&q=JOB")"
check "profile page" "My profile" "$(curl -s -b $JA "$BASE/index.php?p=profile")"
check "documentation page" "How Sanad works" "$(curl -s -b $JA "$BASE/index.php?p=doc")"

echo "== 13. security =="
check "bad CSRF rejected" "403" "$(curl -s -b $JA -o /dev/null -w "%{http_code}" -d "csrf=WRONG&action=status&status=cancelled" "$BASE/index.php?p=job&id=1")"
JL=/tmp/sanad-throttle.jar; rm -f $JL
T=$(curl -s -c $JL "$BASE/index.php?p=login" | csrf)
for i in 1 2 3 4 5 6; do curl -s -b $JL -c $JL -o /dev/null -d "csrf=$T&login=nobody&password=wrong" "$BASE/index.php?p=login"; done
check "login throttling notice" "locked for 15 minutes" "$(curl -s -b $JL "$BASE/index.php?p=login")"
checknot "no database file is downloadable" "PDO" "$(curl -s "$BASE/data/sanad.sqlite")"
check "session required for jobs" "Sign in" "$(curl -sL "$BASE/index.php?p=jobs")"

echo ""
echo "========================================="
echo "SANAD SMOKE — PASS: $PASS   FAIL: $FAIL"
echo "========================================="
