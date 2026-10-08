<?php
declare(strict_types=1);

/**
 * Idara — demo data seeder (Investment Authority scenario).
 * Run:  php seed.php   (refuses if tasks exist)   ·   php seed.php --force
 */

define('APP_ROOT', __DIR__);
define('CONFIG_FILE', APP_ROOT . '/data/config.php');

if (!is_file(CONFIG_FILE)) {
    fwrite(STDERR, "Idara is not installed yet. Open install.php first.\n");
    exit(1);
}
$config = require CONFIG_FILE;
require_once APP_ROOT . '/includes/db.php';
Database::init($config['db']);

$force = in_array('--force', $argv ?? [], true) || isset($_GET['force']);
$taskCount = (int) Database::value('SELECT COUNT(*) FROM tasks');
if ($taskCount > 0 && !$force) {
    echo "Tasks already exist ({$taskCount}). Use --force to add demo data anyway.\n";
    exit(0);
}

function dt(string $off): string { return date('Y-m-d H:i:s', strtotime($off)); }
function dd(string $off): string { return date('Y-m-d', strtotime($off)); }

function dept(string $ar, string $en, string $code, int $sort): int
{
    $e = Database::value('SELECT id FROM departments WHERE code = ?', [$code]);
    return $e ? (int) $e : Database::insert(
        'INSERT INTO departments (name_ar, name_en, code, manager_id, active, sort_order) VALUES (?, ?, ?, NULL, 1, ?)',
        [$ar, $en, $code, $sort]
    );
}

function user(string $name, string $en, string $login, string $pass, string $role, ?int $deptId, string $job, ?int $mgr = null): int
{
    $e = Database::value('SELECT id FROM users WHERE username = ?', [$login]);
    return $e ? (int) $e : Database::insert(
        'INSERT INTO users (name, name_en, username, email, password_hash, role, department_id, job_title, phone, manager_id, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
        [$name, $en, $login, $login . '@idara.local', password_hash($pass, PASSWORD_DEFAULT), $role, $deptId, $job, '', $mgr, dt('-90 days')]
    );
}

function ref(string $table, string $prefix, int $id): void
{
    Database::exec("UPDATE {$table} SET ref = ? WHERE id = ?", [$prefix . '-' . date('Y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $id]);
}

function task(array $t): int
{
    $id = Database::insert(
        'INSERT INTO tasks (title, description, category_id, priority, status, progress, creator_id, assignee_id, department_id, start_date, due_date, completed_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$t['title'], $t['desc'] ?? '', $t['cat'] ?? null, $t['prio'], $t['status'], $t['prog'],
         $t['creator'], $t['assignee'] ?? null, $t['dept'] ?? null, $t['start'] ?? null, $t['due'] ?? null,
         $t['done'] ?? null, $t['created'] ?? dt('-5 days'), $t['updated'] ?? dt('-1 day')]
    );
    ref('tasks', 'TSK', $id);
    return $id;
}

function item(int $taskId, string $title, bool $done, int $sort): void
{
    Database::exec('INSERT INTO task_items (task_id, title, is_done, sort_order, created_at) VALUES (?, ?, ?, ?, ?)', [$taskId, $title, $done ? 1 : 0, $sort, dt('-5 days')]);
}

function upd(int $taskId, int $author, string $body, ?int $prog, string $off): void
{
    Database::exec('INSERT INTO task_updates (task_id, author_id, body, progress, created_at) VALUES (?, ?, ?, ?, ?)', [$taskId, $author, $body, $prog, dt($off)]);
}

function approval(array $a, array $chain): int
{
    $id = Database::insert(
        'INSERT INTO approvals (title, description, type_id, requester_id, department_id, priority, status, current_step, due_date, related_task_id, created_at, updated_at, closed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$a['title'], $a['desc'] ?? '', $a['type'] ?? null, $a['req'], $a['dept'] ?? null, $a['prio'],
         $a['status'], $a['step'] ?? 1, $a['due'] ?? null, $a['task'] ?? null,
         $a['created'] ?? dt('-4 days'), $a['updated'] ?? dt('-1 day'), $a['closed'] ?? null]
    );
    ref('approvals', 'APR', $id);
    $order = 1;
    foreach ($chain as $s) {
        Database::exec(
            'INSERT INTO approval_steps (approval_id, step_order, approver_id, status, note, decided_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $order, $s['who'], $s['status'], $s['note'] ?? '', isset($s['at']) ? dt($s['at']) : null, $a['created'] ?? dt('-4 days')]
        );
        $order++;
    }
    return $id;
}

function letter(array $c): int
{
    $id = Database::insert(
        'INSERT INTO correspondence (direction, subject, summary, party, reference_no, priority, status, assignee_id, department_id, received_at, due_date, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$c['dir'], $c['subject'], $c['summary'] ?? '', $c['party'] ?? '', $c['ref_no'] ?? '', $c['prio'],
         $c['status'], $c['assignee'] ?? null, $c['dept'] ?? null, $c['received'] ?? dd('-3 days'), $c['due'] ?? null, dt('-3 days'), dt('-1 day')]
    );
    ref('correspondence', $c['dir'] === 'incoming' ? 'IN' : 'OUT', $id);
    return $id;
}

