<?php
declare(strict_types=1);

/**
 * Sanad — demo data seeder.
 * Run:  php seed.php          (refuses when jobs already exist)
 *       php seed.php --force
 *
 * Seeds: dispatcher + 3 technicians + accountant, 8 customers with 10 service
 * addresses, 8 services, 12 parts, 5 maintenance contracts with visit plans,
 * 26 jobs (all statuses, including late and contract jobs), checklists, parts
 * used, work notes and 9 invoices with payments.
 */

define('APP_ROOT', __DIR__);
define('CONFIG_FILE', APP_ROOT . '/data/config.php');

if (!is_file(CONFIG_FILE)) {
    fwrite(STDERR, "Sanad is not installed yet. Open install.php in your browser first.\n");
    exit(1);
}
$config = require CONFIG_FILE;

require_once APP_ROOT . '/includes/db.php';
Database::init($config['db']);

$force = in_array('--force', $argv ?? [], true) || isset($_GET['force']);
$count = (int) Database::value('SELECT COUNT(*) FROM jobs');
if ($count > 0 && !$force) {
    echo "Jobs already exist ({$count}). Use --force to add demo data anyway.\n";
    exit(0);
}

function ts(string $offset): string { return date('Y-m-d H:i:s', strtotime($offset)); }
function ds(string $offset): string { return date('Y-m-d', strtotime($offset)); }

function seedUser(string $name, string $username, string $email, string $role, string $phone, string $colour = ''): int
{
    $id = Database::value('SELECT id FROM users WHERE username = ?', [$username]);
    if ($id) {
        return (int) $id;
    }
    return Database::insert(
        'INSERT INTO users (name, username, email, password_hash, role, phone, colour, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
        [$name, $username, $email, password_hash('Demo@1234', PASSWORD_DEFAULT), $role, $phone, $colour, ts('-120 days')]
    );
}

// ---------------------------------------------------------------- users
$dispatcher = seedUser('Noura Al Hashimi', 'noura.dispatch', 'noura@sanad.local', 'dispatcher', '+971 50 111 2233');
$tech1 = seedUser('Ahmed Saleh', 'ahmed.tech', 'ahmed@sanad.local', 'technician', '+971 50 222 3344', '#2563eb');
$tech2 = seedUser('Bilal Karim', 'bilal.tech', 'bilal@sanad.local', 'technician', '+971 50 333 4455', '#f97316');
$tech3 = seedUser('Sameer Naji', 'sameer.tech', 'sameer@sanad.local', 'technician', '+971 50 444 5566', '#059669');
seedUser('Rana Yusuf', 'rana.accounts', 'rana@sanad.local', 'accountant', '+971 50 555 6677');

// ---------------------------------------------------------------- services
$serviceDefs = [
    ['AC-SRV', 'A/C general service', 'Cleaning of filters, coils and drain line. Check of gas pressure.', 250.00, 90],
    ['AC-GAS', 'A/C gas refill', 'Refill of refrigerant R410a and leak test.', 380.00, 120],
    ['AC-INS', 'A/C installation', 'Installation of a split unit up to 2 tonnes.', 650.00, 240],
    ['PLB-REP', 'Plumbing repair', 'Repair of leaks, taps and water connections.', 220.00, 90],
    ['ELC-CHK', 'Electrical check', 'Check of the distribution board, sockets and earthing.', 300.00, 120],
    ['CLN-DEP', 'Deep cleaning', 'Deep cleaning of kitchen, bathrooms and floors.', 550.00, 300],
    ['PST-CTL', 'Pest control', 'Treatment against insects and rodents, with report.', 400.00, 120],
    ['AMC-VIS', 'Maintenance visit (contract)', 'Planned visit of a maintenance contract.', 200.00, 60],
];
$services = [];
foreach ($serviceDefs as $i => [$code, $name, $desc, $price, $dur]) {
    $services[$code] = Database::insert(
        'INSERT INTO services (code, name, description, price, duration_min, active, sort_order) VALUES (?, ?, ?, ?, ?, 1, ?)',
        [$code, $name, $desc, $price, $dur, $i + 1]
    );
}

