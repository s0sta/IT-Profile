<?php
declare(strict_types=1);

/**
 * Documentation content — English.
 * Very simple language (A1). Same keys, same order and same icons as doc-ar.php (master).
 */

return [
    'title'    => 'How Idara works',
    'subtitle' => 'A simple guide for everyone — no technical knowledge needed.',
    'intro'    => 'Idara is the manager workspace. In it you organise your tasks, assign work to your team, and approve the requests that arrive on your desk. Everything is kept in one place.',
    'cta'      => 'Start now',
    'toc'      => 'On this page',

    'sections' => [
        [
            'icon'  => '🎯',
            'title' => '1. What is Idara?',
            'blocks' => [
                ['type' => 'p', 'text' => 'Idara is a program for the work of a manager. It is one place for everything a manager must follow: his tasks, his team tasks, and the requests that need his approval.'],
                ['type' => 'p', 'text' => 'You do not need paper and you do not need many files. Everything is saved in the system, and you can see it at any time.'],
                ['type' => 'note', 'text' => 'The most important work in Idara is approvals. When a request arrives on your desk, you must decide: approve, or reject, or return for revision.'],
            ],
        ],
        [
            'icon'  => '👥',
            'title' => '2. Who uses Idara?',
            'blocks' => [
                ['type' => 'p', 'text' => 'There are four kinds of users:'],
                ['type' => 'ul', 'items' => [
                    'System Administrator — this person manages the program: users, departments, types, settings and reports.',
                    'Executive Leadership — they see all departments and all approvals, and they approve the big requests.',
                    'Department Manager — this is you. You organise your tasks, assign work to your team, and approve requests.',
                    'Employee — does the tasks given to him, writes follow-ups, and asks for an approval when needed.',
                ]],
                ['type' => 'note', 'text' => 'A department manager sees the tasks of his department. An employee sees only his own tasks.'],
            ],
        ],
        [
            'icon'  => '🔑',
            'title' => '3. How do I sign in?',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Open the address of the system in your browser.',
                    'Write your username or your email.',
                    'Write your password, then click “Sign in”.',
                ]],
                ['type' => 'p', 'text' => 'Your account is made by the system administrator. You cannot make an account yourself.'],
                ['type' => 'note', 'text' => 'Did you forget your password? Ask the system administrator. He will give you a new password.'],
            ],
        ],
        [
            'icon'  => '📊',
            'title' => '4. The dashboard',
            'blocks' => [
                ['type' => 'p', 'text' => 'The dashboard is the first page you see after you sign in. It shows the important numbers of your day.'],
                ['type' => 'ul', 'items' => [
                    'My open tasks — the tasks that are not finished yet.',
                    'Awaiting my approval — the requests that wait for your decision.',
                    'Overdue tasks — the tasks whose due date has passed.',
                    'Due this week — the tasks that end this week.',
                    'Open letters — the letters that are not closed yet.',
                    'Upcoming meetings — the meetings that are near.',
                ]],
                ['type' => 'p', 'text' => 'Every number is a button. Click it to open the full list.'],
                ['type' => 'note', 'text' => 'A red number means a delay. Look at the dashboard every morning.'],
            ],
        ],
        [
            'icon'  => '✅',
            'title' => '5. My tasks',
            'blocks' => [
                ['type' => 'p', 'text' => 'Your tasks are the work that you are responsible for. They can be made by you or given to you.'],
                ['type' => 'ul', 'items' => [
                    'Status — New, or In Progress, or Waiting on Another Party, or Blocked, or Completed, or Cancelled.',
                    'Checklist — small steps inside the task. When you finish the steps, the progress goes up by itself.',
                    'Progress — a number from 0 to 100. It shows how much you finished.',
                    'Follow-ups — notes that you write to record what was done.',
                    'Due date — the last day to finish the task.',
                ]],
                ['type' => 'ol', 'items' => [
                    'Open “Tasks”, then “My Tasks”.',
                    'Click a task to open its details.',
                    'Write a follow-up, or finish a step, or change the status.',
                    'When you finish, click “Complete task”.',
                ]],
                ['type' => 'note', 'text' => 'Write a short follow-up every day. Then your team knows what you did.'],
            ],
        ],
        [
            'icon'  => '👥',
            'title' => '6. My team tasks',
            'blocks' => [
                ['type' => 'p', 'text' => 'You are responsible for the work of your team. You can give any employee a task and follow his progress.'],
                ['type' => 'ol', 'items' => [
                    'Open “My Team” to see the members and the workload of each one.',
                    'Open “Tasks”, then “Team Tasks”.',
                    'Choose the filter “Overdue only” to see the late work.',
                    'Click any task to see its steps and its follow-ups.',
                ]],
                ['type' => 'ul', 'items' => [
                    'A red number means the task is late.',
                    'The “Progress” column shows how much is finished.',
                    'The “Workload” list shows who has many tasks.',
                ]],
                ['type' => 'note', 'text' => 'If one employee has many tasks, move some work before it becomes late.'],
            ],
        ],
        [
            'icon'  => '🖊️',
            'title' => '7. How do I create a task?',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Click “New Task”.',
                    'Write a short and clear title.',
                    'Write a description: what exactly is needed?',
                    'Choose the priority: Low, or Medium, or High, or Urgent.',
                    'Choose a category, for example “Projects”.',
                    'Choose the assignee from the list of team members.',
                    'Write the due date.',
                    'Click “Save”.',
                ]],
                ['type' => 'p', 'text' => 'After you save, the assignee gets a message. The task appears in his task list.'],
                ['type' => 'note', 'text' => 'A good task has a clear title and a due date. Do not put two jobs in one task.'],
            ],
        ],
        [
            'icon'  => '🔏',
            'title' => '8. The approvals you must confirm',
            'blocks' => [
                ['type' => 'p', 'text' => 'An approval request is an official request that needs your agreement. For example: to pay an amount, or to sign a letter, or to hire, or to travel.'],
                ['type' => 'p', 'text' => 'The approval chain is the order of the approvers. Step one approves first, then step two, and so on until the last step.'],
                ['type' => 'ul', 'items' => [
                    'Approve — you agree. The request moves to the next step, or it closes if this was the last step.',
                    'Reject — you do not agree. The request stops and it closes.',
                    'Return for revision — the request needs a fix. It goes back to the requester to edit it.',
                ]],
                ['type' => 'note', 'text' => 'When you reject or return, you must write a decision note. When you approve, the note is optional.'],
                ['type' => 'p', 'text' => 'The request shows a badge “Your decision is needed” when the turn is yours. You can also see the full approval history: who approved, when, and with which note.'],
            ],
        ],
        [
            'icon'  => '🧾',
            'title' => '9. How do I request an approval?',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Click “New Approval Request”.',
                    'Write the title of the request and its details.',
                    'Choose the approval type, for example “Payment approval”.',
                    'Choose the approvers in order: first, then second…',
                    'Attach the documents if there are any.',
                    'Click “Send”.',
                ]],
                ['type' => 'p', 'text' => 'After you send, the request goes to the first approver. You can follow it in “My Requests”.'],
                ['type' => 'note', 'text' => 'Choose the right chain from the start. A short chain is faster, and a long chain is more careful.'],
            ],
        ],
        [
            'icon'  => '🤝',
            'title' => '10. Delegation',
            'blocks' => [
                ['type' => 'p', 'text' => 'Delegation means that another person approves instead of you for a fixed time. A manager delegates when he travels or when he is on leave.'],
                ['type' => 'ul', 'items' => [
                    'Delegator — the person who delegates. Usually the manager.',
                    'Delegate — the person who approves instead of him.',
                    'Period — from a start date to an end date.',
                ]],
                ['type' => 'p', 'text' => 'While the delegation is active, your approvals appear in the inbox of the delegate. He approves them by an active delegation.'],
                ['type' => 'note', 'text' => 'Open “Delegations” before you travel. Make a delegation with a clear start date and end date.'],
            ],
        ],
        [
            'icon'  => '✉️',
            'title' => '11. Official correspondence',
            'blocks' => [
                ['type' => 'p', 'text' => 'Correspondence is the official letters with other parties: ministries, or companies, or government bodies.'],
                ['type' => 'ul', 'items' => [
                    'Incoming — a letter that arrived to us.',
                    'Outgoing — a letter that we sent.',
                    'Every letter has a number, a date received and a due date.',
                ]],
                ['type' => 'ul', 'items' => [
                    'New — the work has not started.',
                    'Under review — the letter is being studied.',
                    'Replied — the answer was sent.',
                    'Archived — the work is finished.',
                ]],
                ['type' => 'note', 'text' => 'If the due date is near and there is no reply, a warning appears on the dashboard.'],
            ],
        ],
        [
            'icon'  => '📅',
            'title' => '12. Meetings and minutes',
            'blocks' => [
                ['type' => 'p', 'text' => 'Meetings are scheduled in the system. Every meeting has a date, a time, a place and attendees.'],
                ['type' => 'ol', 'items' => [
                    'Click “New Meeting”.',
                    'Write the title, and choose the date, the time and the place.',
                    'Choose the attendees.',
                    'After the meeting, write the minutes: the decisions and the recommendations.',
                ]],
                ['type' => 'p', 'text' => 'The meeting status is: Scheduled, or Done, or Cancelled.'],
                ['type' => 'note', 'text' => 'Write the minutes on the same day. Then the decisions are not forgotten.'],
            ],
        ],
        [
            'icon'  => '🗓️',
            'title' => '13. The calendar and the life of an approval',
            'blocks' => [
                ['type' => 'p', 'text' => 'The calendar shows all the due dates in one place: tasks, approvals, correspondence and meetings.'],
                ['type' => 'ul', 'items' => [
                    'Task due — work with a due date.',
                    'Approval due — an approval request with a date.',
                    'Letter due — a letter with a reply date.',
                    'Meeting — the time of a meeting.',
                ]],
                ['type' => 'p', 'text' => 'The life of an approval has five steps. Look at the picture:'],
                ['type' => 'flow'],
                ['type' => 'p', 'text' => 'If a request is returned for revision, the requester edits it and sends it again. The chain starts from step one.'],
            ],
        ],
        [
            'icon'  => '❓',
            'title' => '14. Small dictionary',
            'blocks' => [
                ['type' => 'ul', 'items' => [
                    'Request — something that needs a decision or an agreement. In Idara: an approval request.',
                    'Task — one job that must be done.',
                    'Approval — the agreement to a request.',
                    'Approval chain — the order of the approvers, step by step.',
                    'Approver — the person who approves the request.',
                    'Requester — the person who sent the request.',
                    'Delegation — when another person approves instead of you for a fixed time.',
                    'Delegate — the person who approves by the delegation.',
                    'Correspondence — an official letter, incoming or outgoing.',
                    'Minutes — the decisions and recommendations of a meeting.',
                    'Checklist — small steps inside a task.',
                    'Progress — how much of the task is finished.',
                    'Due date — the last day to finish or to reply.',
                    'Priority — how urgent the work is: Low, Medium, High or Urgent.',
                    'Status — where the work is right now.',
                ]],
            ],
        ],
    ],

    'flow' => [
        'steps' => [
            ['label' => 'Pending', 'hint' => 'The request was sent'],
            ['label' => 'Step 1', 'hint' => 'The manager studies it'],
            ['label' => 'Step 2', 'hint' => 'A higher approval'],
            ['label' => 'Approved', 'hint' => 'The request is closed'],
            ['label' => 'Returned', 'hint' => 'It needs a fix'],
        ],
        'reopen' => 'Returned for revision → the requester edits the request and sends it again',
    ],
];
