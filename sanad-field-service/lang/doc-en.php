<?php
declare(strict_types=1);

/**
 * Documentation content — English (master). Simple A1 language.
 * Other language files must keep exactly the same keys and structure.
 */

return [
    'title'    => 'How Sanad works',
    'subtitle' => 'A simple guide for the office, the technicians and the boss.',
    'intro'    => 'Sanad organises the daily work of a service company. Every visit, every repair and every maintenance contract is in one place. You always know who goes where, what was done, which parts were used and who still has to pay.',
    'cta'      => 'Open Sanad',
    'toc'      => 'On this page',

    'sections' => [
        [
            'icon'  => '🎯',
            'title' => '1. What is Sanad?',
            'blocks' => [
                ['type' => 'p', 'text' => 'Sanad is a work organiser for a service company: air-conditioning, plumbing, cleaning, electricity, pest control — any business that sends people to customers.'],
                ['type' => 'p', 'text' => 'Every piece of work is one “job”. A job has a number, a customer, an address, a date, a technician and a status. Nothing is lost on paper or in a chat group.'],
                ['type' => 'note', 'text' => 'A job number looks like JOB-2026-00042.'],
            ],
        ],
        [
            'icon'  => '👥',
            'title' => '2. Who uses Sanad?',
            'blocks' => [
                ['type' => 'p', 'text' => 'There are four kinds of users:'],
                ['type' => 'ul', 'items' => [
                    'Dispatcher — plans the work: takes the job, sets the date, gives it to a technician.',
                    'Technician — does the work in the field: sees only their own jobs, writes notes, ticks the checklist, adds parts and photos.',
                    'Accountant — makes invoices and records the money that comes in.',
                    'Administrator — sets up everything: users, services, parts, prices and settings.',
                ]],
                ['type' => 'note', 'text' => 'A technician sees only their own jobs. The office sees everything.'],
            ],
        ],
        [
            'icon'  => '🔑',
            'title' => '3. How do I sign in?',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Open the address of Sanad in your browser.',
                    'Write your username or your email, and your password.',
                    'Click “Sign in”.',
                ]],
                ['type' => 'p', 'text' => 'Your account is made by the administrator. You cannot make an account yourself.'],
            ],
        ],
        [
            'icon'  => '🗓️',
            'title' => '4. The dispatcher: plan the day',
            'blocks' => [
                ['type' => 'p', 'text' => 'Open “Dispatch board”. You see one column for every technician and one column for jobs that have no technician yet.'],
                ['type' => 'ol', 'items' => [
                    'Choose the day with the arrows or the date box.',
                    'Look at the jobs nobody has yet — at the end of the board.',
                    'Choose a technician in the box under the job. The job moves to that column.',
                    'You can change the status of a job with the box inside the card.',
                ]],
                ['type' => 'note', 'text' => 'With “New job” you can also create a job directly for the chosen day.'],
            ],
        ],
        [
            'icon'  => '📝',
            'title' => '5. How do I create a new job?',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Click “New job”.',
                    'Choose the customer. If the job is at one of their addresses, choose it too.',
                    'Choose the service (for example “A/C general service”). The price is filled in later on the invoice.',
                    'Write a short title: what must be done?',
                    'Set the priority: normal, high or urgent.',
                    'Choose the technician, the day and the time window.',
                    'Click “Create job”.',
                ]],
                ['type' => 'note', 'text' => 'You can leave the technician empty. The job waits in the “Not assigned” column until somebody takes it.'],
            ],
        ],
        [
            'icon'  => '🔧',
            'title' => '6. The technician: do the work',
            'blocks' => [
                ['type' => 'p', 'text' => 'Open “My jobs”. You see your work for today and everything that is still open.'],
                ['type' => 'ol', 'items' => [
                    'Open the job. Click the status and choose “In progress” when you start.',
                    'Read the description, the address and the access notes (gate code, contact person).',
                    'Tick the checklist while you work.',
                    'Add the parts you used. The stock goes down automatically.',
                    'Write a note: what did you do? Take a photo if it helps.',
                    'When everything is finished, choose the status “Done”.',
                ]],
                ['type' => 'note', 'text' => 'A photo of the finished work protects you and the company.'],
            ],
        ],
        [
            'icon'  => '🧰',
            'title' => '7. Parts and stock',
            'blocks' => [
                ['type' => 'p', 'text' => '“Parts stock” is your small warehouse: filters, gas, cables, spare parts.'],
                ['type' => 'ul', 'items' => [
                    'Every part has a number, a cost price and a selling price.',
                    'Write a “reorder level”. When the stock goes under this level, the part is shown in red and Sanad warns the office.',
                    'When new goods arrive, write the quantity in “Add to stock”.',
                    'Parts you add to a job are taken out of the stock automatically and appear on the invoice.',
                ]],
                ['type' => 'note', 'text' => 'You cannot use more parts than you have. Sanad stops you and asks you to check the stock.'],
            ],
        ],
        [
            'icon'  => '📄',
            'title' => '8. Maintenance contracts (AMC)',
            'blocks' => [
                ['type' => 'p', 'text' => 'A contract is regular work for one customer: every month, every 3 months, every 6 months or once a year.'],
                ['type' => 'ol', 'items' => [
                    'Click “Maintenance contracts” and fill the form: customer, service, how often, price per visit, start and end date.',
                    'Sanad plans all the visits of the contract automatically.',
                    'In “Visits waiting for a job” you see which visit is due soon. Choose a technician and click “Plan a job”.',
                    'The visit becomes a real job on the board — with the right customer, address and service.',
                ]],
                ['type' => 'note', 'text' => 'The list “Contracts ending soon” reminds you to speak with the customer before the contract ends.'],
            ],
        ],
        [
            'icon'  => '💵',
            'title' => '9. Invoices and money',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Open a finished job and click “Create invoice”.',
                    'Sanad writes the service price and all parts used on the invoice automatically.',
                    'Check the lines. You can add or remove a line and change the VAT.',
                    'Click “Print” to give the customer a clean paper invoice.',
                    'When the customer pays, open the invoice and write the amount in “Record a payment”.',
                ]],
                ['type' => 'p', 'text' => 'The status of the invoice changes by itself: not paid → partly paid → paid.'],
                ['type' => 'note', 'text' => 'Do not delete an invoice to hide a mistake. Write a payment back or set the status to “Cancelled”.'],
            ],
        ],
        [
            'icon'  => '📊',
            'title' => '10. Reports',
            'blocks' => [
                ['type' => 'p', 'text' => '“Reports” answers the questions of the boss:'],
                ['type' => 'ul', 'items' => [
                    'How many jobs did we do this month?',
                    'How many are still open, how many are late?',
                    'How much did we bill and how much money really came in?',
                    'Who is the busiest technician? Which service do customers ask for most?',
                    'Which parts do we use most?',
                    'Which invoices are more than 30 or 60 days late?',
                ]],
                ['type' => 'p', 'text' => 'With “Export CSV” you can open the list in Excel.'],
            ],
        ],
        [
            'icon'  => '🔔',
            'title' => '11. What does the bell mean?',
            'blocks' => [
                ['type' => 'p', 'text' => 'Sanad looks at the dates every day and warns the office automatically:'],
                ['type' => 'ul', 'items' => [
                    'A contract visit is due soon.',
                    'A job is late — the planned day is in the past and the work is not finished.',
                    'A part is under the reorder level.',
                ]],
                ['type' => 'p', 'text' => 'You see the number on the bell at the top right. Click the bell to read the messages.'],
            ],
        ],
        [
            'icon'  => '🛠️',
            'title' => '12. For administrators: set up Sanad',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    '“Users” — make accounts for the office, the technicians and the accountant.',
                    '“Service catalog” — the services you sell, with price and time needed.',
                    '“Parts stock” — your material with cost price, selling price and reorder level.',
                    '“Settings” — the name of the business, the number prefixes, the currency, the VAT and the days to pay.',
                ]],
                ['type' => 'note', 'text' => 'Please delete install.php from the server after the first installation. It is a security risk.'],
            ],
        ],
        [
            'icon'  => '🧾',
            'title' => '13. Good habits',
            'blocks' => [
                ['type' => 'ul', 'items' => [
                    'Set the status honestly. A finished job must be “Done”.',
                    'Write the parts on the job, not only in the invoice. Then the stock stays correct.',
                    'Every job gets the real date. The board and the reports are only useful when the dates are true.',
                    'Use the “Internal notes” for private office information. The customer never sees them.',
                    'Take photos when the work is unusual. It helps in a discussion.',
                ]],
            ],
        ],
        [
            'icon'  => '🔄',
            'title' => '14. The life of a job',
            'blocks' => [
                ['type' => 'flow'],
                ['type' => 'p', 'text' => 'The customer calls. The office creates the job. The dispatcher plans a day and a technician. The technician does the work and records the parts. The office makes the invoice and records the money.'],
            ],
        ],
        [
            'icon'  => '❓',
            'title' => '15. Small dictionary',
            'blocks' => [
                ['type' => 'ul', 'items' => [
                    'Job — one piece of work for one customer.',
                    'Technician — the person who goes to the customer.',
                    'Dispatcher — the person in the office who plans the work.',
                    'Site — the address where the work happens.',
                    'Checklist — the steps of the work, ticked one by one.',
                    'Contract (AMC) — regular work for the same customer for a period.',
                    'Visit — one date of a contract.',
                    'Priority — how urgent a job is.',
                    'Reorder level — the smallest stock you want to have.',
                    'Invoice — the paper that says how much the customer must pay.',
                    'VAT — the tax that is added on the invoice.',
                    'Overdue — the day is in the past and it is not finished.',
                ]],
            ],
        ],
    ],

    'flow' => [
        'steps' => [
            ['label' => 'New', 'hint' => 'The customer calls'],
            ['label' => 'Scheduled', 'hint' => 'Day and technician set'],
            ['label' => 'In progress', 'hint' => 'Technician is working'],
            ['label' => 'Done', 'hint' => 'Work finished'],
            ['label' => 'Invoiced', 'hint' => 'Bill is written'],
            ['label' => 'Paid', 'hint' => 'Money received'],
        ],
        'reopen' => 'Customer calls again → create a new job with the same address',
    ],
];