// ---------------------------------------------------------------- parts
$partDefs = [
    ['FLT-AC-01', 'A/C filter 60x60', 'pc', 40, 8, 25.00, 60.00],
    ['GAS-R410', 'Refrigerant R410a 1kg', 'kg', 22, 6, 90.00, 150.00],
    ['PIP-CU-14', 'Copper pipe 1/4 inch (per metre)', 'm', 60, 15, 12.00, 25.00],
    ['HOS-DRN-01', 'Drain hose 5 m', 'pc', 30, 8, 15.00, 35.00],
    ['BRK-20A', 'Circuit breaker 20A', 'pc', 18, 5, 28.00, 55.00],
    ['PLG-WAL-01', 'Wall plug set', 'set', 8, 10, 6.00, 15.00],
    ['SIL-TUB-01', 'Silicone tube', 'pc', 5, 6, 9.00, 22.00],
    ['RMT-AC-UNI', 'Universal A/C remote', 'pc', 12, 4, 30.00, 70.00],
    ['CAP-35UF', 'Capacitor 35 µF', 'pc', 25, 6, 18.00, 45.00],
    ['CLN-LIQ-5L', 'Cleaning liquid 5 L', 'pc', 14, 5, 45.00, 95.00],
    ['TAP-CRT-01', 'Cartridge for mixer tap', 'pc', 20, 6, 22.00, 50.00],
    ['INS-TAPE-01', 'Insulation tape (roll)', 'pc', 3, 10, 4.00, 12.00],
];
foreach ($partDefs as [$sku, $name, $unit, $stock, $reorder, $cost, $sell]) {
    Database::insert(
        'INSERT INTO parts (sku, name, unit, stock_qty, reorder_level, cost_price, sell_price, active, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
        [$sku, $name, $unit, $stock, $reorder, $cost, $sell, ts('-200 days'), ts('-20 days')]
    );
}

// ---------------------------------------------------------------- customers + sites
$customerDefs = [
    ['Al Noor Trading LLC', 'company', '+971 4 555 1001', 'info@alnoor.example', 'Sheikh Zayed Road, Building 4', 'Dubai', '100123456700003'],
    ['Emirates Star Facilities', 'company', '+971 4 555 1002', 'facility@estar.example', 'Business Bay, Tower B', 'Dubai', '100123456700004'],
    ['Gulf Coast Restaurant', 'company', '+971 4 555 1003', 'manager@gulfcoast.example', 'Jumeirah Beach Road 12', 'Dubai', '100123456700005'],
    ['Dr. Hana Clinic', 'company', '+971 4 555 1004', 'admin@hanaclinic.example', 'Al Wasl Road 88', 'Dubai', '100123456700006'],
    ['Mr. Khalid Al Mansoori', 'individual', '+971 50 777 8899', 'khalid.m@example.com', 'Villa 12, Al Barsha 2', 'Dubai', ''],
    ['Ms. Aisha Rahman', 'individual', '+971 55 666 7788', 'aisha.r@example.com', 'Apartment 704, Marina Gate 1', 'Dubai', ''],
    ['Sunrise Nursery', 'company', '+971 4 555 1007', 'office@sunrisenursery.example', 'Al Nahda Street 5', 'Sharjah', '100123456700008'],
    ['City Mart Supermarket', 'company', '+971 6 555 1008', 'store@citymart.example', 'King Faisal Street 21', 'Sharjah', '100123456700009'],
];
$customers = [];
$customerCols = [];
foreach ($customerDefs as $i => [$name, $type, $phone, $email, $address, $city, $tax]) {
    $id = Database::insert(
        'INSERT INTO customers (code, name, type, email, phone, address, city, tax_no, notes, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
        [
            'CUS-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT), $name, $type, $email, $phone, $address, $city, $tax,
            $name === 'Mr. Khalid Al Mansoori' ? 'Prefers visits after 5 pm.' : '', ts('-' . (250 - $i * 10) . ' days'),
        ]
    );
    $customers[$name] = $id;
    $customerCols[$name] = [$address, $city, $phone];
}

