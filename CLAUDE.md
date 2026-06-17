# Easy Event – Projektregeln für Claude Code

## Projektstruktur

```
easy-event/                        ← Git-Repo-Root, direkt verbunden mit GitHub
├── easy-event.php                 ← Plugin-Einstiegspunkt, PUC-Init
├── composer.json / composer.lock  ← Abhängigkeiten (PUC)
├── readme.txt                     ← WordPress.org-Format, wird von PUC gelesen
├── uninstall.php                  ← Saubere Deinstallation
├── includes/
│   ├── class-database.php         ← Tabellen-Setup und DB-Migrationen
│   └── class-email.php            ← E-Mail-Versand mit Platzhaltern
├── admin/
│   ├── class-admin.php            ← Admin-Menü, Speichern, CSV-Export
│   └── views/
│       ├── event-edit.php         ← Event-Formular (Tabs: Details, Gruppen, E-Mail)
│       ├── events-list.php        ← Übersichtstabelle
│       └── registrations.php      ← Anmeldungsliste mit Paginierung
├── public/
│   ├── class-shortcode.php        ← Shortcode-Registrierung und Formularverarbeitung
│   └── views/form.php             ← Frontend-Anmeldeformular
├── assets/
│   ├── css/admin.css / public.css
│   └── js/admin.js / public.js
├── vendor/                        ← Composer-Abhängigkeiten (nicht in Git)
└── .github/workflows/release.yml  ← GitHub Actions: ZIP bei Tag erstellen
```

GitHub-Remote: https://github.com/tellysava-rgb/easy-event

## Git-Workflow

```bash
git add <datei>
git commit -m "Beschreibung"
git push origin main
```

Kein Force-Push. Kein `--no-verify`. Immer spezifische Dateien stagen (kein `git add .`).

## Versionierung

Schema: `MAJOR.MINOR.PATCH`

| Änderungsart | Beispiel |
|---|---|
| Mehrere sichtbare Änderungen / neue Funktionen | MINOR: 1.2.8 → 1.3.0 |
| Einzelner Bugfix | PATCH: 1.3.0 → 1.3.1 |
| Breaking Change / DB-Umbau | MAJOR: 1.x.x → 2.0.0 |

Version immer an **drei** Stellen anpassen:
1. `easy-event.php` — Header `* Version:` und `define('EASY_EVENT_VERSION', ...)`
2. `readme.txt` — `Stable tag:` und neuer Changelog-Eintrag unter `== Changelog ==`

## GitHub-Release — PFLICHT für Plugin-Updates

Der Plugin Update Checker (PUC) prüft GitHub-Releases. Ohne Release sieht WordPress kein Update, auch wenn der Code bereits gepusht ist.

**Tag und Release werden nur auf explizite Anweisung erstellt.** Nach der Anweisung:

```bash
git tag vX.Y.Z
git push origin vX.Y.Z
```

→ GitHub Actions (`release.yml`) erstellt automatisch `easy-event-X.Y.Z.zip` und veröffentlicht es als GitHub-Release. Das ZIP enthält keine dev-Dependencies, kein `.git/`, kein `.github/`.

## Plugin Update Checker (PUC)

- Abhängigkeit: `yahnis-elsts/plugin-update-checker` via Composer (aktuell v5.7)
- PUC-Initialisierung im `init`-Hook in `easy-event.php`
- `vendor/` ist in `.gitignore` — der CI-Runner installiert Composer-Abhängigkeiten selbst
- PUC liest `readme.txt` aus dem GitHub-Repo-Root für das "Details anzeigen"-Popup in WordPress

## Datenbankmigrationen

Die aktuelle DB-Schemaversion steht als Konstante in `class-database.php`:

```php
const DB_VERSION = '1.6';
```

Bei Schemaänderungen:
1. `DB_VERSION` erhöhen (z.B. `'1.6'` → `'1.7'`)
2. Migrationscode in `maybe_upgrade()` ergänzen
3. `create_tables()` aktualisieren

`maybe_upgrade()` wird bei jedem `plugins_loaded` aufgerufen und führt Migrationen nur aus, wenn die gespeicherte DB-Version abweicht.

## E-Mail-Platzhalter

Verfügbare Platzhalter in Bestätigungs- und Admin-E-Mails:

`{vorname}` `{nachname}` `{email}` `{anzahl_personen}` `{gruppe_beschreibung}` `{event_name}` `{event_datum}`

Admin-Benachrichtigungstext ist pro Event im E-Mail-Tab konfigurierbar.

## Shortcode

```
[easy_event id="X"]
```

Die Event-ID `X` wird in der Ereignisliste im Admin angezeigt. Mehrere Events parallel möglich.
