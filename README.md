# landolsi-typo3-camino14

[![CI](https://github.com/alandolsi/landolsi-typo3-camino14/actions/workflows/ci.yml/badge.svg)](https://github.com/alandolsi/landolsi-typo3-camino14/actions/workflows/ci.yml)
[![Deploy Production](https://github.com/alandolsi/landolsi-typo3-camino14/actions/workflows/deploy-production.yml/badge.svg)](https://github.com/alandolsi/landolsi-typo3-camino14/actions/workflows/deploy-production.yml)

Professionelles TYPO3-14-LTS-Demoprojekt mit dem Camino Theme, DDEV, Composer, eigenem Site-Package, Content Blocks, Consent Banner, OpenPanel-Tracking und GitHub-Actions-Deployment auf CloudPanel.

**Live-Demo:** https://camino14.landolsi.de

**Repository:** https://github.com/alandolsi/landolsi-typo3-camino14

---

## Zweck des Projekts

Dieses Repository zeigt, wie schnell sich mit TYPO3 14 LTS und dem Camino Theme eine moderne, mehrsprachige Demo-Website produktionsnah aufsetzen lässt. Der Fokus liegt auf einem nachvollziehbaren Setup für Entwicklung, Redaktion, Deployment und Betrieb:

- Composer-basierte TYPO3-14-LTS-Installation
- lokales Setup mit DDEV
- Camino Theme als visuelle Basis
- eigenes Site-Package für Projektassets und Content Blocks
- Consent-gesteuertes OpenPanel-Tracking
- GitHub Actions für CI und Production Deployment
- CloudPanel-Zielsystem mit separatem Daten-/Dateisync

---

## Tech Stack

| Bereich | Technologie |
|--------|-------------|
| CMS | TYPO3 14 LTS |
| Theme | `typo3/theme-camino` |
| Content-Modellierung | `friendsoftypo3/content-blocks` |
| PHP | 8.5 |
| Paketverwaltung | Composer 2 |
| Lokale Umgebung | DDEV |
| CI/CD | GitHub Actions |
| Deployment | rsync + SSH auf CloudPanel |
| Tracking | OpenPanel, consent-gesteuert |

---

## Voraussetzungen

| Tool | Mindestversion |
|------|---------------|
| [DDEV](https://ddev.readthedocs.io/) | 1.23+ |
| Docker | 24+ |
| Git | 2.x |
| Composer | 2 |

PHP muss lokal nicht direkt installiert sein, wenn alle Befehle über DDEV ausgeführt werden.

---

## Lokale Installation

```bash
git clone git@github.com:alandolsi/landolsi-typo3-camino14.git
cd landolsi-typo3-camino14

ddev start
ddev composer install
```

Bei einer frischen Datenbank kann TYPO3 lokal initial eingerichtet werden:

```bash
ddev typo3 setup \
  --server-type=other \
  --driver=mysqli \
  --host=db \
  --port=3306 \
  --dbname=db \
  --username=db \
  --password=db \
  --create-site="https://camino14.ddev.site/" \
  --no-interaction
```

Danach das Backend öffnen:

```bash
ddev launch /typo3/
```

`config/system/settings.php` und `config/system/additional.php` werden lokal bzw. auf Production generiert und sind bewusst nicht versioniert.

---

## URLs

| Umgebung | Zweck | URL |
|---------|-------|-----|
| Production | Frontend | https://camino14.landolsi.de |
| Lokal | Frontend | https://camino14.ddev.site/ |
| Lokal | Backend | https://camino14.ddev.site/typo3/ |
| Lokal | Mailpit | https://camino14.ddev.site:8026/ |

---

## Wichtige DDEV-Kommandos

```bash
ddev start                       # Projekt starten
ddev stop                        # Projekt stoppen
ddev restart                     # Container neu starten
ddev describe                    # URLs und Verbindungsdaten anzeigen
ddev ssh                         # Shell im Web-Container
ddev composer <cmd>              # Composer im Container ausführen
ddev typo3 <cmd>                 # TYPO3 CLI ausführen
ddev import-db --file=dump.sql   # Datenbank importieren
ddev export-db > dump.sql        # Datenbank exportieren
ddev logs                        # Container-Logs anzeigen
```

Production-Sync über den DDEV Provider:

```bash
ddev pull production             # DB + fileadmin von Production holen
ddev push production             # lokal nach Production pushen, mit Schutzabfrage
```

Für den Sync wird eine lokale `.ddev/.env.pullpush` benötigt. Die Vorlage liegt in `.ddev/.env.pullpush.example`.

---

## TYPO3-Kommandos

```bash
ddev typo3 -V                                  # TYPO3-Version anzeigen
ddev typo3 cache:flush                         # Cache leeren
ddev typo3 extension:list                      # Extensions auflisten
ddev typo3 content-blocks:lint                 # Content Blocks validieren
ddev typo3 extension:setup --extension=site_package
ddev typo3 backend:user:create                 # Backend-User anlegen
```

---

## Features

### Camino Theme

Camino liefert die visuelle Basis für die Demo-Website. Projektindividuelle Anpassungen liegen im Site-Package, nicht im Theme selbst.

### Site-Package

Das lokale Site-Package `landolsi/site-package` bündelt:

- CSS/JavaScript für Consent Banner, Color Switcher, Carousel und Content Blocks
- TypoScript-Site-Set
- eigene Content Blocks
- frontendnahe Projektlogik ohne Build-Pipeline

### Content Blocks

Das Projekt nutzt `friendsoftypo3/content-blocks` für redaktionelle Elemente. Enthalten ist aktuell das Element **Camino CTA** mit:

- Kicker
- Überschrift
- Richtext
- Button-Link
- optionalem Vorschaubild
- Backend-Preview mit Thumbnail

Details: [`docs/content-blocks.md`](docs/content-blocks.md)

### Consent Banner und OpenPanel

Das Consent Banner ist bewusst schlank im Site-Package umgesetzt. OpenPanel-Tracking wird nur aktiviert, wenn Besucher die Statistik-Kategorie akzeptieren. Lokale DDEV- und LAN-Aufrufe werden nicht getrackt.

Der OpenPanel Client Secret ist nicht für Frontend-Code gedacht und wird nicht committed.

---

## Projektstruktur

```text
landolsi-typo3-camino14/
├── .ddev/                         # DDEV-Konfiguration und Production Provider
├── .github/workflows/             # CI und Production Deployment
├── config/
│   ├── sites/                     # versionierte TYPO3 Site-Konfiguration
│   └── system/                    # lokale/produktive Secrets, nicht versioniert
├── docs/                          # Deployment- und Content-Blocks-Doku
├── packages/site-package/          # Site-Package, Assets, Content Blocks
├── public/                        # TYPO3 Docroot
├── composer.json
├── composer.lock
└── README.md
```

---

## GitHub / Branching

| Branch | Zweck |
|--------|-------|
| `main` | stabiler Production-Stand |
| `develop` | Integrationsbranch |
| `feature/<name>` | neue Features und Optimierungen |
| `hotfix/<name>` | dringende Produktionsfixes |

Änderungen laufen über Pull Requests. Production Deployments werden über versionierte Tags ausgelöst.

---

## CI

Die CI läuft bei Pushes und Pull Requests auf `main` und `develop`.

| Job | Prüfung |
|-----|---------|
| `Validate Composer` | `composer validate --strict`, Secret-Regressionscheck |
| `Build & Smoke Test` | Composer Install, TYPO3-Version, JS-Syntax, Content-Blocks-Lint, Asset-Smoke-Checks |

Workflow: [`.github/workflows/ci.yml`](.github/workflows/ci.yml)

---

## CD / Production Deployment

Production Deployments laufen code-only über GitHub Actions:

1. Tag nach Muster `v*` pushen, z. B. `v0.3.8`
2. GitHub Actions baut `vendor/` mit Composer
3. Vor dem Deploy werden `fileadmin` und, falls möglich, die DB gesichert
4. Code wird per `rsync --delete` auf den CloudPanel-Server übertragen
5. `public/fileadmin/`, `var/` und produktive Systemkonfiguration bleiben unangetastet
6. TYPO3 Post-Setup und Cache-Flush laufen per SSH
7. zentrale Seiten werden per HTTP-Healthcheck geprüft

Workflow: [`.github/workflows/deploy-production.yml`](.github/workflows/deploy-production.yml)

Details: [`docs/deployment.md`](docs/deployment.md)

---

## Production Sync via DDEV

Code-Deployment und Daten-/Dateisync sind bewusst getrennt:

- GitHub Actions deployed Code
- DDEV Provider synchronisiert DB und `public/fileadmin`

```bash
cp .ddev/.env.pullpush.example .ddev/.env.pullpush
# .ddev/.env.pullpush mit echten Zugangsdaten befüllen

ddev pull production
```

`ddev push production` ist möglich, aber absichtlich mit einer starken Schutzabfrage abgesichert, weil damit produktive Daten überschrieben werden können.

---

## Sicherheit

- keine Secrets im Repository
- `config/system/settings.php` und `config/system/additional.php` sind ignoriert
- `.ddev/.env.pullpush` ist ignoriert
- Production Deployments erhalten Secrets ausschließlich über GitHub Environment Secrets
- `public/fileadmin/` wird nicht durch Code-Deployments gelöscht
- OpenPanel läuft consent-gesteuert und ohne Frontend-Secret

---

## Roadmap

- Homepage Carousel als redaktionellen Content Block modellieren
- weitere Camino-nahe Content Blocks ergänzen
- strukturierte Demo-Inhalte für Blog/News vorbereiten
- Monitoring und Alerting rund um Production Healthchecks ausbauen
- Screenshots und Architekturdiagramm für die öffentliche Projektdoku ergänzen
