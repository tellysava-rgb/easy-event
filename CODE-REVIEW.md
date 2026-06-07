# Easy Event – Code Review Checkliste

Datum: 2026-06-07  
Geprüfte Dateien: alle PHP-, JS- und CSS-Dateien des Plugins

---

## 🔴 Kritisch (Sicherheit)

- [x] **`$_POST` wird ungefiltert an `save_event()` weitergegeben**  
  `class-admin.php` – `process_save_event()` baut jetzt ein explizites Whitelist-Array aus `$_POST`. Nur bekannte Felder werden übernommen. ✅ Behoben

- [x] **`From:`-Header im E-Mail wird nicht sanitized (Header-Injection)**  
  `class-email.php` – `str_replace(["\r","\n"], '', $sender_name)` direkt beim Zusammenbauen des Headers in allen drei Methoden. ✅ Behoben

- [x] **`ee_current_url` wird ohne Herkunftsprüfung als Redirect-Ziel verwendet**  
  `class-shortcode.php` – `wp_validate_redirect()` stellt sicher dass die URL zur eigenen Domain gehört. Fallback auf `home_url('/')`. ✅ Behoben

- [x] **Toter Code: `process_test_email()`, `page_test_email()`, POST-Handler**  
  `class-admin.php` – alle drei toten Code-Blöcke entfernt. ✅ Behoben

---

## 🔴 Kritisch (Datenverlust)

- [x] **`delete_event()` löscht alle Anmeldungen unwiderruflich – kein Schutz**  
  `admin/views/events-list.php` – Bestätigungsdialog zeigt jetzt konkret: Anzahl Gruppen, Anzahl Anmeldungen, Hinweis «Diese Aktion kann nicht rückgängig gemacht werden». ✅ Behoben

- [x] **`save_event()` + `save_groups()` nicht in einer Transaktion**  
  `class-admin.php` – `process_save_event()` wraps beide Operationen in `START TRANSACTION` / `COMMIT`. Bei Fehler: `ROLLBACK`. ✅ Behoben

- [x] **`save_event()` meldet DB-Fehler beim UPDATE/INSERT nicht zurück**  
  `class-database.php` – Rückgabewert von `$wpdb->update()` / `$wpdb->insert()` geprüft. Bei Fehler: `WP_Error` zurückgegeben, in `process_save_event()` abgefangen, Fehlermeldung via Transient im Admin angezeigt. ✅ Behoben

- [x] **Ungültige E-Mail-Adressen werden lautlos auf Leerstring gesetzt**  
  `class-admin.php` – Validierung vor dem Speichern: wenn Feld ausgefüllt aber ungültig → Warnung im Admin (gelbe Notice mit konkreter Adresse). ✅ Behoben

- [x] **`max_tickets` kann unter die bereits verkaufte Anzahl gesetzt werden**  
  `class-admin.php` + `class-database.php` – Neue Methode `get_group_sold_tickets()`. Beim Speichern wird geprüft ob `max_tickets < sold`, Warnung erscheint als Admin-Notice. ✅ Behoben

- [x] **Formular-Daten Transient läuft nach 5 Minuten ab**  
  `class-shortcode.php` – TTL von 300 auf 1800 Sekunden (30 Min.) erhöht. ✅ Behoben

- [x] **Versehentlich gelöschte Gruppe (ohne Anmeldungen) ist unwiderruflich weg**  
  `class-database.php` + `class-admin.php` – `save_groups()` gibt jetzt Gruppen-Nummern zurück, die nicht gelöscht werden konnten. Admin sieht Warnung. Gruppen ohne Anmeldungen: akzeptiertes Risiko, da explizites Entfernen + Speichern nötig ist. ✅ Teilweise behoben (Gruppen mit Anmeldungen geschützt)

---

## 🟠 Wichtig (Best Practice / UX)

