<?php
declare(strict_types=1);

/**
 * Dokumentations-Inhalt — Deutsch.
 * Sehr einfache Sprache (A1), formelle Anrede "Sie".
 * Gleiche Schlüssel wie in lang/doc-en.php.
 */

return [
    'title'    => 'So funktioniert Daem',
    'subtitle' => 'Eine einfache Anleitung für alle — kein IT-Wissen nötig.',
    'intro'    => 'Daem ist ein Helpdesk. Sie schreiben Ihr Problem. Das IT-Team antwortet. Sie können alles jederzeit sehen.',
    'cta'      => 'Daem öffnen',
    'toc'      => 'Auf dieser Seite',

    'sections' => [
        [
            'icon'  => '🎯',
            'title' => '1. Was ist Daem?',
            'blocks' => [
                ['type' => 'p', 'text' => 'Daem ist ein Programm für IT-Hilfe. Etwas ist kaputt? Sie brauchen einen neuen Laptop? Sie brauchen Zugang zu einem Ordner? Sie schreiben es in Daem.'],
                ['type' => 'p', 'text' => 'Das IT-Team sieht Ihre Nachricht. Es antwortet Ihnen. Sie können die Antwort jederzeit lesen. Nichts geht verloren.'],
                ['type' => 'note', 'text' => 'Eine Nachricht in Daem heißt ein “Ticket”. Ein Ticket hat eine Nummer. Mit dieser Nummer finden Sie es immer wieder.'],
            ],
        ],
        [
            'icon'  => '👥',
            'title' => '2. Wer benutzt Daem?',
            'blocks' => [
                ['type' => 'p', 'text' => 'Es gibt drei Arten von Benutzern:'],
                ['type' => 'ul', 'items' => [
                    'Anfragender — das sind Sie. Sie schreiben Tickets und antworten auf Fragen.',
                    'Agent — das ist die IT-Person. Sie liest die Tickets und löst die Probleme.',
                    'Administrator — diese Person verwaltet das Programm: Benutzer, Kategorien, Zeiten und Berichte.',
                ]],
                ['type' => 'note', 'text' => 'Sie sehen nur Ihre eigenen Tickets. Das IT-Team sieht alle Tickets.'],
            ],
        ],
        [
            'icon'  => '🔑',
            'title' => '3. Wie melde ich mich an?',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Öffnen Sie die Adresse von Daem in Ihrem Browser.',
                    'Schreiben Sie Ihren Benutzernamen (oder Ihre E-Mail) und Ihr Passwort.',
                    'Klicken Sie auf “Anmelden”.',
                ]],
                ['type' => 'p', 'text' => 'Ihr Konto erstellt die IT-Administration. Sie können kein Konto selbst erstellen.'],
                ['type' => 'note', 'text' => 'Haben Sie Ihr Passwort vergessen? Schreiben Sie ein Ticket. Die Administration gibt Ihnen ein neues Passwort.'],
            ],
        ],
        [
            'icon'  => '✍️',
            'title' => '4. Wie schreibe ich ein Ticket?',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Klicken Sie links auf “Neues Ticket”.',
                    'Schreiben Sie einen kurzen Betreff. Beispiel: “Mein Drucker druckt nicht”.',
                    'Wählen Sie eine Kategorie. Beispiel: “Hardware”.',
                    'Wählen Sie eine Priorität. Schauen Sie in Abschnitt 6 unten.',
                    'Schreiben Sie, was passiert ist. Schreiben Sie die Fehlermeldung, wenn Sie eine sehen. Schreiben Sie, was Sie schon versucht haben.',
                    'Klicken Sie auf “Ticket senden”.',
                ]],
                ['type' => 'p', 'text' => 'Danach bekommen Sie eine Ticket-Nummer. Das IT-Team bekommt eine Nachricht.'],
                ['type' => 'note', 'text' => 'Gute Tickets sind kurz und klar. Sagen Sie, was Sie machen wollten und was dann passiert ist.'],
            ],
        ],
        [
            'icon'  => '📋',
            'title' => '5. Was bedeuten die Status-Wörter?',
            'blocks' => [
                ['type' => 'ul', 'items' => [
                    'Neu — Ihr Ticket ist neu. Noch hat niemand angefangen.',
                    'Offen — eine IT-Person hat angefangen, es anzusehen.',
                    'In Bearbeitung — die IT-Person arbeitet daran.',
                    'Wartet auf Benutzer — die IT-Person wartet auf eine Antwort von Ihnen.',
                    'Gelöst — das Problem sollte jetzt gelöst sein. Bitte prüfen Sie es.',
                    'Geschlossen — das Ticket ist fertig.',
                ]],
                ['type' => 'note', 'text' => 'Ist das Problem noch da? Schreiben Sie eine Nachricht. Das Ticket öffnet sich automatisch wieder.'],
            ],
        ],
        [
            'icon'  => '⏱️',
            'title' => '6. Was sind Priorität und SLA?',
            'blocks' => [
                ['type' => 'p', 'text' => 'Die Priorität sagt, wie schnell das IT-Team antworten soll. Bitte wählen Sie sie ehrlich.'],
                ['type' => 'ul', 'items' => [
                    'Dringend — Sie können gar nicht arbeiten. Antwort-Ziel: 1 Stunde.',
                    'Hoch — Ihre Arbeit ist sehr langsam oder für viele Leute blockiert. Antwort-Ziel: 4 Stunden.',
                    'Mittel — Sie können arbeiten, aber es ist ärgerlich. Antwort-Ziel: 8 Stunden.',
                    'Niedrig — ein kleines Problem oder eine Frage. Antwort-Ziel: 24 Stunden.',
                ]],
                ['type' => 'p', 'text' => 'SLA heißt “service level agreement”. Das ist das Zeit-Ziel für eine Antwort. Daem zeigt eine Uhr, wenn ein Ticket zu spät ist.'],
                ['type' => 'note', 'text' => 'Bitte wählen Sie “Dringend” nicht für kleine Probleme. Dann bekommen echte Notfälle schneller Hilfe.'],
            ],
        ],
        [
            'icon'  => '💬',
            'title' => '7. Wie antworte ich?',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Öffnen Sie Ihr Ticket.',
                    'Schreiben Sie Ihre Nachricht in das Feld unten.',
                    'Klicken Sie auf “Senden”.',
                ]],
                ['type' => 'p', 'text' => 'Sie können ein gelöstes Ticket auch schließen. Klicken Sie auf “Ticket schließen”, wenn alles in Ordnung ist.'],
            ],
        ],
        [
            'icon'  => '📎',
            'title' => '8. Kann ich Dateien senden?',
            'blocks' => [
                ['type' => 'p', 'text' => 'Ja. Sie können Dateien an eine Nachricht anhängen. Zum Beispiel ein Foto vom Problem oder ein Bildschirmfoto vom Fehler.'],
                ['type' => 'ul', 'items' => [
                    'Eine Datei kann bis zu 5 MB groß sein.',
                    'Erlaubt: PDF, Bilder (PNG, JPG, GIF), Text, CSV, Word, Excel und ZIP-Dateien.',
                ]],
            ],
        ],
        [
            'icon'  => '📚',
            'title' => '9. Was ist die Wissensdatenbank?',
            'blocks' => [
                ['type' => 'p', 'text' => 'Die Wissensdatenbank hat kurze Artikel. Sie erklären einfache Lösungen. Vielleicht ist Ihr Problem schon dabei.'],
                ['type' => 'ol', 'items' => [
                    'Klicken Sie auf “Wissensdatenbank”.',
                    'Schreiben Sie ein Wort in das Suchfeld. Beispiel: “Passwort”.',
                    'Öffnen Sie einen Artikel und lesen Sie ihn.',
                ]],
                ['type' => 'p', 'text' => 'Sie können auch abstimmen: “Ja, das hat geholfen” oder “Nein, das hat nicht geholfen”.'],
                ['type' => 'note', 'text' => 'Wenn Sie ein neues Ticket schreiben, zeigt Daem Artikel, die helfen können. Bitte schauen Sie zuerst hinein. Das geht schneller.'],
            ],
        ],
        [
            'icon'  => '🧑‍💼',
            'title' => '10. Für IT-Agents: Ihre tägliche Arbeit',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Öffnen Sie “Tickets”. Wählen Sie “Nicht zugewiesen” oder “SLA bald fällig”.',
                    'Öffnen Sie ein Ticket. Lesen Sie die Beschreibung genau.',
                    'Nehmen Sie das Ticket selbst: Wählen Sie Ihren Namen in der Liste “Agent”.',
                    'Antworten Sie dem Anfragenden. Fragen Sie nach mehr Informationen, wenn Sie sie brauchen.',
                    'Schreiben Sie eine interne Notiz, wenn Ihr Team etwas wissen muss. Der Anfragende kann sie nicht sehen.',
                    'Ändern Sie die Priorität oder die Kategorie, wenn sie falsch ist.',
                    'Setzen Sie den Status auf “Gelöst”, wenn das Problem gelöst ist.',
                ]],
                ['type' => 'note', 'text' => 'Ihr Dashboard zeigt späte Tickets (rot) und Ihre eigenen offenen Tickets. Schauen Sie jeden Morgen dort hinein.'],
            ],
        ],
        [
            'icon'  => '🛠️',
            'title' => '11. Für Administratoren: Daem einrichten',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    '“Benutzer” — erstellen Sie Konten für Ihr Team und für die Anfragenden. Wählen Sie die Rolle für jeden Benutzer.',
                    '“Ticket-Kategorien” — erstellen Sie Kategorien, die zu Ihrer Firma passen. Beispiel: Hardware, Software, Netzwerk.',
                    '“SLA & Einstellungen” — schreiben Sie den Namen Ihres Helpdesks, das Ticket-Präfix und die Zeit-Ziele.',
                    '“Wissensdatenbank verwalten” — schreiben Sie Kategorien und Artikel für häufige Fragen.',
                    '“Berichte” — sehen Sie, wie viele Tickets kamen, wie schnell sie gelöst wurden und wie viele pünktlich waren. Sie können eine CSV-Datei herunterladen.',
                    '“Audit-Protokoll” — sehen Sie jede Aktion: wer sich angemeldet hat, wer was geändert hat. Nützlich für die Sicherheit.',
                ]],
                ['type' => 'note', 'text' => 'Bitte löschen Sie install.php nach der ersten Installation vom Server. Es ist ein Sicherheitsrisiko.'],
            ],
        ],
        [
            'icon'  => '🔄',
            'title' => '12. Das Leben eines Tickets',
            'blocks' => [
                ['type' => 'flow'],
                ['type' => 'p', 'text' => 'Der Anfragende schreibt das Ticket. Das IT-Team antwortet und arbeitet daran. Wenn es gelöst ist, ist der Status “Gelöst”. Wenn das Problem wieder kommt, schreibt der Anfragende wieder und das Ticket geht zurück auf “Offen”.'],
            ],
        ],
        [
            'icon'  => '❓',
            'title' => '13. Kleines Wörterbuch',
            'blocks' => [
                ['type' => 'ul', 'items' => [
                    'Ticket — eine Bitte um Hilfe.',
                    'Agent — die IT-Person, die Tickets löst.',
                    'Anfragender — die Person, die Hilfe braucht.',
                    'Priorität — wie schnell die Antwort kommen muss.',
                    'SLA — das Zeit-Ziel für eine Antwort.',
                    'Status — wo das Ticket gerade ist.',
                    'Interne Notiz — eine private Nachricht nur für das IT-Team.',
                    'Wissensdatenbank — Artikel mit einfachen Lösungen.',
                    'Anhang — eine Datei, die Sie mit einer Nachricht senden.',
                ]],
            ],
        ],
    ],

    'flow' => [
        'steps' => [
            ['label' => 'Neu', 'hint' => 'Sie schreiben das Ticket'],
            ['label' => 'Offen', 'hint' => 'IT liest es'],
            ['label' => 'In Bearbeitung', 'hint' => 'IT arbeitet daran'],
            ['label' => 'Gelöst', 'hint' => 'IT hat es gelöst'],
            ['label' => 'Geschlossen', 'hint' => 'Alles ist in Ordnung'],
        ],
        'reopen' => 'Sie schreiben wieder → das Ticket wird wieder “Offen”',
    ],
];