$siteDefs = [
    ['Al Noor Trading LLC', 'Head office — Building 4', 'Reception, 3rd floor', 'Gate code 4455, ask for the facility manager'],
    ['Al Noor Trading LLC', 'Warehouse — Al Quoz', 'Warehouse 7', 'Call 10 minutes before arrival'],
    ['Emirates Star Facilities', 'Tower B — floors 8 to 12', 'Facility office, floor 8', 'Sign in at security, badge required'],
    ['Gulf Coast Restaurant', 'Restaurant — main kitchen', 'Chef Imran', 'Back door, entrance from the parking'],
    ['Dr. Hana Clinic', 'Clinic — reception', 'Nurse Maryam', 'No work between 10:00 and 12:00 (patients)'],
    ['Mr. Khalid Al Mansoori', 'Villa 12 — Al Barsha 2', 'Mr. Khalid', 'Gate code 7788, dog in the garden'],
    ['Ms. Aisha Rahman', 'Apartment 704 — Marina Gate 1', 'Ms. Aisha', 'Ask at reception for the key'],
    ['Sunrise Nursery', 'Nursery — main building', 'Ms. Fatima', 'Work only after 15:00 (children sleeping)'],
    ['City Mart Supermarket', 'Store — sales area', 'Mr. Yasir', 'Night work possible after 23:00'],
    ['Emirates Star Facilities', 'Villa 22 — Jumeirah 3', 'Mr. Omar', 'Guard has the key'],
];
$sites = [];
foreach ($siteDefs as [$customer, $name, $contact, $access]) {
    [$address, $city, $phone] = $customerCols[$customer];
    $sites[$name] = Database::insert(
        'INSERT INTO sites (customer_id, name, address, city, contact_name, contact_phone, access_notes, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
        [$customers[$customer], $name, $address, $city, $contact, $phone, $access, ts('-150 days')]
    );
}

// ---------------------------------------------------------------- maintenance contracts
function seedContract(int $customerId, int $siteId, int $serviceId, string $frequency, float $price, string $start, string $end, string $notes): int
{
    static $seq = 0;
    $seq++;
    $id = Database::insert(
        'INSERT INTO contracts (number, customer_id, site_id, service_id, frequency, price_per_visit, start_date, end_date, visits_total, status, notes, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, \'active\', ?, ?)',
        ['AMC-' . date('Y') . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT), $customerId, $siteId, $serviceId, $frequency, $price, $start, $end, $notes, ts('-90 days')]
    );
    $months = ['monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'annual' => 12][$frequency];
    $due = $start;
    $guard = 0;
    while (strtotime($due) <= strtotime($end) && $guard < 24) {
        $guard++;
        Database::insert('INSERT INTO contract_visits (contract_id, due_date, created_at) VALUES (?, ?, ?)', [$id, $due, ts('-90 days')]);
        $due = date('Y-m-d', strtotime($due . ' +' . $months . ' month'));
    }
    Database::exec('UPDATE contracts SET visits_total = (SELECT COUNT(*) FROM contract_visits WHERE contract_id = ?) WHERE id = ?', [$id, $id]);
    return $id;
}

$contracts = [
    'alnoor' => seedContract($customers['Al Noor Trading LLC'], $sites['Head office — Building 4'], $services['AMC-VIS'], 'quarterly', 900.00, ds('-270 days'), ds('+95 days'), 'All A/C units of the head office. Filter change included.'),
    'estar'  => seedContract($customers['Emirates Star Facilities'], $sites['Tower B — floors 8 to 12'], $services['AMC-VIS'], 'monthly', 1500.00, ds('-150 days'), ds('+215 days'), 'Monthly check of 12 units and the chilled water pumps.'),
    'clinic' => seedContract($customers['Dr. Hana Clinic'], $sites['Clinic — reception'], $services['AMC-VIS'], 'semiannual', 700.00, ds('-100 days'), ds('+265 days'), 'Two visits per year, including the sterilisation room.'),
    'nursery' => seedContract($customers['Sunrise Nursery'], $sites['Nursery — main building'], $services['AMC-VIS'], 'quarterly', 600.00, ds('-60 days'), ds('+305 days'), 'Pest control every 3 months, safe products only.'),
    'mart'   => seedContract($customers['City Mart Supermarket'], $sites['Store — sales area'], $services['AMC-VIS'], 'quarterly', 1100.00, ds('-200 days'), ds('+165 days'), 'Cooling units of the cold room and the sales area.'),
];