function meeting(array $m, array $who): int
{
    $id = Database::insert(
        'INSERT INTO meetings (title, agenda, location, starts_at, ends_at, organizer_id, minutes, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$m['title'], $m['agenda'] ?? '', $m['location'] ?? 'قاعة الاجتماعات الرئيسية', $m['starts'], $m['ends'] ?? null,
         $m['org'], $m['minutes'] ?? '', $m['status'], dt('-10 days'), dt('-1 day')]
    );
    foreach (array_unique($who) as $uid) {
        Database::exec('INSERT INTO meeting_attendees (meeting_id, user_id, attended) VALUES (?, ?, ?)', [$id, (int) $uid, $m['attended'] ?? 0]);
    }
    return $id;
}

// ---------------------------------------------------------------- departments
$hq   = dept('الإدارة العامة', 'Head Office', 'HQ', 1);
$fdi  = dept('إدارة الاستثمار الأجنبي', 'Foreign Investment Department', 'FDI', 2);
$lic  = dept('إدارة التراخيص الاستثمارية', 'Investment Licensing Department', 'LIC', 3);
$inv  = dept('إدارة خدمات المستثمرين', 'Investor Services Department', 'INV', 4);
$leg  = dept('الشؤون القانونية', 'Legal Affairs', 'LEG', 5);
dept('التخطيط الاستراتيجي', 'Strategic Planning', 'STR', 6);
dept('العلاقات الدولية', 'International Relations', 'INT', 7);
$it   = dept('تقنية المعلومات', 'Information Technology', 'IT', 8);

// ---------------------------------------------------------------- users
user('مسؤول النظام', 'System Administrator', 'admin', 'Admin@1234', 'admin', $it, 'مسؤول النظام');
$exec = user('عبدالله الحربي', 'Abdullah Al-Harbi', 'abdullah.harbi', 'Manager@1234', 'executive', $hq, 'وكيل الوزارة المساعد للاستثمار');
$mLic = user('سارة القحطاني', 'Sarah Al-Qahtani', 'sarah.qahtani', 'Manager@1234', 'manager', $lic, 'مدير إدارة التراخيص الاستثمارية', $exec);
$mFdi = user('محمد العتيبي', 'Mohammed Al-Otaibi', 'mohammed.otaibi', 'Manager@1234', 'manager', $fdi, 'مدير إدارة الاستثمار الأجنبي', $exec);
$mInv = user('هند العنزي', 'Hind Al-Anazi', 'hind.anazi', 'Manager@1234', 'manager', $inv, 'مدير إدارة خدمات المستثمرين', $exec);
$nora = user('نورة الشمري', 'Noura Al-Shammari', 'noura.shammari', 'User@1234', 'member', $lic, 'أخصائية تراخيص استثمارية', $mLic);
$fahd = user('فهد الدوسري', 'Fahad Al-Dosari', 'fahad.dosari', 'User@1234', 'member', $fdi, 'محلل استثمار أجنبي', $mFdi);
$reem = user('ريم الزهراني', 'Reem Al-Zahrani', 'reem.zahrani', 'User@1234', 'member', $leg, 'باحثة قانونية', $mLic);
$khal = user('خالد الغامدي', 'Khalid Al-Ghamdi', 'khalid.ghamdi', 'User@1234', 'member', $it, 'مطوّر نظم', $mLic);
$mona = user('منى السبيعي', 'Mona Al-Subaie', 'mona.subaie', 'User@1234', 'member', $inv, 'مسؤولة علاقات المستثمرين', $mInv);