- [x] **Erfolgsmeldung via URL-Parameter (`?ee_success=1`) – kein gutes Pattern**  
  `class-shortcode.php` – Erfolg wird via Transient (einmaliger zufälliger Key) übermittelt. URL-Parameter `?ee_msg=KEY` ist unratbar und Single-Use. Nach Anzeige wird der Parameter via `history.replaceState()` aus der Adresszeile entfernt. ✅ Behoben

- [x] **`?ee_error=…` bleibt nach Neuladen der Seite aktiv**  
  `class-shortcode.php` – Nach Anzeige des Fehlers entfernt `history.replaceState()` den `ee_error`-Parameter aus der URL. Transient wird bereits beim ersten Lesen gelöscht. ✅ Behoben

- [x] **Presale-Zeitstempel verwendet `current_time('timestamp')` (deprecated-Risiko)**  
  `class-shortcode.php` – Kommentar ergänzt: Daten werden in lokaler WP-Zeitzone gespeichert, `current_time('timestamp')` gibt ebenfalls lokale Zeit zurück → konsistent. `strtotime()`-Rückgabe wird auf `false` geprüft. ✅ Behoben

- [x] **Gruppen-Formular: Index-Lücken nach Entfernen einer Zeile**  
  `admin.js` – `reindexGroups()` wird nach jedem Hinzufügen und Entfernen aufgerufen. Indizes sind immer lückenlos 0, 1, 2, … ✅ Behoben

- [x] **`save_groups()` zeigt keine Warnung wenn Gruppe nicht gelöscht werden konnte**  
  `class-admin.php` / `class-database.php` – Warnung wird als Admin-Notice angezeigt. ✅ Behoben

- [x] **CSV-Export: `date()` statt `date_i18n()`**  
  `class-admin.php` – `date_i18n('Y-m-d')` für Dateinamen. ✅ Behoben

- [x] **`send_confirmation()` gibt `false` zurück wenn Betreff leer ist, ohne Fehlermeldung**  
  `class-email.php` – `error_log()` Eintrag wird geschrieben wenn E-Mail still verworfen wird. ✅ Behoben

- [x] **`easy_event_init()` lädt Shortcode-Klasse auch im Admin-Bereich**  
  `easy-event.php` – Shortcode wird nur geladen wenn `!is_admin()`. ✅ Behoben

- [x] **Keine Datenbankversion-Prüfung beim Update**  
  `class-database.php` + `easy-event.php` – Neue Methode `maybe_upgrade()`: Vergleicht gespeicherte DB-Version mit `DB_VERSION`. Läuft bei jedem `plugins_loaded`-Aufruf, führt `create_tables()` (mit `dbDelta`) nur bei Versionsabweichung aus. ✅ Behoben

---

## 🟡 Kleinere Verbesserungen (Code-Qualität)

- [x] **Toter Code: `page_test_email()` in `class-admin.php`**  
  Entfernt. ✅ Behoben

- [x] **Inkonsistente Sanitization bei Gruppen-Input**  
  `class-admin.php` – Gruppen-Array wird jetzt in `process_save_event()` bereits mit `absint()`/`sanitize_text_field()` aufbereitet bevor es an `save_groups()` übergeben wird. ✅ Behoben

- [x] **`strtotime()` ohne Fehlerprüfung**  
  `class-shortcode.php` – Alle `strtotime()`-Aufrufe prüfen jetzt auf `false` bevor der Wert in Vergleichen oder `date_i18n()` verwendet wird. ✅ Behoben

- [x] **Kein Capability-Check in `page_events()` und `page_registrations()`**  
  `class-admin.php` – `wp_die()` bei fehlendem `manage_options`-Capability. ✅ Behoben

- [x] **Kein Nonce beim Anzeigen des Edit-Formulars**  
  `class-admin.php` + `events-list.php` – Edit-Links werden mit `wp_nonce_url()` generiert (`easy_event_edit_{id}`). `page_events()` prüft den Nonce beim Bearbeiten. Redirect nach Speichern enthält ebenfalls den Nonce. ✅ Behoben

