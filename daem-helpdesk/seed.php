<?php
declare(strict_types=1);

/**
 * Daem — demo data seeder.
 * Run:  php seed.php          (refuses if tickets already exist)
 *       php seed.php --force  (adds data anyway)
 *
 * Seeds: 2 agents, 3 requesters, 7 ticket categories, 2 KB categories,
 * 6 KB articles, 12 tickets across every status/priority (including an
 * SLA-breach demo), threads with internal notes, and a few notifications.
 */

define('APP_ROOT', __DIR__);
define('CONFIG_FILE', APP_ROOT . '/data/config.php');

if (!is_file(CONFIG_FILE)) {
    fwrite(STDERR, "Daem is not installed yet. Open install.php in your browser first.\n");
    exit(1);
}
$config = require CONFIG_FILE;

require_once APP_ROOT . '/includes/db.php';
Database::init($config['db']);

$force = in_array('--force', $argv ?? [], true) || isset($_GET['force']);

$ticketCount = (int) Database::value('SELECT COUNT(*) FROM tickets');
if ($ticketCount > 0 && !$force) {
    echo "Tickets already exist (" . $ticketCount . "). Use --force to add demo data anyway.\n";
    exit(0);
}

function seedUser(string $name, string $username, string $email, string $password, string $role, string $department): int
{
    $existing = Database::value('SELECT id FROM users WHERE username = ?', [$username]);
    if ($existing) {
        return (int) $existing;
    }
    return Database::insert(
        'INSERT INTO users (name, username, email, password_hash, role, department, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
        [$name, $username, $email, password_hash($password, PASSWORD_DEFAULT), $role, $department, date('Y-m-d H:i:s', strtotime('-60 days'))]
    );
}

function seedCategory(string $name, string $description, int $sort): int
{
    $existing = Database::value('SELECT id FROM categories WHERE name = ?', [$name]);
    if ($existing) {
        return (int) $existing;
    }
    return Database::insert(
        'INSERT INTO categories (name, description, active, sort_order) VALUES (?, ?, 1, ?)',
        [$name, $description, $sort]
    );
}

function seedKbCategory(string $name, string $description, int $sort): int
{
    $existing = Database::value('SELECT id FROM kb_categories WHERE name = ?', [$name]);
    if ($existing) {
        return (int) $existing;
    }
    return Database::insert('INSERT INTO kb_categories (name, description, sort_order) VALUES (?, ?, ?)', [$name, $description, $sort]);
}

function dt(string $offset): string { return date('Y-m-d H:i:s', strtotime($offset)); }

// ---------------------------------------------------------------- users
$agentOmar   = seedUser('Omar Ali', 'omar.ali', 'omar.ali@company.com', 'Agent@1234', 'agent', 'IT');
$agentFatima = seedUser('Fatima Noor', 'fatima.noor', 'fatima.noor@company.com', 'Agent@1234', 'agent', 'IT');
$khalid      = seedUser('Khalid Salem', 'khalid.salem', 'khalid.salem@company.com', 'User@1234', 'user', 'Finance');
$sara        = seedUser('Sara Ahmed', 'sara.ahmed', 'sara.ahmed@company.com', 'User@1234', 'user', 'HR');
$nasser      = seedUser('Nasser Qahtani', 'nasser.qahtani', 'nasser.qahtani@company.com', 'User@1234', 'user', 'Operations');

// ---------------------------------------------------------------- ticket categories
$cHardware = seedCategory('Hardware', 'Laptops, printers, phones and peripherals', 1);
$cSoftware = seedCategory('Software', 'Applications, licenses and installs', 2);
$cNetwork  = seedCategory('Network & Wi-Fi', 'Connectivity, VPN and firewalls', 3);
$cEmail    = seedCategory('Email & Accounts', 'Mailboxes, signatures and aliases', 4);
$cAccess   = seedCategory('Access & Permissions', 'Folder access, systems and approvals', 5);
$cSecurity = seedCategory('Security', 'Phishing, incidents and antivirus', 6);
seedCategory('Other', 'Anything else', 7);