// ---------------------------------------------------------------- reference data
$cProj = (int) (Database::value('SELECT id FROM task_categories WHERE name_ar = ?', ['مشاريع']) ?: Database::insert('INSERT INTO task_categories (name_ar, name_en, active, sort_order) VALUES (?, ?, 1, 1)', ['مشاريع', 'Projects']));
$cRep  = (int) (Database::value('SELECT id FROM task_categories WHERE name_ar = ?', ['تقارير']) ?: Database::insert('INSERT INTO task_categories (name_ar, name_en, active, sort_order) VALUES (?, ?, 1, 2)', ['تقارير', 'Reports']));
$cMeet = (int) (Database::value('SELECT id FROM task_categories WHERE name_ar = ?', ['اجتماعات']) ?: Database::insert('INSERT INTO task_categories (name_ar, name_en, active, sort_order) VALUES (?, ?, 1, 3)', ['اجتماعات', 'Meetings']));
$cCorr = (int) (Database::value('SELECT id FROM task_categories WHERE name_ar = ?', ['مراسلات']) ?: Database::insert('INSERT INTO task_categories (name_ar, name_en, active, sort_order) VALUES (?, ?, 1, 4)', ['مراسلات', 'Correspondence']));

$tyTask = (int) (Database::value('SELECT id FROM approval_types WHERE name_ar = ?', ['اعتماد مهمة']) ?: 0);
$tyReq  = (int) (Database::value('SELECT id FROM approval_types WHERE name_ar = ?', ['اعتماد طلب']) ?: 0);
$tyLet  = (int) (Database::value('SELECT id FROM approval_types WHERE name_ar = ?', ['اعتماد خطاب']) ?: 0);
$tyExp  = (int) (Database::value('SELECT id FROM approval_types WHERE name_ar = ?', ['اعتماد صرف']) ?: 0);
$tyLev  = (int) (Database::value('SELECT id FROM approval_types WHERE name_ar = ?', ['اعتماد إجازة']) ?: 0);

// ---------------------------------------------------------------- tasks
$t1 = task(['title' => 'إعداد التقرير الربع سنوي لمؤشرات الاستثمار الأجنبي', 'desc' => 'تجميع بيانات الاستثمار الأجنبي للربع ومقارنتها بالربع المماثل مع تحليل أسباب التغير.', 'cat' => $cRep, 'prio' => 'high', 'status' => 'in_progress', 'prog' => 60, 'creator' => $mFdi, 'assignee' => $fahd, 'dept' => $fdi, 'start' => dd('-12 days'), 'due' => dd('+2 days'), 'created' => dt('-12 days'), 'updated' => dt('-1 day')]);
item($t1, 'تجميع بيانات الاستثمار الأجنبي', true, 1);
item($t1, 'مقارنة الأداء مع الربع المماثل', true, 2);
item($t1, 'تحليل أسباب التغير وصياغة التوصيات', false, 3);
item($t1, 'مراجعة التقرير قبل الرفع', false, 4);
upd($t1, $fahd, 'تم الانتهاء من الجدول المقارن، والعمل جارٍ على تحليل الأسباب.', 60, '-1 day');

$t2 = task(['title' => 'مراجعة طلبات الترخيص المتأخرة', 'desc' => 'حصر الطلبات المتجاوزة للمدة النظامية والرفع بخطة معالجة.', 'cat' => $cProj, 'prio' => 'urgent', 'status' => 'new', 'prog' => 0, 'creator' => $mLic, 'assignee' => $nora, 'dept' => $lic, 'start' => dd('-2 days'), 'due' => dd('-1 day'), 'created' => dt('-6 days'), 'updated' => dt('-6 days')]);

$t3 = task(['title' => 'تحديث دليل خدمة المستثمر', 'desc' => 'تحديث الخطوات والمستندات المطلوبة وفق اللائحة الجديدة.', 'cat' => $cProj, 'prio' => 'medium', 'status' => 'waiting', 'prog' => 45, 'creator' => $mInv, 'assignee' => $mona, 'dept' => $inv, 'start' => dd('-9 days'), 'due' => dd('+5 days'), 'created' => dt('-9 days'), 'updated' => dt('-3 days')]);
item($t3, 'تحديث الخطوات', true, 1);
item($t3, 'تحديث المستندات المطلوبة', false, 2);
upd($t3, $mona, 'بانتظار اعتماد الصياغة النهائية من الشؤون القانونية.', 45, '-3 days');