- [x] **JavaScript: `groupIndex` beginnt beim DOM-Zustand beim Laden**  
  Behoben durch `reindexGroups()` – wird via 🟠-Punkt oben abgedeckt. ✅ Behoben

- [ ] **`assets/css/public.css`: Mögliche Theme-Konflikte bei generischen Selektoren**  
  Selektoren wie `.easy-event-form label` könnten je nach Theme in Konflikt geraten. Akzeptiertes Risiko – alle Selektoren sind bereits unter `.easy-event-*`-Klassen geschachtelt.

---

---

## 🗑️ Ungenutzter Code / verwaiste Dateien

- [x] **`admin/views/test-email.php`** – View für das entfernte Test-E-Mail-Menü. Wurde von keiner PHP-Funktion mehr geladen. Datei gelöscht. ✅ Behoben
- [x] **`process_test_email()`** in `class-admin.php` – Zugehörige PHP-Methode entfernt. ✅ Behoben  
- [x] **`page_test_email()`** in `class-admin.php` – Zugehörige PHP-Methode entfernt. ✅ Behoben
- [x] **POST-Handler `easy_event_send_test`** in `handle_form_submissions()` – Toter Pfad entfernt. ✅ Behoben

---

---

## 🆕 Professionalisierung (nachträglich implementiert)

- [x] **Deinstallations-Hook** – `uninstall.php`: Entfernt beim Plugin-Löschen alle 3 Tabellen und die `easy_event_db_version`-Option. ✅
- [x] **Duplikat-E-Mail-Schutz** – Neue Spalte `allow_duplicate_email` in der Events-Tabelle. Checkbox im Admin (Event-Details-Tab). Prüfung atomar innerhalb der `save_registration()`-Transaktion. ✅
- [x] **Doppelabsenden-Schutz (Submit-Token)** – Beim Rendern des Formulars wird ein einmaliger Token als Transient gespeichert. Beim Absenden wird er geprüft und sofort gelöscht. Zweites Absenden desselben Formulars schlägt fehl mit erklärender Meldung. ✅
- [x] **Honeypot gegen Bots** – Verstecktes `ee_website`-Feld. Bots füllen es aus → stumme Erfolgs-Antwort (Bot weiss nicht, dass er abgefangen wurde). CSS versteckt es für echte Nutzer. ✅
- [x] **Paginierung Anmeldungsliste** – 50 Einträge pro Seite. `paginate_links()` zeigt WordPress-Standardnavigation. Neuer zählender Query `count_registrations_total()`. ✅
- [x] **PHP/WP-Mindestversion** – Plugin-Header: `Requires at least: 5.8`, `Requires PHP: 7.4`. ✅
- [x] **Plugin-Version** – Auf `1.1.0` angehoben, DB-Version auf `1.1` (löst `maybe_upgrade()` + `dbDelta` für neue Spalte aus). ✅

---

## ✅ Bereits korrekt umgesetzt

- Nonce-Prüfung bei allen Formular-Actions (`check_admin_referer`, `wp_verify_nonce`, `check_ajax_referer`)
- `wp_safe_redirect()` statt `header('Location: ...')`
- `absint()`, `sanitize_text_field()`, `sanitize_email()`, `esc_html()`, `esc_attr()`, `esc_url()` konsequent eingesetzt
- MySQL-Transaktion mit `SELECT ... FOR UPDATE` gegen Race Conditions bei Anmeldungen
- `wpdb->prepare()` bei allen DB-Queries
- `wp_kses_post()` für HTML-Inhalte (Beschreibung)
- `ABSPATH`-Check in jeder Datei
- Capability-Check (`manage_options`) vor allen Admin-Aktionen
- AJAX-Handler prüft Nonce + Capability
- Keine direkte Ausgabe von `$_POST`/`$_GET` ohne Escaping
- Transient-basiertes PRG-Pattern für Fehler- und Erfolgsfall
- `wp_validate_redirect()` für externe URL-Eingaben