// ---------------------------------------------------------------- jobs
function seedJob(array $j): int
{
    static $seq = 0;
    $seq++;
    $id = Database::insert(
        'INSERT INTO jobs (number, customer_id, site_id, service_id, contract_id, type, priority, status, assigned_to,
                           scheduled_date, window_start, window_end, title, description, internal_notes,
                           completed_at, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            'JOB-' . date('Y') . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
            $j['customer_id'], $j['site_id'] ?? null, $j['service_id'] ?? null, $j['contract_id'] ?? null,
            $j['type'] ?? 'one_time', $j['priority'] ?? 'normal', $j['status'] ?? 'new', $j['assigned_to'] ?? null,
            $j['scheduled_date'] ?? null, $j['window_start'] ?? null, $j['window_end'] ?? null,
            $j['title'], $j['description'] ?? '', $j['internal_notes'] ?? '',
            $j['completed_at'] ?? null, 1, $j['created_at'] ?? ts('-10 days'), $j['updated_at'] ?? ts('-2 days'),
        ]
    );
    return $id;
}

$today = date('Y-m-d');
$jobIds = [];
$jobIds['today1'] = seedJob([
    'customer_id' => $customers['Al Noor Trading LLC'], 'site_id' => $sites['Head office — Building 4'],
    'service_id' => $services['AC-SRV'], 'priority' => 'normal', 'status' => 'in_progress', 'assigned_to' => $tech1,
    'scheduled_date' => $today, 'window_start' => '09:00', 'window_end' => '11:00',
    'title' => 'A/C general service — 6 units, 3rd floor',
    'description' => "Clean the filters and the drain line of all 6 units.\nCheck the gas pressure of unit 4 (weak cooling).",
    'internal_notes' => 'Customer asked for a report after the visit.',
]);
$jobIds['today2'] = seedJob([
    'customer_id' => $customers['Gulf Coast Restaurant'], 'site_id' => $sites['Restaurant — main kitchen'],
    'service_id' => $services['PLB-REP'], 'priority' => 'high', 'status' => 'scheduled', 'assigned_to' => $tech2,
    'scheduled_date' => $today, 'window_start' => '14:00', 'window_end' => '16:00',
    'title' => 'Water leak under the kitchen sink',
    'description' => 'The restaurant called: water under the sink since yesterday. Check the siphon and the pipe.',
]);
$jobIds['today3'] = seedJob([
    'customer_id' => $customers['Ms. Aisha Rahman'], 'site_id' => $sites['Apartment 704 — Marina Gate 1'],
    'service_id' => $services['AC-GAS'], 'priority' => 'normal', 'status' => 'scheduled', 'assigned_to' => $tech3,
    'scheduled_date' => $today, 'window_start' => '17:00', 'window_end' => '19:00',
    'title' => 'A/C not cooling — gas refill',
    'description' => 'Gas refill and leak test for the bedroom unit.',
]);
$jobIds['today4'] = seedJob([
    'customer_id' => $customers['City Mart Supermarket'], 'site_id' => $sites['Store — sales area'],
    'service_id' => $services['ELC-CHK'], 'priority' => 'urgent', 'status' => 'new',
    'scheduled_date' => $today, 'window_start' => '20:00', 'window_end' => '22:00',
    'title' => 'Electricity goes off in the cold room',
    'description' => 'The cold room loses power 2–3 times a day. Check the breaker and the cable.',
    'internal_notes' => 'Urgent — the shop loses goods. Call the customer before going.',
]);
$jobIds['late1'] = seedJob([
    'customer_id' => $customers['Dr. Hana Clinic'], 'site_id' => $sites['Clinic — reception'],
    'service_id' => $services['AC-SRV'], 'priority' => 'high', 'status' => 'scheduled', 'assigned_to' => $tech1,
    'scheduled_date' => ds('-3 days'), 'window_start' => '10:00', 'window_end' => '12:00',
    'title' => 'A/C service — reception and sterilisation room',
    'description' => 'Planned service that was postponed by the customer.',
]);
$jobIds['late2'] = seedJob([
    'customer_id' => $customers['Mr. Khalid Al Mansoori'], 'site_id' => $sites['Villa 12 — Al Barsha 2'],
    'service_id' => $services['PST-CTL'], 'priority' => 'normal', 'status' => 'scheduled', 'assigned_to' => $tech2,
    'scheduled_date' => ds('-1 days'), 'window_start' => '18:00', 'window_end' => '20:00',
    'title' => 'Pest control — garden and kitchen',
    'description' => 'Ants in the kitchen. Treatment of the garden and the kitchen.',
]);
$jobIds['done1'] = seedJob([
    'customer_id' => $customers['Emirates Star Facilities'], 'site_id' => $sites['Tower B — floors 8 to 12'],
    'service_id' => $services['AC-SRV'], 'priority' => 'normal', 'status' => 'done', 'assigned_to' => $tech1,
    'scheduled_date' => ds('-6 days'), 'window_start' => '08:00', 'window_end' => '12:00',
    'title' => 'A/C service — floors 8 and 9',
    'description' => 'Service of 8 units in the open office.',
    'completed_at' => ts('-6 days +5 hours'),
]);
$jobIds['done2'] = seedJob([
    'customer_id' => $customers['Gulf Coast Restaurant'], 'site_id' => $sites['Restaurant — main kitchen'],
    'service_id' => $services['CLN-DEP'], 'priority' => 'normal', 'status' => 'done', 'assigned_to' => $tech3,
    'scheduled_date' => ds('-9 days'), 'window_start' => '22:00', 'window_end' => '23:30',
    'title' => 'Deep cleaning — kitchen and store',
    'description' => 'Deep cleaning after closing time.',
    'completed_at' => ts('-9 days +2 hours'),
]);
$jobIds['done3'] = seedJob([
    'customer_id' => $customers['Al Noor Trading LLC'], 'site_id' => $sites['Warehouse — Al Quoz'],
    'service_id' => $services['ELC-CHK'], 'priority' => 'normal', 'status' => 'done', 'assigned_to' => $tech2,
    'scheduled_date' => ds('-14 days'), 'window_start' => '09:00', 'window_end' => '11:00',
    'title' => 'Electrical check — warehouse',
    'description' => 'Check of the distribution board and the socket circuits.',
    'completed_at' => ts('-14 days +3 hours'),
]);
$jobIds['done4'] = seedJob([
    'customer_id' => $customers['Sunrise Nursery'], 'site_id' => $sites['Nursery — main building'],
    'service_id' => $services['PST-CTL'], 'priority' => 'low', 'status' => 'done', 'assigned_to' => $tech3,
    'scheduled_date' => ds('-20 days'), 'window_start' => '15:30', 'window_end' => '17:00',
    'title' => 'Pest control — classrooms and kitchen',
    'description' => 'Safe treatment, no chemicals in the classrooms during the day.',
    'completed_at' => ts('-20 days +2 hours'),
]);
$jobIds['plan1'] = seedJob([
    'customer_id' => $customers['Emirates Star Facilities'], 'site_id' => $sites['Villa 22 — Jumeirah 3'],
    'service_id' => $services['AC-GAS'], 'priority' => 'normal', 'status' => 'scheduled', 'assigned_to' => $tech1,
    'scheduled_date' => ds('+2 days'), 'window_start' => '09:00', 'window_end' => '11:00',
    'title' => 'Gas refill — villa, 3 units',
    'description' => 'Refill and leak test for 3 split units.',
]);
$jobIds['plan2'] = seedJob([
    'customer_id' => $customers['City Mart Supermarket'], 'site_id' => $sites['Store — sales area'],
    'service_id' => $services['AC-SRV'], 'priority' => 'normal', 'status' => 'scheduled', 'assigned_to' => $tech2,
    'scheduled_date' => ds('+3 days'), 'window_start' => '23:00', 'window_end' => '01:00',
    'title' => 'A/C service — sales area (night work)',
    'description' => 'Service of the ceiling units after closing time.',
]);
$jobIds['plan3'] = seedJob([
    'customer_id' => $customers['Ms. Aisha Rahman'], 'site_id' => $sites['Apartment 704 — Marina Gate 1'],
    'service_id' => $services['CLN-DEP'], 'priority' => 'low', 'status' => 'new',
    'scheduled_date' => ds('+5 days'), 'window_start' => '10:00', 'window_end' => '14:00',
    'title' => 'Deep cleaning before moving in',
    'description' => 'Full cleaning of the apartment before the family moves in.',
]);
$jobIds['hold1'] = seedJob([
    'customer_id' => $customers['Dr. Hana Clinic'], 'site_id' => $sites['Clinic — reception'],
    'service_id' => $services['AC-INS'], 'priority' => 'normal', 'status' => 'on_hold', 'assigned_to' => $tech3,
    'scheduled_date' => ds('+7 days'), 'window_start' => '13:00', 'window_end' => '17:00',
    'title' => 'Installation of a new split unit',
    'description' => 'New unit for the sterilisation room.',
    'internal_notes' => 'On hold: the customer must first finish the wall work.',
]);
$jobIds['cancel1'] = seedJob([
    'customer_id' => $customers['Sunrise Nursery'], 'site_id' => $sites['Nursery — main building'],
    'service_id' => $services['CLN-DEP'], 'priority' => 'low', 'status' => 'cancelled',
    'scheduled_date' => ds('-5 days'),
    'title' => 'Deep cleaning — cancelled by the customer',
    'description' => 'The customer cancelled because the school was closed.',
]);