$t4 = task(['title' => 'دراسة مقارنة لأنظمة تحفيز الاستثمار في المنطقة', 'desc' => 'مقارنة بين خمس دول مع توصيات لتطوير الحوافز.', 'cat' => $cRep, 'prio' => 'medium', 'status' => 'in_progress', 'prog' => 30, 'creator' => $exec, 'assignee' => $mFdi, 'dept' => $fdi, 'start' => dd('-15 days'), 'due' => dd('+10 days'), 'created' => dt('-15 days'), 'updated' => dt('-4 days')]);

$t5 = task(['title' => 'إطلاق النسخة الجديدة من بوابة التراخيص', 'desc' => 'اختبار البوابة، تدريب الفرق، ثم الإطلاق التدريجي.', 'cat' => $cProj, 'prio' => 'high', 'status' => 'blocked', 'prog' => 25, 'creator' => $mLic, 'assignee' => $khal, 'dept' => $it, 'start' => dd('-20 days'), 'due' => dd('+3 days'), 'created' => dt('-20 days'), 'updated' => dt('-2 days')]);
item($t5, 'اختبار القبول', true, 1);
item($t5, 'تدريب موظفي التراخيص', false, 2);
item($t5, 'الإطلاق التدريجي', false, 3);
upd($t5, $khal, 'التعطل بسبب عدم توفر بيئة الاختبار النهائية.', 25, '-2 days');

$t6 = task(['title' => 'إعداد مذكرة قانونية بشأن تحديث اللائحة', 'desc' => 'مذكرة مختصرة بالآثار القانونية للتعديلات المقترحة.', 'cat' => $cCorr, 'prio' => 'high', 'status' => 'in_progress', 'prog' => 70, 'creator' => $mLic, 'assignee' => $reem, 'dept' => $leg, 'start' => dd('-7 days'), 'due' => dd('+1 day'), 'created' => dt('-7 days'), 'updated' => dt('-12 hours')]);

$t7 = task(['title' => 'تنظيم ورشة عمل للمستثمرين الأجانب', 'desc' => 'تجهيز العرض والدعوات ومستلزمات الورشة.', 'cat' => $cMeet, 'prio' => 'medium', 'status' => 'completed', 'prog' => 100, 'creator' => $mInv, 'assignee' => $mona, 'dept' => $inv, 'start' => dd('-25 days'), 'due' => dd('-10 days'), 'done' => dt('-10 days'), 'created' => dt('-25 days'), 'updated' => dt('-10 days')]);

$t8 = task(['title' => 'أرشفة ملفات الاتفاقيات الدولية', 'desc' => 'ترتيب وأرشفة الاتفاقيات الموقعة إلكترونياً.', 'cat' => $cCorr, 'prio' => 'low', 'status' => 'new', 'prog' => 0, 'creator' => $mFdi, 'assignee' => $fahd, 'dept' => $fdi, 'start' => dd('-1 day'), 'due' => dd('+20 days'), 'created' => dt('-1 day'), 'updated' => dt('-1 day')]);

$t9 = task(['title' => 'متابعة توصيات الاجتماع السابق مع الشؤون القانونية', 'desc' => 'حصر التوصيات ومتابعة تنفيذها.', 'cat' => $cMeet, 'prio' => 'urgent', 'status' => 'waiting', 'prog' => 50, 'creator' => $exec, 'assignee' => $mLic, 'dept' => $lic, 'start' => dd('-8 days'), 'due' => dd('-2 days'), 'created' => dt('-8 days'), 'updated' => dt('-2 days')]);

$t10 = task(['title' => 'تحديث صفحة الفرص الاستثمارية على المنصة', 'desc' => 'إضافة الفرص الجديدة وتحديث المنتهية.', 'cat' => $cProj, 'prio' => 'medium', 'status' => 'in_progress', 'prog' => 80, 'creator' => $mInv, 'assignee' => $khal, 'dept' => $it, 'start' => dd('-5 days'), 'due' => dd('+4 days'), 'created' => dt('-5 days'), 'updated' => dt('-1 day')]);