// ---------------------------------------------------------------- knowledge base
$kbStart = seedKbCategory('Getting Started', 'First steps for new employees', 1);
$kbAccounts = seedKbCategory('Accounts & Passwords', 'Everything about your accounts', 2);
$kbNet = seedKbCategory('Network & VPN', 'Connecting from office and home', 3);
seedKbCategory('Email', 'Outlook and mailbox help', 4);
seedKbCategory('Security', 'Keeping your devices safe', 5);

function seedArticle(int $catId, string $title, string $body, ?int $author, int $views, int $helpful, int $notHelpful, string $offset, bool $published = true): void
{
    if (Database::value('SELECT COUNT(*) FROM kb_articles WHERE title = ?', [$title])) {
        return;
    }
    Database::exec(
        'INSERT INTO kb_articles (category_id, title, slug, body, author_id, published, views, helpful, not_helpful, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $catId, $title, strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-')), $body,
            $author ?: null, $published ? 1 : 0, $views, $helpful, $notHelpful, dt($offset), dt($offset),
        ]
    );
}

seedArticle($kbStart, 'Welcome to the IT Service Desk', "This is how you get help with any IT problem.\n\n1. Sign in to the help desk with your company account.\n2. Open New Ticket and describe your issue.\n3. Pick the right category and priority — urgent means you are completely blocked.\n4. A support agent responds within the SLA target of your priority.\n5. Follow the conversation on the ticket page any time.\n\nTip: most common questions are already answered in the Knowledge Base.", $agentOmar, 342, 38, 2, '-45 days');
seedArticle($kbAccounts, 'How to reset your password', "If you forgot your password, open a ticket with the category Access & Permissions and an administrator will reset it for you.\n\nYour temporary password must be changed on first sign-in from My Profile → Change password.\n\nPassword rules:\n- At least 8 characters\n- Different from your last 3 passwords\n- Never share it with anyone — IT will never ask for it", $agentFatima, 518, 61, 4, '-40 days');
seedArticle($kbNet, 'Connecting to the VPN from home', "Use the VPN client installed on your laptop.\n\n1. Open the VPN client and select the HQ profile.\n2. Sign in with your network account (not your email).\n3. Approve the multi-factor prompt on your phone.\n4. Wait for the Connected status before opening internal systems.\n\nIf it fails, check that your home Wi-Fi works, restart the client, and try again. Still stuck? Open a ticket with category Network & Wi-Fi.", $agentOmar, 277, 33, 6, '-33 days');
seedArticle($kbAccounts, 'Setting up multi-factor authentication (MFA)', "MFA protects your account even if your password leaks.\n\n1. Install the authenticator app on your phone.\n2. Open My Profile → Security in your company portal.\n3. Scan the QR code shown on screen.\n4. Enter the 6-digit code to confirm.\n\nFrom now on, sign-ins require the code from your phone. If you get a new phone, open a ticket before you factory-reset the old one.", $agentFatima, 405, 49, 3, '-21 days');
seedArticle($kbNet, 'Fixing slow Wi-Fi in the office', "Slow Wi-Fi is usually one of three things:\n\n- Too many devices on the same access point — move to another area.\n- VPN left connected while on-site — disconnect it.\n- An old Wi-Fi driver — check the update center.\n\nRun a speed test and attach a screenshot to your ticket if you still have problems.", $agentOmar, 189, 17, 5, '-12 days');
seedArticle($kbStart, 'Requesting a new laptop or software', "Equipment and software are provisioned through the help desk.\n\n1. Open a ticket with category Hardware (equipment) or Software (applications).\n2. Include the reason and your manager's approval if the item is non-standard.\n3. Standard laptops ship within 5 working days.\n\nYour manager can track the ticket at any time.", $agentOmar, 233, 21, 8, '-6 days');