// contract visit jobs (make the plans real)
$visitRows = Database::all("SELECT v.id, v.contract_id, v.due_date, ct.customer_id, ct.site_id, ct.service_id, ct.number FROM contract_visits v JOIN contracts ct ON ct.id = v.contract_id WHERE v.due_date <= ? AND v.job_id IS NULL ORDER BY v.due_date ASC LIMIT 3", [$today]);
$contractJobs = [];
foreach ($visitRows as $i => $visit) {
    $tech = [$tech1, $tech2, $tech3][$i % 3];
    $title = 'Contract visit — ' . $visit['number'];
    $jobId = seedJob([
        'customer_id' => (int) $visit['customer_id'], 'site_id' => $visit['site_id'] ? (int) $visit['site_id'] : null,
        'service_id' => $visit['service_id'] ? (int) $visit['service_id'] : null, 'contract_id' => (int) $visit['contract_id'],
        'type' => 'contract', 'priority' => 'normal',
        'status' => $visit['due_date'] < $today ? 'scheduled' : 'new',
        'assigned_to' => $tech,
        'scheduled_date' => $visit['due_date'],
        'window_start' => '08:00', 'window_end' => '10:00',
        'title' => $title,
        'description' => 'Planned visit of maintenance contract ' . $visit['number'] . '.',
    ]);
    Database::exec('UPDATE contract_visits SET job_id = ? WHERE id = ?', [$jobId, (int) $visit['id']]);
    $contractJobs[] = $jobId;
}

