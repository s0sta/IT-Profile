<?php
declare(strict_types=1);

/**
 * Dokumentations-Inhalt — Deutsch. Einfache Sprache (A1).
 * Die Schlüssel und die Struktur sind genau wie in lang/doc-en.php.
 */

return [
    'title'    => 'So funktioniert Sanad',
    'subtitle' => 'Ein einfacher Leitfaden für das Büro, die Techniker und den Chef.',
    'intro'    => 'Sanad organisiert die tägliche Arbeit von einer Service-Firma. Jeder Besuch, jede Reparatur und jeder Wartungsvertrag sind an einem Ort. Sie wissen immer, wer wohin geht, was gemacht wurde, welche Ersatzteile verbraucht wurden und wer noch bezahlen muss.',
    'cta'      => 'Sanad öffnen',
    'toc'      => 'Auf dieser Seite',

    'sections' => [
        [
            'icon'  => '🎯',
            'title' => '1. Was ist Sanad?',
            'blocks' => [
                ['type' => 'p', 'text' => 'Sanad ist ein Arbeits-Organisator für eine Service-Firma: Klimaanlagen, Sanitär, Reinigung, Elektrik, Schädlingsbekämpfung — jedes Geschäft, das Leute zu Kunden schickt.'],
                ['type' => 'p', 'text' => 'Jede Arbeit ist ein „Auftrag“. Ein Auftrag hat eine Nummer, einen Kunden, eine Adresse, ein Datum, einen Techniker und einen Status. Nichts geht auf Papier oder in einer Chat-Gruppe verloren.'],
                ['type' => 'note', 'text' => 'Eine Auftragsnummer sieht so aus: JOB-2026-00042.'],
            ],
        ],
        [
            'icon'  => '👥',
            'title' => '2. Wer benutzt Sanad?',
            'blocks' => [
                ['type' => 'p', 'text' => 'Es gibt vier Arten von Benutzern:'],
                ['type' => 'ul', 'items' => [
                    'Disponent — plant die Arbeit: nimmt den Auftrag, setzt das Datum, gibt ihn einem Techniker.',
                    'Techniker — macht die Arbeit vor Ort: sieht nur die eigenen Aufträge, schreibt Notizen, macht Haken in der Checkliste, fügt Ersatzteile und Fotos hinzu.',
                    'Buchhalter — macht Rechnungen und erfasst das Geld, das hereinkommt.',
                    'Administrator — richtet alles ein: Benutzer, Leistungen, Ersatzteile, Preise und Einstellungen.',
                ]],
                ['type' => 'note', 'text' => 'Ein Techniker sieht nur die eigenen Aufträge. Das Büro sieht alles.'],
            ],
        ],
        [
            'icon'  => '🔑',
            'title' => '3. Wie melde ich mich an?',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Öffnen Sie die Adresse von Sanad im Browser.',
                    'Schreiben Sie Ihren Benutzernamen oder Ihre E-Mail und Ihr Passwort.',
                    'Klicken Sie auf „Anmelden“.',
                ]],
                ['type' => 'p', 'text' => 'Ihr Konto macht der Administrator. Sie können kein Konto selbst anlegen.'],
            ],
        ],
        [
            'icon'  => '🗓️',
            'title' => '4. Der Disponent: den Tag planen',
            'blocks' => [
                ['type' => 'p', 'text' => 'Öffnen Sie „Disposition“. Sie sehen eine Spalte für jeden Techniker und eine Spalte für Aufträge, die noch keinen Techniker haben.'],
                ['type' => 'ol', 'items' => [
                    'Wählen Sie den Tag mit den Pfeilen oder mit dem Datumsfeld.',
                    'Schauen Sie auf die Aufträge, die noch niemand hat — am Ende der Tafel.',
                    'Wählen Sie einen Techniker im Feld unter dem Auftrag. Der Auftrag geht in diese Spalte.',
                    'Sie können den Status von einem Auftrag mit dem Feld in der Karte ändern.',
                ]],
                ['type' => 'note', 'text' => 'Mit „Neuer Auftrag“ können Sie auch direkt für den gewählten Tag einen Auftrag anlegen.'],
            ],
        ],
        [
            'icon'  => '📝',
            'title' => '5. Wie lege ich einen neuen Auftrag an?',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Klicken Sie auf „Neuer Auftrag“.',
                    'Wählen Sie den Kunden. Wenn die Arbeit bei einer seiner Adressen ist, wählen Sie sie auch.',
                    'Wählen Sie die Leistung (zum Beispiel „Klimaanlage allgemein warten“). Der Preis kommt später auf die Rechnung.',
                    'Schreiben Sie einen kurzen Titel: Was ist zu tun?',
                    'Setzen Sie die Priorität: Normal, Hoch oder Dringend.',
                    'Wählen Sie den Techniker, den Tag und das Zeitfenster.',
                    'Klicken Sie auf „Auftrag anlegen“.',
                ]],
                ['type' => 'note', 'text' => 'Sie können den Techniker leer lassen. Der Auftrag wartet in der Spalte „Nicht zugeteilt“, bis jemand ihn nimmt.'],
            ],
        ],
        [
            'icon'  => '🔧',
            'title' => '6. Der Techniker: die Arbeit machen',
            'blocks' => [
                ['type' => 'p', 'text' => 'Öffnen Sie „Meine Aufträge“. Sie sehen Ihre Arbeit für heute und alles, was noch offen ist.'],
                ['type' => 'ol', 'items' => [
                    'Öffnen Sie den Auftrag. Klicken Sie auf den Status und wählen Sie „In Arbeit“, wenn Sie anfangen.',
                    'Lesen Sie die Beschreibung, die Adresse und die Zugangshinweise (Torcode, Ansprechpartner).',
                    'Machen Sie Haken in der Checkliste, während Sie arbeiten.',
                    'Fügen Sie die verbrauchten Ersatzteile hinzu. Der Bestand sinkt automatisch.',
                    'Schreiben Sie eine Notiz: Was haben Sie gemacht? Machen Sie ein Foto, wenn es hilft.',
                    'Wenn alles fertig ist, wählen Sie den Status „Erledigt“.',
                ]],
                ['type' => 'note', 'text' => 'Ein Foto von der fertigen Arbeit schützt Sie und die Firma.'],
            ],
        ],
        [
            'icon'  => '🧰',
            'title' => '7. Ersatzteile und Lager',
            'blocks' => [
                ['type' => 'p', 'text' => '„Ersatzteillager“ ist Ihr kleines Lager: Filter, Gas, Kabel, Ersatzteile.'],
                ['type' => 'ul', 'items' => [
                    'Jedes Ersatzteil hat eine Nummer, einen Einkaufspreis und einen Verkaufspreis.',
                    'Schreiben Sie einen „Meldebestand“. Wenn der Bestand unter diese Zahl geht, wird das Ersatzteil rot gezeigt und Sanad warnt das Büro.',
                    'Wenn neue Ware kommt, schreiben Sie die Menge bei „Zum Lager hinzufügen“.',
                    'Ersatzteile, die Sie zu einem Auftrag hinzufügen, gehen automatisch aus dem Lager und erscheinen auf der Rechnung.',
                ]],
                ['type' => 'note', 'text' => 'Sie können nicht mehr Ersatzteile verbrauchen, als Sie haben. Sanad stoppt Sie und bittet Sie, den Bestand zu prüfen.'],
            ],
        ],
        [
            'icon'  => '📄',
            'title' => '8. Wartungsverträge (AMC)',
            'blocks' => [
                ['type' => 'p', 'text' => 'Ein Vertrag ist regelmäßige Arbeit für einen Kunden: jeden Monat, alle 3 Monate, alle 6 Monate oder einmal im Jahr.'],
                ['type' => 'ol', 'items' => [
                    'Klicken Sie auf „Wartungsverträge“ und füllen Sie das Formular: Kunde, Leistung, wie oft, Preis pro Besuch, Beginn und Ende.',
                    'Sanad plant alle Besuche vom Vertrag automatisch.',
                    'Bei „Besuche warten auf einen Auftrag“ sehen Sie, welcher Besuch bald fällig ist. Wählen Sie einen Techniker und klicken Sie auf „Auftrag planen“.',
                    'Der Besuch wird ein echter Auftrag auf der Tafel — mit dem richtigen Kunden, der Adresse und der Leistung.',
                ]],
                ['type' => 'note', 'text' => 'Die Liste „Verträge enden bald“ erinnert Sie daran, vor dem Ende vom Vertrag mit dem Kunden zu sprechen.'],
            ],
        ],
        [
            'icon'  => '💵',
            'title' => '9. Rechnungen und Geld',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    'Öffnen Sie einen fertigen Auftrag und klicken Sie auf „Rechnung erstellen“.',
                    'Sanad schreibt den Preis von der Leistung und alle verbrauchten Ersatzteile automatisch auf die Rechnung.',
                    'Prüfen Sie die Positionen. Sie können eine Position hinzufügen oder entfernen und die MwSt. ändern.',
                    'Klicken Sie auf „Drucken“, um dem Kunden eine saubere Rechnung auf Papier zu geben.',
                    'Wenn der Kunde bezahlt, öffnen Sie die Rechnung und schreiben Sie den Betrag bei „Zahlung erfassen“.',
                ]],
                ['type' => 'p', 'text' => 'Der Status von der Rechnung ändert sich von selbst: offen → teilweise bezahlt → bezahlt.'],
                ['type' => 'note', 'text' => 'Löschen Sie keine Rechnung, um einen Fehler zu verstecken. Schreiben Sie eine Zahlung zurück oder setzen Sie den Status auf „Storniert“.'],
            ],
        ],
        [
            'icon'  => '📊',
            'title' => '10. Berichte',
            'blocks' => [
                ['type' => 'p', 'text' => '„Berichte“ beantwortet die Fragen vom Chef:'],
                ['type' => 'ul', 'items' => [
                    'Wie viele Aufträge haben wir diesen Monat gemacht?',
                    'Wie viele sind noch offen, wie viele sind zu spät?',
                    'Wie viel haben wir berechnet und wie viel Geld ist wirklich gekommen?',
                    'Wer ist der beschäftigtste Techniker? Welche Leistung fragen die Kunden am meisten?',
                    'Welche Ersatzteile verbrauchen wir am meisten?',
                    'Welche Rechnungen sind mehr als 30 oder 60 Tage zu spät?',
                ]],
                ['type' => 'p', 'text' => 'Mit „CSV exportieren“ können Sie die Liste in Excel öffnen.'],
            ],
        ],
        [
            'icon'  => '🔔',
            'title' => '11. Was bedeutet die Glocke?',
            'blocks' => [
                ['type' => 'p', 'text' => 'Sanad schaut jeden Tag auf die Daten und warnt das Büro automatisch:'],
                ['type' => 'ul', 'items' => [
                    'Ein Vertragsbesuch ist bald fällig.',
                    'Ein Auftrag ist zu spät — der geplante Tag ist vorbei und die Arbeit ist nicht fertig.',
                    'Ein Ersatzteil ist unter dem Meldebestand.',
                ]],
                ['type' => 'p', 'text' => 'Sie sehen die Zahl an der Glocke oben rechts. Klicken Sie auf die Glocke, um die Nachrichten zu lesen.'],
            ],
        ],
        [
            'icon'  => '🛠️',
            'title' => '12. Für Administratoren: Sanad einrichten',
            'blocks' => [
                ['type' => 'ol', 'items' => [
                    '„Benutzer“ — Konten für das Büro, die Techniker und den Buchhalter anlegen.',
                    '„Leistungen“ — die Leistungen, die Sie verkaufen, mit Preis und benötigter Zeit.',
                    '„Ersatzteillager“ — Ihr Material mit Einkaufspreis, Verkaufspreis und Meldebestand.',
                    '„Einstellungen“ — der Name von der Firma, die Nummern-Vorsätze, die Währung, die MwSt. und die Tage zum Bezahlen.',
                ]],
                ['type' => 'note', 'text' => 'Bitte löschen Sie install.php nach der ersten Installation vom Server. Es ist ein Sicherheitsrisiko.'],
            ],
        ],
        [
            'icon'  => '🧾',
            'title' => '13. Gute Gewohnheiten',
            'blocks' => [
                ['type' => 'ul', 'items' => [
                    'Setzen Sie den Status ehrlich. Ein fertiger Auftrag muss „Erledigt“ sein.',
                    'Schreiben Sie die Ersatzteile auf den Auftrag, nicht nur auf die Rechnung. Dann bleibt der Bestand richtig.',
                    'Jeder Auftrag bekommt das echte Datum. Die Tafel und die Berichte sind nur nützlich, wenn die Daten stimmen.',
                    'Nutzen Sie die „Interne Notizen“ für private Informationen vom Büro. Der Kunde sieht sie nie.',
                    'Machen Sie Fotos, wenn die Arbeit ungewöhnlich ist. Das hilft bei einer Diskussion.',
                ]],
            ],
        ],
        [
            'icon'  => '🔄',
            'title' => '14. Das Leben von einem Auftrag',
            'blocks' => [
                ['type' => 'flow'],
                ['type' => 'p', 'text' => 'Der Kunde ruft an. Das Büro legt den Auftrag an. Der Disponent plant einen Tag und einen Techniker. Der Techniker macht die Arbeit und erfasst die Ersatzteile. Das Büro macht die Rechnung und erfasst das Geld.'],
            ],
        ],
        [
            'icon'  => '❓',
            'title' => '15. Kleines Wörterbuch',
            'blocks' => [
                ['type' => 'ul', 'items' => [
                    'Auftrag — eine Arbeit für einen Kunden.',
                    'Techniker — die Person, die zum Kunden geht.',
                    'Disponent — die Person im Büro, die die Arbeit plant.',
                    'Einsatzort — die Adresse, wo die Arbeit passiert.',
                    'Checkliste — die Schritte von der Arbeit, einer nach dem anderen abgehakt.',
                    'Vertrag (AMC) — regelmäßige Arbeit für denselben Kunden über einen Zeitraum.',
                    'Besuch — ein Termin von einem Vertrag.',
                    'Priorität — wie dringend ein Auftrag ist.',
                    'Meldebestand — der kleinste Bestand, den Sie haben wollen.',
                    'Rechnung — das Papier, das sagt, wie viel der Kunde bezahlen muss.',
                    'MwSt. — die Steuer, die auf die Rechnung kommt.',
                    'Überfällig — der Tag ist vorbei und die Arbeit ist nicht fertig.',
                ]],
            ],
        ],
    ],

    'flow' => [
        'steps' => [
            ['label' => 'Neu', 'hint' => 'Der Kunde ruft an'],
            ['label' => 'Geplant', 'hint' => 'Tag und Techniker gesetzt'],
            ['label' => 'In Arbeit', 'hint' => 'Techniker arbeitet'],
            ['label' => 'Erledigt', 'hint' => 'Arbeit fertig'],
            ['label' => 'Berechnet', 'hint' => 'Rechnung ist geschrieben'],
            ['label' => 'Bezahlt', 'hint' => 'Geld erhalten'],
        ],
        'reopen' => 'Kunde ruft wieder an → neuen Auftrag mit derselben Adresse anlegen',
    ],
];
