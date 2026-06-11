=== Easy Event ===
Contributors: easyevent
Tags: event, registration, tickets, presale, groups
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Tested with PHP: 8.3.5
Stable tag: 1.2.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Einfaches Event-Anmeldesystem mit Vorverkauf, Gruppen und Ticketkontingenten.

== Description ==

Easy Event ermöglicht die Verwaltung von Veranstaltungen mit Ticketkontingenten, Gruppenaufteilung und Vorverkaufssteuerung.

**Funktionen:**

* Unbegrenzt viele Events gleichzeitig
* Gruppenaufteilung mit frei konfigurierbarem Beschreibungsfeld
* Vorverkaufs-Countdown mit konfigurierbarem Datum und Uhrzeit
* Ticketkontingente pro Gruppe mit Echtzeit-Verfügbarkeitsanzeige
* Bestätigungs-E-Mail an Teilnehmer und Benachrichtigung an Admin
* Frei konfigurierbarer E-Mail-Text mit Platzhaltern
* CSV-Export aller Anmeldungen
* Schutz gegen Race Conditions (MySQL-Transaktionen)
* Schutz gegen Doppelabsenden und Bot-Spam (Honeypot)
* Optionaler Duplikat-E-Mail-Schutz pro Event
* Shortcode `[easy_event id="X"]` für beliebige Seiten

== Installation ==

1. Plugin-Ordner `easy-event` in `/wp-content/plugins/` hochladen
2. Plugin im WordPress-Admin unter „Plugins" aktivieren
3. Unter „Easy Event" ein neues Event erstellen
4. Shortcode `[easy_event id="X"]` auf einer beliebigen Seite einfügen

== Frequently Asked Questions ==

= Wie binde ich ein Event auf einer Seite ein? =
Mit dem Shortcode `[easy_event id="X"]`, wobei X die ID des Events ist. Die ID wird in der Events-Liste angezeigt.

= Kann ich mehrere Events gleichzeitig betreiben? =
Ja, beliebig viele Events können parallel aktiv sein. Jedes Event erhält einen eigenen Shortcode.

= Was passiert wenn sich zwei Personen gleichzeitig anmelden? =
Das Plugin verwendet MySQL-Transaktionen mit Row-Level-Locking. Überverkäufe sind dadurch ausgeschlossen.

