# Deployment-Dokumentation

Dieses Dokument beschreibt das einmalige Server-Setup, die benötigten GitHub-Secrets
und den vollständigen Deployment-Prozess für `landolsi-typo3-camino14` auf CloudPanel.

**Server:** `host7.rosenheim-web-services.de`  
**User:** `landolsi-camino14`  
**Deploy-Pfad:** `/home/landolsi-camino14/htdocs/camino14.landolsi.de`

---

## Deployment-Strategie

Code wird auf CI (GitHub Actions) gebaut und per `rsync` auf den Server übertragen.
Der Server muss **kein Composer** installiert haben — `vendor/` kommt bereits fertig gebaut an.

```
Tag pushen (v0.3.0)
   → GitHub Actions: checkout + composer install --no-dev
   → rsync: Code + vendor/ → Server
   → SSH: TYPO3 cache:flush
```

**Trigger:** Push eines Tags nach dem Muster `v*` (z.B. `v0.3.0`, `v1.0.0`)

---

## Schritt 1: Deploy-Key auf Server hinterlegen

Ein dedizierter ed25519-Key wurde für GitHub Actions generiert.

**Public Key → auf den Server kopieren:**

```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIHf3jKOdlvGVpPCKSZsEkojwiqRINEb3VMVy+oVjzOUs github-actions-deploy@camino14
```

Auf dem Server als `landolsi-camino14` ausführen:

```bash
echo "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIHf3jKOdlvGVpPCKSZsEkojwiqRINEb3VMVy+oVjzOUs github-actions-deploy@camino14" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

---

## Schritt 2: GitHub Secrets konfigurieren

GitHub Repository öffnen → **Settings → Environments → production → Environment secrets**

| Secret | Wert |
|--------|------|
| `PROD_HOST` | `host7.rosenheim-web-services.de` |
| `PROD_PORT` | `22` (oder leer lassen für Default) |
| `PROD_USER` | `landolsi-camino14` |
| `PROD_SSH_PRIVATE_KEY` | *(privater Key — wird separat übergeben)* |
| `PROD_DEPLOY_PATH` | `/home/landolsi-camino14/htdocs/camino14.landolsi.de` |
| `PROD_PHP_BIN` | `php` (ggf. `/usr/bin/php8.5` wenn nötig) |

### Optional / Fallback-Defaults

Wenn `PROD_PORT` oder `PROD_PHP_BIN` leer sind, verwendet der Workflow:
- Port: `22`
- PHP: `php`

---

## Schritt 3: GitHub Environment anlegen

1. GitHub Repository → **Settings → Environments → New environment**
2. Name: `production`
3. **Required reviewers** aktivieren (empfohlen — verhindert versehentliche Deploys)
4. Deployment branches: `main` + Tags `v*`

---

## Schritt 4: Einmalige Server-Vorbereitung

### Deploy-Verzeichnis anlegen

```bash
ssh landolsi-camino14@host7.rosenheim-web-services.de
mkdir -p /home/landolsi-camino14/htdocs/camino14.landolsi.de
mkdir -p /home/landolsi-camino14/htdocs/camino14.landolsi.de/var/cache
mkdir -p /home/landolsi-camino14/htdocs/camino14.landolsi.de/var/log
mkdir -p /home/landolsi-camino14/htdocs/camino14.landolsi.de/var/labels
mkdir -p /home/landolsi-camino14/htdocs/camino14.landolsi.de/public/fileadmin
mkdir -p /home/landolsi-camino14/htdocs/camino14.landolsi.de/public/typo3temp
chmod -R 2775 /home/landolsi-camino14/htdocs/camino14.landolsi.de/var/
chmod -R 2775 /home/landolsi-camino14/htdocs/camino14.landolsi.de/public/fileadmin
chmod -R 2775 /home/landolsi-camino14/htdocs/camino14.landolsi.de/public/typo3temp
```

### CloudPanel Docroot setzen

In CloudPanel → Site → **camino14.landolsi.de** → Document Root:
```
/home/landolsi-camino14/htdocs/camino14.landolsi.de/public
```

### TYPO3 Systemkonfiguration anlegen

`config/system/settings.php` wird **nicht** per Deployment übertragen.
Diese Datei muss einmalig manuell auf dem Server angelegt werden:

```bash
nano /home/landolsi-camino14/htdocs/camino14.landolsi.de/config/system/settings.php
```

Inhalt (Werte anpassen):

```php
<?php
return [
    'DB' => [
        'Connections' => [
            'Default' => [
                'driver'   => 'mysqli',
                'host'     => '127.0.0.1',
                'port'     => 3306,
                'dbname'   => '<prod-db-name>',
                'user'     => '<prod-db-user>',
                'password' => '<prod-db-password>',
                'charset'  => 'utf8mb4',
            ],
        ],
    ],
    'SYS' => [
        'sitename'           => 'camino14.landolsi.de',
        'encryptionKey'      => '<langer-zufälliger-schlüssel>',
        'trustedHostsPattern'=> 'camino14\\.landolsi\\.de',
    ],
    'MAIL' => [
        'transport'             => 'smtp',
        'transport_smtp_server' => 'localhost:25',
    ],
];
```

Encryption Key erzeugen:
```bash
php -r "echo bin2hex(random_bytes(50)) . PHP_EOL;"
```

---

## Deployment starten

### Automatisch via Tag

```bash
# Lokal: neuen Tag setzen und pushen
git tag v0.3.0
git push origin v0.3.0
# → GitHub Actions startet automatisch deploy-production
```

### Manuell via GitHub UI

1. GitHub Repository → **Actions → Deploy Production**
2. **Run workflow** → Branch/Tag auswählen → **Run workflow**

---

## ddev pull production

Zieht DB + fileadmin von Production nach lokal:

```bash
# Einmalig: Zugangsdaten konfigurieren
cp .ddev/.env.pullpush.example .ddev/.env.pullpush
# .ddev/.env.pullpush editieren

# Ausführen
ddev pull production
```

Voraussetzungen:
- SSH-Zugang zu `landolsi-camino14@host7.rosenheim-web-services.de` lokal eingerichtet
- `mysqldump` auf dem Production-Server verfügbar

---

## ddev push production

> **⚠️ ACHTUNG: Überschreibt die Produktionsdatenbank! Nur in kontrollierten Situationen verwenden.**

```bash
ddev push production
```

**Für reguläre Code-Deployments NIE die DB per push übertragen — dafür ist GitHub Actions zuständig.**

---

## Hinweise zu Konfigurationsdateien

| Datei | Im Repo? | Warum |
|-------|----------|-------|
| `config/system/settings.php` | ❌ Nein | Enthält DB-Credentials und Encryption Key |
| `config/system/additional.php` | ❌ Nein | Enthält ggf. umgebungsspezifische Overrides |
| `config/sites/*/config.yaml` | ✅ Ja | Site-Konfiguration, keine Secrets |
| `.ddev/.env.pullpush` | ❌ Nein | Enthält Production-Zugangsdaten |
| `.ddev/.env.pullpush.example` | ✅ Ja | Vorlage ohne echte Werte |
| `.ddev/config.yaml` | ✅ Ja | DDEV-Konfiguration ohne Secrets |