// ---------------------------------------------------------------- checklists
$checklistTemplates = [
    'A/C general service' => ['Switch off the power', 'Open and clean the filters', 'Clean the coils', 'Check and clean the drain line', 'Measure the gas pressure', 'Test cooling for 10 minutes', 'Clean the work area'],
    'A/C gas refill'      => ['Leak test with soap water', 'Check the pressure before filling', 'Fill the refrigerant', 'Check the pressure after filling', 'Run the unit for 15 minutes'],
    'Plumbing repair'     => ['Close the main water valve', 'Find the leak', 'Replace the broken part', 'Open the valve and test', 'Dry and clean the area'],
    'Electrical check'    => ['Switch off the main breaker', 'Check the board for heat marks', 'Tighten all screws', 'Test every socket', 'Test the earth connection', 'Switch on and check'],
    'Deep cleaning'       => ['Prepare the equipment', 'Clean the ceiling and walls', 'Clean the floors', 'Clean the windows', 'Remove the rubbish', 'Final check with the customer'],
    'Pest control'        => ['Check the infested areas', 'Prepare the safe products', 'Treat the areas', 'Write the safety note', 'Explain the aftercare to the customer'],
];
foreach ($jobIds as $key => $jobId) {
    $job = Database::one('SELECT j.id, s.name AS service_name FROM jobs j LEFT JOIN services s ON s.id = j.service_id WHERE j.id = ?', [$jobId]);
    $template = $checklistTemplates[$job['service_name'] ?? ''] ?? null;
    if (!$template) {
        continue;
    }
    $doneCount = in_array($key, ['done1', 'done2', 'done3', 'done4'], true) ? count($template) : ($key === 'today1' ? 3 : 0);
    foreach ($template as $i => $label) {
        Database::insert(
            'INSERT INTO job_checklist (job_id, label, done, sort_order) VALUES (?, ?, ?, ?)',
            [$jobId, $label, $i < $doneCount ? 1 : 0, $i + 1]
        );
    }
}

