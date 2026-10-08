<?php
declare(strict_types=1);

/**
 * Documentation content — English (master).
 * Very simple language (A1). Every other language file must keep the same keys.
 */

return [
    'title'    => 'How Daem works',
    'subtitle' => 'A simple guide for everyone — no IT knowledge needed.',
    'intro'    => 'Daem is a help desk. You write your problem. The IT team answers. You can see everything at any time.',
    'cta'      => 'Open Daem',
    'toc'      => 'On this page',

    'sections' => [
        [
            'icon'  => '🎯',
            'title' => '1. What is Daem?',
            'blocks' => [
                ['type' => 'p', 'text' => 'Daem is a program for IT help. Something is broken? You need a new laptop? You need access to a folder? You write it in Daem.'],
                ['type' => 'p', 'text' => 'The IT team sees your message. They answer you. You can read the answer at any time. Nothing gets lost.'],
                ['type' => 'note', 'text' => 'One message in Daem is called a “ticket”. A ticket has a number. You can always find it again with this number.'],
            ],
        ],
        [
            'icon'  => '👥',
            'title' => '2. Who uses Daem?',
            'blocks' => [
                ['type' => 'p', 'text' => 'There are three kinds of users:'],
                ['type' => 'ul', 'items' => [
                    'Requester — this is you. You write tickets and you answer questions.',
                    'Agent — this is the IT person. They read the tickets and solve the problems.',
                    'Administrator — this person manages the program: users, categories, times and reports.',
                ]],
                ['type' => 'note', 'text' => 'You only see your own tickets. The IT team sees all tickets.'],
            ],
        ],
        [
            'icon'  => '🔑',
            'title' => '3. How do I sign in?',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Open the address of Daem in your browser.',
                    'Write your username (or your email) and your password.',
                    'Click “Sign in”.',
                ]],
                ['type' => 'p', 'text' => 'Your account is made by the IT administrator. You cannot make an account yourself.'],
                ['type' => 'note', 'text' => 'Did you forget your password? Write a ticket. The administrator will give you a new password.'],
            ],
        ],
        [
            'icon'  => '✍️',
            'title' => '4. How do I write a ticket?',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Click “New Ticket” on the left side.',
                    'Write a short subject. Example: “My printer does not print”.',
                    'Choose a category. Example: “Hardware”.',
                    'Choose a priority. Look at section 6 below.',
                    'Write what happened. Write the error message if you see one. Write what you already tried.',
                    'Click “Send ticket”.',
                ]],
                ['type' => 'p', 'text' => 'After that you get a ticket number. The IT team gets a message.'],
                ['type' => 'note', 'text' => 'Good tickets are short and clear. Say what you wanted to do, and what happened instead.'],
            ],
        ],
        [
            'icon'  => '📋',
            'title' => '5. What do the status words mean?',
            'blocks' => [
                ['type' => 'ul', 'items' => [
                    'New — your ticket is new. Nobody started yet.',
                    'Open — an IT person started to look at it.',
                    'In Progress — the IT person is working on it.',
                    'Waiting on User — the IT person waits for an answer from you.',
                    'Resolved — the problem should be solved now. Please check it.',
                    'Closed — the ticket is finished.',
                ]],
                ['type' => 'note', 'text' => 'Is the problem still there? Write a message. The ticket opens again automatically.'],
            ],
        ],
        [
            'icon'  => '⏱️',
            'title' => '6. What is priority and SLA?',
            'blocks' => [
                ['type' => 'p', 'text' => 'The priority says how fast the IT team should answer. Please choose it honestly.'],
                ['type' => 'ul', 'items' => [
                    'Urgent — you cannot work at all. Answer target: 1 hour.',
                    'High — your work is very slow or blocked for many people. Answer target: 4 hours.',
                    'Medium — you can work, but it is annoying. Answer target: 8 hours.',
                    'Low — a small problem or a question. Answer target: 24 hours.',
                ]],
                ['type' => 'p', 'text' => 'SLA means “service level agreement”. It is the time target for an answer. Daem shows a clock when a ticket is late.'],
                ['type' => 'note', 'text' => 'Please do not choose “Urgent” for small problems. Then real emergencies get help faster.'],
            ],
        ],
        [
            'icon'  => '💬',
            'title' => '7. How do I answer?',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Open your ticket.',
                    'Write your message in the box at the bottom.',
                    'Click “Send”.',
                ]],
                ['type' => 'p', 'text' => 'You can also close a solved ticket. Click “Close ticket” when everything is fine.'],
            ],
        ],
        [
            'icon'  => '📎',
            'title' => '8. Can I send files?',
            'blocks' => [
                ['type' => 'p', 'text' => 'Yes. You can add files to a message. For example a photo of the problem or a screenshot of the error.'],
                ['type' => 'ul', 'items' => [
                    'One file can be up to 5 MB.',
                    'Allowed: PDF, pictures (PNG, JPG, GIF), text, CSV, Word, Excel and ZIP files.',
                ]],
            ],
        ],
        [
            'icon'  => '📚',
            'title' => '9. What is the knowledge base?',
            'blocks' => [
                ['type' => 'p', 'text' => 'The knowledge base has short articles. They explain easy solutions. Maybe your problem is already there.'],
                ['type' => 'ol', 'items' => [
                    'Click “Knowledge Base”.',
                    'Write a word in the search box. Example: “password”.',
                    'Open an article and read it.',
                ]],
                ['type' => 'p', 'text' => 'You can also vote: “Yes, this helped” or “No, this did not help”.'],
                ['type' => 'note', 'text' => 'When you write a new ticket, Daem shows you articles that may help. Please look at them first. It is faster.'],
            ],
        ],
        [
            'icon'  => '🧑‍💼',
            'title' => '10. For IT agents: your daily work',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Open “Tickets”. Choose “Unassigned” or “SLA due soonest”.',
                    'Open a ticket. Read the description carefully.',
                    'Give the ticket to yourself: choose your name in the “Agent” list.',
                    'Answer the requester. Ask for more information if you need it.',
                    'Write an internal note if your team must know something. The requester cannot see it.',
                    'Change the priority or the category if it is wrong.',
                    'Set the status to “Resolved” when the problem is solved.',
                ]],
                ['type' => 'note', 'text' => 'Your dashboard shows late tickets (red) and your own open tickets. Look there every morning.'],
            ],
        ],
        [
            'icon'  => '🛠️',
            'title' => '11. For administrators: how to set up Daem',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    '“Users” — make accounts for your team and for the requesters. Choose the role for each user.',
                    '“Ticket Categories” — make categories that fit your company. Example: Hardware, Software, Network.',
                    '“SLA & Settings” — write the name of your help desk, the ticket prefix and the time targets.',
                    '“Knowledge Base Admin” — write categories and articles for common questions.',
                    '“Reports” — see how many tickets came in, how fast they were solved and how many were on time. You can download a CSV file.',
                    '“Audit Log” — see every action: who signed in, who changed what. Useful for security.',
                ]],
                ['type' => 'note', 'text' => 'Please delete install.php from the server after the first installation. It is a security risk.'],
            ],
        ],
        [
            'icon'  => '🔄',
            'title' => '12. The life of a ticket',
            'blocks' => [
                ['type' => 'flow'],
                ['type' => 'p', 'text' => 'The requester writes the ticket. The IT team answers and works on it. When it is solved, the status is “Resolved”. If the problem comes back, the requester writes again and the ticket goes back to “Open”.'],
            ],
        ],
        [
            'icon'  => '❓',
            'title' => '13. Small dictionary',
            'blocks' => [
                ['type' => 'ul', 'items' => [
                    'Ticket — one request for help.',
                    'Agent — the IT person who solves tickets.',
                    'Requester — the person who needs help.',
                    'Priority — how fast the answer must come.',
                    'SLA — the time target for an answer.',
                    'Status — where the ticket is right now.',
                    'Internal note — a private message for the IT team only.',
                    'Knowledge base — articles with easy solutions.',
                    'Attachment — a file that you send with a message.',
                ]],
            ],
        ],
    ],

    'flow' => [
        'steps' => [
            ['label' => 'New', 'hint' => 'You write the ticket'],
            ['label' => 'Open', 'hint' => 'IT reads it'],
            ['label' => 'In Progress', 'hint' => 'IT works on it'],
            ['label' => 'Resolved', 'hint' => 'IT solved it'],
            ['label' => 'Closed', 'hint' => 'Everything is fine'],
        ],
        'reopen' => 'You write again → the ticket becomes “Open” again',
    ],
];