$t11 = task(['title' => 'إعداد خطة الأداء للربع القادم', 'desc' => 'خطة الأداء التشغيلي مع المؤشرات المستهدفة.', 'cat' => $cRep, 'prio' => 'high', 'status' => 'new', 'prog' => 0, 'creator' => $exec, 'assignee' => $mInv, 'dept' => $inv, 'start' => dd('+1 day'), 'due' => dd('+12 days'), 'created' => dt('-3 days'), 'updated' => dt('-3 days')]);

$t12 = task(['title' => 'معالجة ملاحظات المراجعة الداخلية', 'desc' => 'تنفيذ ملاحظات تقرير المراجعة الداخلية.', 'cat' => $cRep, 'prio' => 'high', 'status' => 'in_progress', 'prog' => 40, 'creator' => $exec, 'assignee' => $mLic, 'dept' => $lic, 'start' => dd('-6 days'), 'due' => dd('+6 days'), 'created' => dt('-6 days'), 'updated' => dt('-2 days')]);

// ---------------------------------------------------------------- approvals
$ap1 = approval(['title' => 'اعتماد إصدار ترخيص استثماري — شركة الخليج للتقنية', 'desc' => 'استكملت الشركة جميع المتطلبات وسدّدت الرسوم. المطلوب الاعتماد لإصدار الترخيص.', 'type' => $tyTask ?: null, 'req' => $nora, 'dept' => $lic, 'prio' => 'high', 'status' => 'pending', 'step' => 1, 'due' => dd('+1 day'), 'created' => dt('-2 days'), 'updated' => dt('-1 day')], [
    ['who' => $mLic, 'status' => 'pending'],
    ['who' => $exec, 'status' => 'waiting'],
]);
$ap2 = approval(['title' => 'اعتماد خطاب رسمي إلى وزارة المالية', 'desc' => 'مسودة الخطاب المتعلق ببيانات الحوافز للسنة المالية الحالية.', 'type' => $tyLet ?: null, 'req' => $reem, 'dept' => $leg, 'prio' => 'urgent', 'status' => 'pending', 'step' => 1, 'due' => dd('-1 day'), 'created' => dt('-3 days'), 'updated' => dt('-3 days')], [
    ['who' => $mLic, 'status' => 'pending'],
]);
$ap3 = approval(['title' => 'اعتماد صرف ميزانية ورشة عمل المستثمرين', 'desc' => 'صرف مستلزمات وتنظيم الورشة التعريفية.', 'type' => $tyExp ?: null, 'req' => $mona, 'dept' => $inv, 'prio' => 'medium', 'status' => 'pending', 'step' => 2, 'due' => dd('+3 days'), 'created' => dt('-5 days'), 'updated' => dt('-1 day')], [
    ['who' => $mInv, 'status' => 'approved', 'note' => 'الميزانية مطابقة للخطة المعتمدة.', 'at' => '-1 day'],
    ['who' => $exec, 'status' => 'pending'],
]);
$ap4 = approval(['title' => 'اعتماد مهمة تحديث دليل خدمة المستثمر', 'desc' => 'الاعتماد النهائي للمحتوى المحدث قبل النشر.', 'type' => $tyTask ?: null, 'req' => $mInv, 'dept' => $inv, 'prio' => 'medium', 'status' => 'returned', 'step' => 1, 'task' => $t3, 'created' => dt('-7 days'), 'updated' => dt('-4 days'), 'closed' => dt('-4 days')], [
    ['who' => $exec, 'status' => 'returned', 'note' => 'يرجى إضافة المستندات المطلوبة في جدول واضح.', 'at' => '-4 days'],
]);
$ap5 = approval(['title' => 'اعتماد إجازة سنوية — نورة الشمري', 'desc' => 'طلب إجازة سنوية لخمسة أيام عمل.', 'type' => $tyLev ?: null, 'req' => $nora, 'dept' => $lic, 'prio' => 'low', 'status' => 'approved', 'step' => 1, 'created' => dt('-12 days'), 'updated' => dt('-11 days'), 'closed' => dt('-11 days')], [
    ['who' => $mLic, 'status' => 'approved', 'note' => 'تم الاعتماد مع ترتيب المهام أثناء الغياب.', 'at' => '-11 days'],
]);
$ap6 = approval(['title' => 'اعتماد تحديث سياسة الخصوصية للمستثمرين', 'desc' => 'تحديث السياسة وفق المتطلبات النظامية الجديدة.', 'type' => $tyReq ?: null, 'req' => $reem, 'dept' => $leg, 'prio' => 'high', 'status' => 'pending', 'step' => 1, 'due' => dd('+2 days'), 'created' => dt('-1 day'), 'updated' => dt('-1 day')], [
    ['who' => $mLic, 'status' => 'pending'],
    ['who' => $exec, 'status' => 'waiting'],
]);
$ap7 = approval(['title' => 'اعتماد صرف مستلزمات تقنية للفريق', 'desc' => 'شراء أجهزة حاسوب محمولة للموظفين الجدد.', 'type' => $tyExp ?: null, 'req' => $khal, 'dept' => $it, 'prio' => 'medium', 'status' => 'pending', 'step' => 1, 'due' => dd('+5 days'), 'created' => dt('-2 days'), 'updated' => dt('-2 days')], [
    ['who' => $mLic, 'status' => 'pending'],
    ['who' => $mInv, 'status' => 'waiting'],
    ['who' => $exec, 'status' => 'waiting'],
]);
$ap8 = approval(['title' => 'اعتماد تقرير المراجعة الداخلية', 'desc' => 'اعتماد الرد الرسمي على ملاحظات المراجعة.', 'type' => $tyTask ?: null, 'req' => $mLic, 'dept' => $lic, 'prio' => 'high', 'status' => 'approved', 'step' => 1, 'task' => $t12, 'created' => dt('-15 days'), 'updated' => dt('-13 days'), 'closed' => dt('-13 days')], [
    ['who' => $exec, 'status' => 'approved', 'note' => 'الرد واضح ويغطي جميع الملاحظات.', 'at' => '-13 days'],
]);