= Kann dieselbe E-Mail-Adresse mehrfach verwendet werden? =
Das ist pro Event konfigurierbar. In den Event-Einstellungen (Tab „Event-Details") gibt es eine entsprechende Checkbox.

== Changelog ==

= 1.2.8 =
* Fix: Bestätigungs-E-Mail an Anmelder wird jetzt immer gesendet (Absender-Header optional)

= 1.2.6 =
* Neu: Konfigurierbarer Admin-Benachrichtigungstext im E-Mail-Tab (mit Platzhaltern)
* Umbenennung: Platzhalter {personen} → {anzahl_personen}
* Entfernt: Platzhalter {gruppe_nr} (nicht mehr verwendet)
* Datenbank: neue Spalte admin_notification_text (DB v1.5)

= 1.2.5 =
* Fix: Placeholder Admin E-Mail auf statischen Text «deine@email.com» geändert

= 1.2.4 =
* Neu: Datumsvalidierungen mit Warnhinweisen (Event-Datum in Vergangenheit, Anmeldeschluss nach Event, Vorverkauf nach Event)
* Mehrfachanmeldung-Checkbox standardmässig deaktiviert für neue Events
* Verbesserung: Beschreibungsfeld in Gruppen-Tab füllt jetzt volle Spaltenbreite
* Änderung: Standardtext «Ausverkauft»-Meldung auf «Leider sind alle Tickets ausverkauft.» gekürzt
* Umbenennung: «Gruppe Nr.» → «Sortierung» im Gruppen-Tab
* Änderung: Gruppen-Dropdown im Anmeldeformular zeigt nur noch die Beschreibung (keine Sortierungsnummer)
* Neu: Alle E-Mail-Felder sind jetzt Pflichtfelder (inkl. serverseitiger Validierung)
* Neu: Platzhaltertexte in E-Mail-Feldern (Admin-E-Mail, Absender Name/E-Mail, Betreff)

= 1.2.3 =
* Fix: Gruppen-Dropdown zeigt «Ausverkauft» statt «noch 0 Tickets» wenn keine Plätze mehr frei sind
* Verbesserung: Neues Dropdown-Format – Status vor der Gruppennummer (Ausverkauft / noch X Tickets)

= 1.2.2 =
* Fix: Zeilenumbrüche in der Event-Beschreibung werden auf der Website korrekt angezeigt (wpautop)

= 1.2.1 =
* Verbesserung: Beschreibungsfeld bei Gruppen füllt jetzt die volle Spaltenbreite
* Fix: Zeilenumbrüche in Bestätigungs-E-Mails werden korrekt dargestellt (HTML-E-Mail mit nl2br)
* Umbenennung: «Anzahl Tickets» → «Anzahl Personen» (Formular, Admin-Benachrichtigung)
* Umbenennung: E-Mail-Platzhalter `{tickets}` → `{personen}`
* Verbesserung: Gruppen-Dropdown zeigt `<Nr> <Beschreibung>` ohne «Gruppe»-Prefix; Verfügbarkeit ab ≤ 10

= 1.2.0 =
* Änderung: Gruppen haben kein Startzeit- und kein Gruppenleiter-Feld mehr, sondern ein freies Beschreibungsfeld
* Datenbank-Migration: Spalten `start_time` / `leader` entfernt, neue Spalte `description` hinzugefügt (DB v1.4)
* Fix: „Column 'presale_date' cannot be null" beim Speichern eines Events ohne Gruppen und ohne Vorverkauf
* E-Mail-Platzhalter: `{startzeit}` und `{gruppenleiter}` durch `{gruppe_beschreibung}` ersetzt
* CSV-Export: Spalten „Startzeit" / „Gruppenleiter" durch „Beschreibung" ersetzt
* Neu: .gitignore hinzugefügt

= 1.1.0 =
* Neu: Duplikat-E-Mail-Schutz pro Event (konfigurierbar)
* Neu: Schutz gegen Doppelabsenden des Anmeldeformulars (Submit-Token)
* Neu: Honeypot-Feld gegen automatisierte Bot-Anmeldungen
* Neu: Paginierung der Anmeldungsliste (50 Einträge pro Seite)
* Neu: Saubere Deinstallation (alle Tabellen und Optionen werden entfernt)
* Neu: Datenbankversion-Prüfung bei Plugin-Updates (automatische Migration)
* Verbesserung: Erfolgsmeldung nicht mehr als URL-Parameter (?ee_success=1)
* Verbesserung: URL-Parameter nach Anzeige via history.replaceState entfernt
* Verbesserung: Whitelist für $_POST-Felder in process_save_event()
* Verbesserung: MySQL-Transaktion umschliesst jetzt save_event + save_groups
* Verbesserung: DB-Fehler beim Speichern werden dem Admin angezeigt
* Verbesserung: Ungültige E-Mail-Adressen lösen Admin-Warnung aus statt lautlos gelöscht zu werden
* Verbesserung: max_tickets-Warnung wenn Wert unter bereits verkaufte Tickets gesetzt wird
* Verbesserung: Gruppen-IDs bleiben beim Speichern erhalten (verhindert Datenverlust bei Anmeldungsreferenzen)
* Verbesserung: Löschen-Dialog zeigt konkrete Anzahl betroffener Anmeldungen und Gruppen
* Sicherheit: Header-Injection-Schutz im E-Mail-Absenderfeld
* Sicherheit: wp_validate_redirect() für Formular-Rückgabe-URL
* Sicherheit: Nonce-Schutz beim Aufrufen des Event-Bearbeitungsformulars
* Sicherheit: Capability-Check in allen Admin-Page-Callbacks

= 1.0.0 =
* Erstveröffentlichung