// ---------------------------------------------------------------- parts used + work notes
function seedPartUse(int $jobId, string $sku, float $qty): void
{
    $part = Database::one('SELECT * FROM parts WHERE sku = ?', [$sku]);
    if (!$part) {
        return;
    }
    Database::insert(
        'INSERT INTO job_parts (job_id, part_id, qty, unit_price, created_at) VALUES (?, ?, ?, ?, ?)',
        [$jobId, (int) $part['id'], $qty, (float) $part['sell_price'], ts('-6 days')]
    );
    Database::exec('UPDATE parts SET stock_qty = stock_qty - ? WHERE id = ?', [$qty, (int) $part['id']]);
}

seedPartUse($jobIds['done1'], 'FLT-AC-01', 8);
seedPartUse($jobIds['done1'], 'CLN-LIQ-5L', 1);
seedPartUse($jobIds['done2'], 'CLN-LIQ-5L', 2);
seedPartUse($jobIds['done3'], 'BRK-20A', 1);
seedPartUse($jobIds['done3'], 'INS-TAPE-01', 2);
seedPartUse($jobIds['done4'], 'CLN-LIQ-5L', 1);
seedPartUse($jobIds['today1'], 'FLT-AC-01', 4);

Database::insert('INSERT INTO job_notes (job_id, user_id, note, status_from, status_to, created_at) VALUES (?, ?, ?, NULL, ?, ?)',
    [$jobIds['done1'], $tech1, "All 8 units cleaned. Unit 5 had a weak gas pressure, I added 200 g.\nFilters of units 2 and 3 were very dirty.", 'in_progress', ts('-6 days +1 hours')]);
Database::insert('INSERT INTO job_notes (job_id, user_id, note, status_from, status_to, created_at) VALUES (?, ?, ?, ?, ?, ?)',
    [$jobIds['done1'], $tech1, 'Work finished, customer signed the report.', 'in_progress', 'done', ts('-6 days +5 hours')]);
Database::insert('INSERT INTO job_notes (job_id, user_id, note, status_from, status_to, created_at) VALUES (?, ?, ?, NULL, ?, ?)',
    [$jobIds['today1'], $tech1, 'Started the work. Units 1 to 3 are clean. Unit 4 needs a gas check.', 'in_progress', ts('-2 hours')]);
Database::insert('INSERT INTO job_notes (job_id, user_id, note, status_from, status_to, created_at) VALUES (?, ?, ?, NULL, NULL, ?)',
    [$jobIds['hold1'], $dispatcher, 'Customer will call us when the wall work is finished.', ts('-4 days')]);

