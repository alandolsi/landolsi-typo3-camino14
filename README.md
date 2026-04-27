# landolsi-typo3-camino14

TYPO3 14 LTS Composer-basiertes Testprojekt mit dem Camino-Theme.  
Lokale Entwicklungsumgebung via DDEV, CI/CD über GitHub Actions, Deployment auf CloudPanel.

---

## Voraussetzungen

| Tool | Mindestversion |
|------|---------------|
| [DDEV](https://ddev.readthedocs.io/) | 1.23+ |
| Docker | 24+ |
| PHP (Host, nur für CI) | 8.5 |
| Composer | 2 |
| Git | 2.x |

---

## Lokale Installation

```bash
# Repo klonen
git clone git@github.com:alandolsi/landolsi-typo3-camino14.git
cd landolsi-typo3-camino14

# DDEV starten
ddev start

# Abhängigkeiten installieren
ddev composer install

# TYPO3 einrichten (einmalig bei Erstinstallation oder frischer DB)
ddev typo3 setup \
  --server-type=other \
  --driver=mysqli \
  --host=db --port=3306 \
  --dbname=db --username=db --password=db \
  --create-site="https://camino14.ddev.site/" \
  --no-interaction

# Backend öffnen
ddev launch /typo3/
```

> **Hinweis:** `config/system/settings.php` und `config/system/additional.php` werden von
> TYPO3/DDEV automatisch erzeugt und sind bewusst **nicht** im Repository.

---

## Lokale URLs

| Zweck | URL |
|-------|-----|
| Frontend | https://camino14.ddev.site/ |
| Backend | https://camino14.ddev.site/typo3/ |
| Mailpit | https://camino14.ddev.site:8026/ |

> Die Produktionsdomain `camino14.landolsi.de` zeigt via DNS-A-Record auf den CloudPanel-Server — sie wird lokal nicht benötigt.

---

## Wichtige DDEV-Kommandos

```bash
ddev start                       # DDEV starten
ddev stop                        # DDEV stoppen
ddev restart                     # DDEV neu starten
ddev describe                    # Projektinfos und URLs
ddev ssh                         # Shell im Web-Container
ddev composer <cmd>              # Composer im Container ausführen
ddev typo3 <cmd>                 # TYPO3 CLI im Container ausführen
ddev import-db --file=dump.sql   # DB-Dump importieren
ddev export-db > dump.sql        # DB exportieren
ddev logs                        # Container-Logs

# Production-Sync via DDEV Provider (SSH-Key + .ddev/.env.pullpush erforderlich)
ddev pull production             # DB + fileadmin von Production holen
ddev push production             # Lokal nach Production übertragen (⚠️ DDEV fragt nach Bestätigung)
```

---

## TYPO3-Kommandos

```bash
ddev typo3 -V                           # Version anzeigen
ddev typo3 cache:flush                  # Cache leeren
ddev typo3 extension:list               # Extensions auflisten
ddev typo3 extension:activate <key>     # Extension aktivieren
ddev typo3 database:updateschema        # DB-Schema aktualisieren
ddev typo3 cleanup:missingrelations     # Fehlende Referenzen bereinigen
ddev typo3 backend:user:create          # Backend-User anlegen
```

---

## Projektstruktur

```
landolsi-typo3-camino14/
├── .ddev/
│   ├── config.yaml                # DDEV-Projektconfig
│   ├── .env.pullpush.example      # Vorlage für Produktionszugänge
│   └── providers/
│       └── production.yaml        # DDEV Provider für ddev pull/push production
├── .github/workflows/
│   ├── ci.yml                     # CI: validate + build bei Push/PR
│   └── deploy-production.yml      # CD: Code-Deployment auf CloudPanel via rsync
├── config/                        # TYPO3-Konfiguration
│   ├── sites/                     # Site-Konfigurationen (committed)
│   └── system/                    # ⚠️ settings.php + additional.php NICHT im Repo
├── docs/
│   ├── content-blocks.md          # Redaktionelle Content-Blocks-Hinweise
│   └── deployment.md              # Server-Setup, Secrets, Deployment-Anleitung
├── packages/site-package/          # Projekt-Site-Package, Assets und Content Blocks
├── public/                        # Docroot
├── vendor/                        # Composer-Abhängigkeiten (ignoriert)
├── composer.json
├── composer.lock
└── README.md
```

---

## GitHub / Branches

| Branch | Zweck |
|--------|-------|
| `main` | Production-Stand, stable |
| `develop` | Integrations-Branch für Features |
| `feature/<name>` | Feature-Branches, PR auf develop |
| `hotfix/<name>` | Hotfixes direkt auf main |

**Workflow:** `feature/*` → PR → `develop` → PR → `main` → automatisches Deployment

---

## CI (GitHub Actions)

Die CI-Pipeline läuft bei jedem Push auf `main`/`develop` und bei Pull Requests:

| Job | Was passiert |
|-----|-------------|
| `validate` | `composer validate --strict` |
| `build` | `composer install`, TYPO3-Version prüfen, JS-Syntax prüfen, Content Blocks linten, Autoload verifizieren |

Datei: `.github/workflows/ci.yml`

---

## CD / Deployment

Das Production-Deployment erfolgt **code-only** via `rsync + SSH` auf den CloudPanel-Server.

- **Trigger:** Push eines Tags nach Muster `v*` oder manuell via `workflow_dispatch`
- **GitHub Environment:** `production` (Required Reviewers empfohlen)
- **Was deployed wird:** Code — kein Datenbankdump, kein fileadmin
- **Ausgeschlossen:** `.git/`, `.github/`, `.ddev/`, `var/`, `public/fileadmin/`, `config/system/settings.php`
- **Sicherungen:** vor dem rsync werden `fileadmin` und, falls `mysqldump` verfügbar ist, die Datenbank in `../backups` gesichert
- **Healthcheck:** nach dem Deploy werden zentrale Seiten auf HTTP 200 und TYPO3-Oops-Marker geprüft

Benötigte Secrets → siehe `docs/deployment.md`

---

## Production Sync via DDEV Provider

Für den manuellen Datenabgleich zwischen Lokal und Production verwendet dieses Projekt
den **DDEV Provider** `.ddev/providers/production.yaml`.

### Setup (einmalig)

```bash
cp .ddev/.env.pullpush.example .ddev/.env.pullpush
# .ddev/.env.pullpush mit echten Zugangsdaten befüllen
# Diese Datei wird NIEMALS committed
```

### ddev pull production

Zieht DB + fileadmin von Production lokal:

```bash
ddev pull production
```

DDEV führt aus: SSH-Check → `mysqldump` remote → `ddev import-db` lokal → `rsync` fileadmin

### ddev push production ⚠️

> **ACHTUNG: Überschreibt die Produktionsdatenbank und fileadmin!**  
> DDEV fragt vor dem Push explizit nach einer Bestätigung.

```bash
ddev push production
# DDEV fragt: "Are you sure you want to push to production? (y/N)"
```

Details und Voraussetzungen: `docs/deployment.md`

---

## Content Blocks / Redaktion

Das Projekt nutzt `friendsoftypo3/content-blocks` für eigene redaktionelle Inhaltselemente im Site-Package.
Aktuell enthalten ist ein erstes Element `Camino CTA` mit Headline, Kicker, Richtext, Button-Link und Vorschaubild inklusive Backend-Preview.

Dateien:

- `packages/site-package/ContentBlocks/ContentElements/camino-cta/config.yaml`
- `packages/site-package/ContentBlocks/ContentElements/camino-cta/templates/frontend.fluid.html`
- `packages/site-package/ContentBlocks/ContentElements/camino-cta/templates/backend-preview.fluid.html`
- `packages/site-package/Resources/Public/content-blocks/content-blocks.css`

Nach Änderungen an Content-Block-Konfigurationen lokal ausführen:

```bash
ddev typo3 content-blocks:lint
ddev typo3 cache:flush -g system
ddev typo3 extension:setup --extension=site_package
```

Details: `docs/content-blocks.md`

---

## Sicherheitshinweise

- `config/system/settings.php` enthält DB-Credentials — **nie** committen
- `.ddev/.env.pullpush` enthält Production-Zugangsdaten — **nie** committen
- Alle Produktionszugriffe laufen SSH-Key-basiert
- GitHub Secrets werden in keinem Log ausgegeben
- Production-Deployment ist code-only — es läuft nur das notwendige Extension-Schema-Setup, keine Inhaltsmigration
- OpenPanel-Client-Secret wird nicht im Frontend verwendet und nicht committed

---

## Cookie / Consent Banner

Das Projekt nutzt eine schlanke Consent-Lösung im eigenen Site-Package statt einer
zusätzlichen TYPO3-Extension. Das Banner verwaltet Kategorien für:

- technisch notwendig
- Komfort-Einstellungen
- Statistik
- Marketing

Die Statistik-Kategorie aktiviert OpenPanel-Tracking für Seitenaufrufe auf der
Production-Domain `camino14.landolsi.de`. Lokale DDEV- und LAN-Aufrufe werden
nicht getrackt. Die Komfort-Kategorie steuert die dauerhafte Speicherung des
Camino-Farbschemas im Browser. Marketing-Dienste sind aktuell nicht aktiv.

Dateien:

- `packages/site-package/Resources/Public/cookie-consent/cookie-consent.css`
- `packages/site-package/Resources/Public/cookie-consent/cookie-consent.js`
- `packages/site-package/Resources/Public/openpanel/openpanel-tracking.js`

OpenPanel nutzt die öffentliche Client-ID und die API-URL der Instanz. Der
Client-Secret ist nur für serverseitige Events vorgesehen und wird nicht im
Frontend verwendet oder committed.

---

## Roadmap / Nächste Schritte

- [ ] GitHub Environment `production` mit Required Reviewers anlegen
- [ ] GitHub Secrets befüllen (siehe `docs/deployment.md`)
- [ ] SSH-Key auf CloudPanel-Server hinterlegen
- [ ] `config/system/settings.php` auf Production anlegen
- [ ] Erste Inhalte mit dem Content Block `Camino CTA` redaktionell pflegen
- [ ] Optional: Homepage-Carousel in einem nächsten Schritt vollständig als Content Block modellieren
