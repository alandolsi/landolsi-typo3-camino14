# Deployment-Dokumentation

Dieses Dokument beschreibt das einmalige Server-Setup, die benötigten GitHub-Secrets
und den vollständigen Deployment-Prozess für `landolsi-typo3-camino14` auf CloudPanel.

---

## Benötigte GitHub Secrets

Alle Secrets müssen im GitHub Repository unter  
**Settings → Secrets and variables → Actions** eingetragen werden.

| Secret | Beschreibung | Beispiel |
|--------|-------------|---------|
| `PROD_HOST` | Hostname oder IP des Servers | `server.example.com` |
| `PROD_PORT` | SSH-Port (Default: 22) | `22` |
| `PROD_USER` | SSH-Benutzername auf dem Server | `camino14` |
| `PROD_SSH_PRIVATE_KEY` | Privater SSH-Schlüssel (vollständiger Inhalt, inkl. Header) | `-----BEGIN OPENSSH...` |
| `PROD_DEPLOY_PATH` | Absoluter Pfad zum Deploy-Verzeichnis auf dem Server | `/home/camino14/htdocs/camino14.landolsi.de` |
| `PROD_PHP_BIN` | Pfad zum PHP-Binary auf dem Server | `/usr/bin/php8.5` |
| `PROD_COMPOSER_BIN` | Pfad zum Composer-Binary (optional, Default: `composer`) | `/usr/local/bin/composer` |

### Optionale Fallback-Werte

Wenn `PROD_PORT`, `PROD_PHP_BIN` oder `PROD_COMPOSER_BIN` leer sind, verwendet der Workflow:
- Port: `22`
- PHP: `php`
- Composer: `composer`

---

## Empfohlene GitHub-Environment-Konfiguration

1. GitHub Repository öffnen → **Settings → Environments → New environment**
2. Name: `production`
3. **Required reviewers** aktivieren (mindestens 1 Person) — verhindert versehentliche Deploys
4. Optional: **Deployment branches** auf `main` beschränken

---

## Empfohlener CloudPanel Deploy-Pfad

```
/home/<site-user>/htdocs/camino14.landolsi.de/
```

Der Docroot im CloudPanel sollte auf den `public/`-Unterordner zeigen:

```
/home/<site-user>/htdocs/camino14.landolsi.de/public
```

---

## Einmalige Server-Vorbereitung

### 1. PHP-Version in CloudPanel

- Stelle sicher, dass PHP 8.5 auf dem Server verfügbar ist
- In CloudPanel: **Sites → camino14.landolsi.de → PHP version**

### 2. Deploy-Verzeichnis anlegen

```bash
mkdir -p /home/<site-user>/htdocs/camino14.landolsi.de
```

### 3. SSH-Key für GitHub Actions anlegen

```bash
# Auf dem Server als site-user ausführen:
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_deploy -N ""

# Öffentlichen Key zu authorized_keys hinzufügen:
cat ~/.ssh/github_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys

# Privaten Key anzeigen (in GitHub Secrets eintragen):
cat ~/.ssh/github_deploy
```

### 4. TYPO3-Systemkonfiguration anlegen

`config/system/settings.php` wird **nicht** per Deployment übertragen.
Sie muss einmalig manuell auf dem Server angelegt werden.

Vorlage (Werte anpassen):

```php
<?php
return [
    'DB' => [
        'Connections' => [
            'Default' => [
                'driver' => 'mysqli',
                'host' => '127.0.0.1',
                'port' => 3306,
                'dbname' => '<prod-db-name>',
                'user' => '<prod-db-user>',
                'password' => '<prod-db-password>',
                'charset' => 'utf8mb4',
            ],
        ],
    ],
    'SYS' => [
        'sitename' => 'camino14.landolsi.de',
        'encryptionKey' => '<langer-zufälliger-schlüssel>',
        'trustedHostsPattern' => 'camino14\\.landolsi\\.de',
    ],
    'MAIL' => [
        'transport' => 'smtp',
        'transport_smtp_server' => 'localhost:25',
    ],
];
```

Wichtig: Ein sicherer `encryptionKey` kann mit folgendem Befehl erzeugt werden:

```bash
php -r "echo bin2hex(random_bytes(50)) . PHP_EOL;"
```

### 5. Verzeichnisse und Berechtigungen

```bash
# Auf dem Server:
mkdir -p public/fileadmin public/typo3temp var/cache var/log var/labels
chmod -R 2775 public/fileadmin public/typo3temp var/
```

### 6. Composer global installieren (falls nicht vorhanden)

```bash
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

---

## Initialer Deployment-Ablauf

1. GitHub Secrets befüllen (siehe oben)
2. GitHub Environment `production` anlegen
3. SSH-Key auf dem Server hinterlegen
4. Deploy-Verzeichnis und `settings.php` auf dem Server anlegen
5. Ersten Deployment-Workflow manuell starten (GitHub Actions → deploy-production → Run workflow)
6. Nach Deployment: `ddev typo3 setup` würde lokale DB überschreiben — **auf Prod nicht ausführen**

---

## Workflow manuell starten

1. GitHub Repository öffnen
2. **Actions → Deploy Production**
3. **Run workflow** → Branch `main` auswählen → **Run workflow**

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
- SSH-Key lokal vorhanden (`~/.ssh/id_rsa` oder angepasst)
- SSH-Zugang zum Production-Server
- `mysqldump` auf dem Production-Server verfügbar

---

## ddev push production

> **⚠️ ACHTUNG: Überschreibt die Produktionsdatenbank!**

```bash
ddev push production
# oder:
ddev push production
```

Dieser Command sollte **nur** in kontrollierten Situationen verwendet werden, z.B.:

- Initiales Deployment von Testdaten auf einen frischen Server
- Datenabgleich in einer isolierten Staging-Umgebung

**Für reguläre Production-Deployments niemals die Datenbank per push übertragen!**

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