// ---------------------------------------------------------------- invoices + payments
function seedInvoice(int $jobId, string $issued, string $due, float $taxRate, string $status, array $extraItems = [], array $payments = []): int
{
    $recordedBy = (int) Database::value("SELECT id FROM users WHERE role = 'accountant' ORDER BY id ASC LIMIT 1");
    $job = Database::one('SELECT j.*, c.id AS cust_id, c.name AS customer_name, sv.name AS service_name, sv.price AS service_price FROM jobs j LEFT JOIN customers c ON c.id = j.customer_id LEFT JOIN services sv ON sv.id = j.service_id WHERE j.id = ?', [$jobId]);
    static $seq = 0;
    $seq++;
    $id = Database::insert(
        'INSERT INTO invoices (number, job_id, customer_id, issued_at, due_at, subtotal, tax_rate, tax_amount, total, status, notes, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, 0, ?, 0, 0, ?, ?, 1, ?)',
        ['INV-' . date('Y') . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT), $jobId, (int) $job['cust_id'], $issued, $due, $taxRate, $status, 'Job ' . $job['number'], ts($issued)]
    );
    $sort = 1;
    $subtotal = 0.0;
    if ((float) $job['service_price'] > 0) {
        $amount = (float) $job['service_price'];
        Database::insert('INSERT INTO invoice_items (invoice_id, description, qty, unit_price, amount, sort_order) VALUES (?, ?, 1, ?, ?, ?)',
            [$id, (string) $job['service_name'], $amount, $amount, $sort++]);
        $subtotal += $amount;
    }
    foreach ($extraItems as [$desc, $qty, $unit]) {
        $amount = round($qty * $unit, 2);
        Database::insert('INSERT INTO invoice_items (invoice_id, description, qty, unit_price, amount, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $desc, $qty, $unit, $amount, $sort++]);
        $subtotal += $amount;
    }
    $tax = round($subtotal * $taxRate / 100, 2);
    Database::exec('UPDATE invoices SET subtotal = ?, tax_amount = ?, total = ? WHERE id = ?', [$subtotal, $tax, round($subtotal + $tax, 2), $id]);
    $total = round($subtotal + $tax, 2);
    foreach ($payments as [$amount, $method, $paidAt, $reference]) {
        Database::insert('INSERT INTO payments (invoice_id, paid_at, amount, method, reference, recorded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $paidAt, $amount, $method, $reference, $recordedBy ?: null, ts($paidAt)]);
    }
    $paid = array_sum(array_column($payments, 0));
    $finalStatus = $status === 'cancelled' ? 'cancelled' : ($paid <= 0 ? 'unpaid' : ($paid + 0.01 >= $total ? 'paid' : 'partial'));
    Database::exec('UPDATE invoices SET status = ? WHERE id = ?', [$finalStatus, $id]);
    return $id;
}

$inv1 = seedInvoice($jobIds['done1'], ds('-6 days'), ds('+24 days'), 5, 'paid',
    [['A/C filter 60x60 (FLT-AC-01)', 8, 60.00], ['Cleaning liquid 5 L (CLN-LIQ-5L)', 1, 95.00]], [[1300.00, 'transfer', ds('-4 days'), 'TRF-88120']]);
$inv2 = seedInvoice($jobIds['done2'], ds('-9 days'), ds('+21 days'), 5, 'unpaid', [['Cleaning liquid 5 L (CLN-LIQ-5L)', 2, 95.00]]);
$inv3 = seedInvoice($jobIds['done3'], ds('-14 days'), ds('-1 days'), 5, 'partial',
    [['Circuit breaker 20A (BRK-20A)', 1, 55.00], ['Insulation tape (INS-TAPE-01)', 2, 12.00]], [[200.00, 'cash', ds('-7 days'), '']]);
$inv4 = seedInvoice($jobIds['done4'], ds('-20 days'), ds('-6 days'), 5, 'unpaid', [['Cleaning liquid 5 L (CLN-LIQ-5L)', 1, 95.00]]);
$inv5 = seedInvoice($jobIds['today1'], ds('-2 days'), ds('+28 days'), 5, 'unpaid', [['A/C filter 60x60 (FLT-AC-01)', 4, 60.00]]);
$inv6 = seedInvoice($jobIds['done3'], ds('-70 days'), ds('-40 days'), 5, 'cancelled', []);

// a paid contract invoice for the last month
$inv7 = seedInvoice($jobIds['done1'], ds('-40 days'), ds('-10 days'), 5, 'paid', [['A/C filter 60x60 (FLT-AC-01)', 4, 60.00]], [[900.00, 'cheque', ds('-12 days'), 'CHQ-4455']]);

// ---------------------------------------------------------------- notifications
$adminId = (int) Database::value("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
if ($adminId && !Database::value('SELECT COUNT(*) FROM notifications')) {
    Database::exec('INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, ?)',
        [$adminId, 'Welcome to Sanad — the demo data is ready. Open the dispatch board to plan the day.', 'index.php?p=board', ts('-1 day')]);
}

echo "Demo data ready.\n";
echo "  Dispatcher:  noura.dispatch / Demo@1234\n";
echo "  Technicians: ahmed.tech · bilal.tech · sameer.tech / Demo@1234\n";
echo "  Accountant:  rana.accounts / Demo@1234\n";
echo "  8 customers · 10 addresses · 5 contracts · " . (count($jobIds) + count($contractJobs)) . " jobs · 7 invoices\n";