// ---------------------------------------------------------------- tickets
function seedTicket(string $subject, string $description, ?int $catId, string $priority, string $status, int $requester, ?int $assignee, string $createdOffset, ?string $firstResponseOffset = null, ?string $resolvedOffset = null, ?string $closedOffset = null, ?int $overrideSlaHours = null): int
{
    $created = dt($createdOffset);
    $priority = $priority;
    $slaHours = $overrideSlaHours ?? (float) Database::value('SELECT response_hours FROM sla WHERE priority = ?', [$priority]);
    $slaDue = date('Y-m-d H:i:s', strtotime($created) + (int) round($slaHours * 3600));
    $id = Database::insert(
        'INSERT INTO tickets (subject, description, category_id, priority, status, requester_id, assignee_id, first_response_at, resolved_at, closed_at, sla_due, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $subject, $description, $catId, $priority, $status, $requester, $assignee,
            $firstResponseOffset ? dt($firstResponseOffset) : null,
            $resolvedOffset ? dt($resolvedOffset) : null,
            $closedOffset ? dt($closedOffset) : null,
            $slaDue, $created, $closedOffset ? dt($closedOffset) : ($resolvedOffset ? dt($resolvedOffset) : dt($createdOffset)),
        ]
    );
    $ref = strtoupper((string) Database::value('SELECT `value` FROM settings WHERE `key` = ?', ['ticket_prefix']));
    $ref .= '-' . date('Y') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    Database::exec('UPDATE tickets SET ref = ? WHERE id = ?', [$ref, $id]);
    return $id;
}

function seedReply(int $ticketId, int $authorId, string $body, string $offset, bool $internal = false): void
{
    Database::exec(
        'INSERT INTO ticket_replies (ticket_id, author_id, body, is_internal, created_at) VALUES (?, ?, ?, ?, ?)',
        [$ticketId, $authorId, $body, $internal ? 1 : 0, dt($offset)]
    );
}

// 1 — urgent, open, UNASSIGNED, 3 days old → SLA response breach demo
$t1 = seedTicket('Cannot access the trading system — production down for me', "When I open the trading system I get 'connection refused'. I restarted twice. The whole finance floor seems affected.\n\nThis blocks my daily close.", $cNetwork, 'urgent', 'open', $khalid, null, '-3 days');
seedReply($t1, $khalid, "Any update? I have been blocked all morning.", '-2 days');

// 2 — high, new
seedTicket('New joiner needs laptop + access before Monday', "Please provision a standard laptop for a new analyst starting Monday. He also needs email, the shared drive and the BI tool access.\n\nManager approval attached in the ticket below.", $cHardware, 'high', 'new', $sara, null, '-6 hours');

// 3 — medium, in_progress, with thread + internal note
$t3 = seedTicket('Excel crashes when opening the monthly report', "Excel closes immediately when I open the 40 MB monthly report file. Smaller files work fine. I need this for the management pack on Thursday.", $cSoftware, 'medium', 'in_progress', $khalid, $agentOmar, '-2 days', '-2 days +35 minutes');
seedReply($t3, $agentOmar, "Hi Khalid — could you send me the exact file or its path on the shared drive? I will test the 64-bit build.", '-2 days +40 minutes');
seedReply($t3, $khalid, "It is on the shared drive: Finance/Reports/2026/Monthly_Pack_v3.xlsx", '-2 days +2 hours');
seedReply($t3, $agentOmar, "The file is fine — the issue is your 32-bit Excel. We will push the 64-bit upgrade tonight after hours.", '-1 day +1 hour', true);

// 4 — low, waiting on user
$t4 = seedTicket('Second monitor not detected', "After the desk move my second monitor shows 'no signal'. The cable looks connected.", $cHardware, 'low', 'waiting', $sara, $agentFatima, '-5 days', '-5 days +2 hours');
seedReply($t4, $agentFatima, "Could you check that the cable is plugged into the display port (the square one), not HDMI? Also press Win+P and choose Extend.", '-5 days +2 hours');
seedReply($t4, $sara, "It was in the wrong port — fixed, thank you!", '-5 days +5 hours');