// ---------------------------------------------------------------- correspondence
letter(['dir' => 'incoming', 'subject' => 'طلب بيانات عن الفرص الاستثمارية في قطاع الطاقة المتجددة', 'summary' => 'طلب رسمي من جهة حكومية شقيقة للحصول على بيانات محدثة.', 'party' => 'وزارة الطاقة', 'ref_no' => 'ME-2026-4471', 'prio' => 'high', 'status' => 'under_review', 'assignee' => $fahd, 'dept' => $fdi, 'due' => dd('+2 days')]);
letter(['dir' => 'incoming', 'subject' => 'دعوة لحضور منتدى الاستثمار الدولي', 'summary' => 'دعوة رسمية لحضور المنتدى ومشاركة ورقة عمل.', 'party' => 'الأمانة العامة لمجلس التعاون', 'ref_no' => 'GCC-2026-1120', 'prio' => 'medium', 'status' => 'new', 'assignee' => $mona, 'dept' => $inv, 'due' => dd('+7 days')]);
letter(['dir' => 'outgoing', 'subject' => 'رد على استفسار شركة أجنبية بشأن متطلبات الترخيص', 'summary' => 'إرسال المتطلبات الكاملة وخطوات التقديم.', 'party' => 'شركة نورديك للطاقة', 'ref_no' => 'OUT-2026-0231', 'prio' => 'medium', 'status' => 'replied', 'assignee' => $nora, 'dept' => $lic, 'due' => dd('-2 days')]);
letter(['dir' => 'incoming', 'subject' => 'ملاحظات المراجعة الداخلية على إجراءات التراخيص', 'summary' => 'تقرير المراجعة الداخلية متضمناً عدداً من الملاحظات.', 'party' => 'المراجعة الداخلية', 'ref_no' => 'AUD-2026-0089', 'prio' => 'urgent', 'status' => 'under_review', 'assignee' => $mLic, 'dept' => $lic, 'due' => dd('-1 day')]);
letter(['dir' => 'outgoing', 'subject' => 'خطاب شكر وتقدير لشركاء الاستثمار', 'summary' => 'خطاب رسمي لعدد من الشركاء.', 'party' => 'شركاء الاستثمار', 'ref_no' => 'OUT-2026-0188', 'prio' => 'low', 'status' => 'archived', 'assignee' => $mona, 'dept' => $inv]);
letter(['dir' => 'incoming', 'subject' => 'طلب تمديد مدة ترخيص استثماري قائم', 'summary' => 'طلب تمديد مع المرفقات والمبررات.', 'party' => 'شركة الرياض للصناعات', 'ref_no' => 'IN-2026-0912', 'prio' => 'high', 'status' => 'new', 'assignee' => $nora, 'dept' => $lic, 'due' => dd('+1 day')]);

// ---------------------------------------------------------------- meetings
meeting(['title' => 'الاجتماع الأسبوعي لإدارة التراخيص', 'agenda' => "1. متابعة الطلبات المتأخرة\n2. مستجدات بوابة التراخيص\n3. مهام الأسبوع القادم", 'starts' => dt('+1 day 10:00'), 'ends' => dt('+1 day 11:00'), 'org' => $mLic, 'status' => 'scheduled'], [$nora, $reem, $khal]);
meeting(['title' => 'اجتماع لجنة الاستثمار — مراجعة الفرص الجديدة', 'agenda' => "1. عرض الفرص الجديدة\n2. تقييم المخاطر\n3. القرارات", 'location' => 'قاعة المجلس', 'starts' => dt('+3 days 09:30'), 'ends' => dt('+3 days 11:30'), 'org' => $exec, 'status' => 'scheduled'], [$mLic, $mFdi, $mInv]);
meeting(['title' => 'ورشة عمل المستثمرين الأجانب', 'agenda' => 'تعريف بالحوافز والخدمات والخطوات النظامية.', 'location' => 'القاعة الكبرى', 'starts' => dt('-10 days 09:00'), 'ends' => dt('-10 days 12:00'), 'org' => $mInv, 'status' => 'done', 'attended' => 1, 'minutes' => "حضر ٣٢ مستثمراً، وتم استعراض الحوافز والخدمات.\nالتوصية: إعداد دليل مبسط ونشره إلكترونياً قبل الربع القادم."], [$mona, $mLic, $mFdi]);
meeting(['title' => 'اجتماع متابعة توصيات المراجعة الداخلية', 'agenda' => 'مراجعة الرد والجدول الزمني للتنفيذ.', 'location' => 'قاعة الاجتماعات الفرعية', 'starts' => dt('-2 days 11:00'), 'ends' => dt('-2 days 12:00'), 'org' => $mLic, 'status' => 'done', 'attended' => 1, 'minutes' => 'تم الاتفاق على تنفيذ الملاحظات خلال أسبوعين مع تقرير متابعة أسبوعي.'], [$reem, $khal]);

// ---------------------------------------------------------------- delegation + notifications
if (!Database::value('SELECT COUNT(*) FROM delegations')) {
    Database::exec(
        'INSERT INTO delegations (delegator_id, delegate_id, starts_at, ends_at, reason, active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)',
        [$mLic, $nora, dd('-1 day'), dd('+6 days'), 'مهمة رسمية خارج الوزارة', dt('-2 days')]
    );
}

foreach ([[$mLic, 'طلب اعتماد بانتظارك: اعتماد إصدار ترخيص استثماري — شركة الخليج للتقنية', $ap1],
          [$mLic, 'طلب اعتماد بانتظارك: اعتماد خطاب رسمي إلى وزارة المالية', $ap2],
          [$mLic, 'طلب اعتماد بانتظارك: اعتماد تحديث سياسة الخصوصية للمستثمرين', $ap6],
          [$mLic, 'طلب اعتماد بانتظارك: اعتماد صرف مستلزمات تقنية للفريق', $ap7],
          [$exec, 'طلب اعتماد بانتظارك: اعتماد صرف ميزانية ورشة عمل المستثمرين', $ap3]] as [$uid, $msg, $apId]) {
    Database::exec(
        'INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, ?)',
        [$uid, $msg, 'index.php?p=approval&id=' . $apId, dt('-1 day')]
    );
}

echo "Idara demo data ready.\n";
echo "  Departments : 8 · Users: 10 (admin, executive, 3 managers, 5 employees)\n";
echo "  Tasks       : 12 (checklists + follow-ups, overdue & blocked included)\n";
echo "  Approvals   : 8 (multi-step chains; 5 waiting for a manager decision)\n";
echo "  Letters     : 6 · Meetings: 4 · Delegations: 1\n";
echo "  Logins      : admin / Admin@1234 · sarah.qahtani / Manager@1234 · noura.shammari / User@1234\n";