// 5 — medium, resolved
$t5 = seedTicket('VPN keeps disconnecting every few minutes', "The VPN drops every 5-10 minutes while I work from home. It reconnects automatically but breaks my remote sessions.", $cNetwork, 'medium', 'resolved', $nasser, $agentOmar, '-10 days', '-10 days +45 minutes', '-9 days');
seedReply($t5, $agentOmar, "Your home router is on an old firmware that drops the keep-alive. Please update it, then test for a full hour and confirm.", '-10 days +1 hour');
seedReply($t5, $nasser, "Router updated — two hours without a single drop. All good.", '-9 days');
seedReply($t5, $agentOmar, "Great, marking as resolved. Reopen if it comes back.", '-9 days +30 minutes');

// 6 — low, closed
seedTicket('Printer on floor 3 prints blank pages', "The floor-3 printer outputs blank pages since yesterday. Toner was replaced last week.", $cHardware, 'low', 'closed', $sara, $agentFatima, '-20 days', '-20 days +3 hours', '-19 days', '-18 days');
// 7 — high, open, assigned
seedTicket('Phishing email received — looks targeted', "I received an email pretending to be the CEO asking me to buy gift cards. I did not click anything. Flagging in case others got it.", $cSecurity, 'high', 'open', $khalid, $agentFatima, '-1 day', '-1 day +55 minutes');
// 8 — medium, new
seedTicket('Request access to the HR shared drive', "I need read access to the HR policies folder for the audit preparation.", $cAccess, 'medium', 'new', $nasser, null, '-2 hours');
// 9 — urgent, resolved
$t9 = seedTicket('Server room temperature alarm', "The monitoring console shows 33°C in server room B. Please check the cooling.", $cHardware, 'urgent', 'resolved', $nasser, $agentOmar, '-7 days', '-7 days +20 minutes', '-7 days +1 hour');
seedReply($t9, $agentOmar, "One of the two cooling units tripped — reset it, temperature back to 21°C. Will schedule maintenance.", '-7 days +30 minutes');
// 10 — low, waiting
seedTicket('Keyboard is sticky — some keys double-type', "The K key on my keyboard sometimes types twice. Not urgent but annoying.", $cHardware, 'low', 'waiting', $sara, $agentOmar, '-4 days', '-4 days +4 hours');
// 11 — medium, open
$t11 = seedTicket('Shared mailbox shows old signature', "The customer-care mailbox still shows the old company logo in the signature. Please update it to the new branding.", $cEmail, 'medium', 'open', $sara, $agentFatima, '-3 days', '-3 days +1 hour');
seedReply($t11, $agentFatima, "On it — branding package received from Marketing, will apply today.", '-3 days +1 hour');
// 12 — high, closed
seedTicket('Account lockout after password change', "My account locked out right after I changed my password. Cannot sign in at all.", $cAccess, 'high', 'closed', $khalid, $agentOmar, '-30 days', '-30 days +15 minutes', '-30 days +1 hour', '-30 days +2 hours');

// ---------------------------------------------------------------- notifications for admin
$adminId = (int) Database::value("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
if ($adminId && !Database::value('SELECT COUNT(*) FROM notifications')) {
    Database::exec(
        'INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, ?)',
        [$adminId, 'New ticket ' . Database::value('SELECT ref FROM tickets WHERE id = ?', [$t1]) . ': Cannot access the trading system', 'index.php?p=ticket&id=' . $t1, dt('-3 days')]
    );
    Database::exec(
        'INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, ?)',
        [$adminId, 'New ticket ' . Database::value('SELECT ref FROM tickets WHERE id = ?', [$t11]) . ': Shared mailbox shows old signature', 'index.php?p=ticket&id=' . $t11, dt('-3 days')]
    );
    Database::exec(
        'INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 1, ?)',
        [$adminId, 'Ticket ' . Database::value('SELECT ref FROM tickets WHERE id = ?', [$t5]) . ' was resolved', 'index.php?p=ticket&id=' . $t5, dt('-9 days')]
    );
}

echo "Demo data ready.\n";
echo "  Agents:      omar.ali / Agent@1234 · fatima.noor / Agent@1234\n";
echo "  Requesters:  khalid.salem · sara.ahmed · nasser.qahtani / User@1234\n";
echo "  Tickets:     12 across all statuses (incl. 1 SLA-breach demo) · KB: 6 articles\n";
