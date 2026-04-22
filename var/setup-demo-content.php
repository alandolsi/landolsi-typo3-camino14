<?php

declare(strict_types=1);

/**
 * TYPO3 14 + Camino – Demo-Inhalte Setup-Skript
 *
 * Erstellt Seiten, Inhaltselemente, Bilder (Unsplash) und Footer-Inhalte.
 * Nur für lokale Entwicklung gedacht – niemals auf Produktion ausführen!
 *
 * Aufruf: ddev exec php var/setup-demo-content.php
 */

// ---------------------------------------------------------------------------
// Bootstrap: Direkte DB-Verbindung (kein TYPO3-Bootstrap nötig)
// ---------------------------------------------------------------------------

$pdo = new PDO('mysql:host=db;dbname=db;charset=utf8mb4', 'db', 'db', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$now = time();
$fileadminPath = '/var/www/html/public/fileadmin';
$demoDir       = $fileadminPath . '/demo';

if (!is_dir($demoDir)) {
    mkdir($demoDir, 0755, true);
}

echo "\n🚀 Landolsi Webdesign – Demo-Inhalte Setup\n";
echo str_repeat('=', 50) . "\n\n";

// ---------------------------------------------------------------------------
// HILFSFUNKTIONEN
// ---------------------------------------------------------------------------

function insertContent(PDO $pdo, array $data): int
{
    $defaults = [
        'tstamp'                        => time(),
        'crdate'                        => time(),
        'sys_language_uid'              => 0,
        'l18n_parent'                   => 0,   // tt_content uses l18n_parent (not l10n_parent)
        'hidden'                        => 0,
        'deleted'                       => 0,
        'CType'                         => 'text',
        'header'                        => '',
        'header_layout'                 => 0,
        'header_position'               => '',
        'subheader'                     => '',
        'bodytext'                      => '',
        'image'                         => 0,
        'assets'                        => 0,
        'imagewidth'                    => 0,
        'imageheight'                   => 0,
        'imageorient'                   => 0,
        'imageborder'                   => 0,
        'image_zoom'                    => 0,
        'imagecols'                     => 0,
        'frame_class'                   => 'default',
        'space_before_class'            => '',
        'space_after_class'             => '',
        'tx_themecamino_link'           => '',
        'tx_themecamino_link_label'     => '',
        'tx_themecamino_link_config'    => '',
        'tx_themecamino_link_icon'      => '',
        'tx_themecamino_header_style'   => 0,
        'tx_themecamino_list_elements'  => 0,
        't3ver_oid'                     => 0,
        't3ver_wsid'                    => 0,
        't3ver_state'                   => 0,
        't3ver_stage'                   => 0,
    ];
    $data = array_merge($defaults, $data);
    $cols = implode(', ', array_keys($data));
    $vals = implode(', ', array_fill(0, count($data), '?'));
    $pdo->prepare("INSERT INTO tt_content ($cols) VALUES ($vals)")->execute(array_values($data));
    return (int)$pdo->lastInsertId();
}

function insertListItem(PDO $pdo, int $foreignUid, string $tablename, int $sorting, array $data): int
{
    $now = time();
    $defaults = [
        'pid'              => 0,
        'tstamp'           => $now,
        'crdate'           => $now,
        'deleted'          => 0,
        'hidden'           => 0,
        'sorting_foreign'  => $sorting,
        'sys_language_uid' => 0,
        'l10n_parent'      => 0,
        'l10n_source'      => 0,
        'l10n_diffsource'  => '',
        't3ver_oid'        => 0,
        't3ver_wsid'       => 0,
        't3ver_state'      => 0,
        't3ver_stage'      => 0,
        'uid_foreign'      => $foreignUid,
        'tablename'        => $tablename,
        'fieldname'        => 'tx_themecamino_list_elements',
        'category'         => '',
        'date'             => null,
        'header'           => '',
        'images'           => 0,
        'link'             => '',
        'link_config'      => '',
        'link_icon'        => '',
        'link_label'       => '',
        'text'             => '',
    ];
    $data  = array_merge($defaults, $data);
    $cols  = implode(', ', array_keys($data));
    $vals  = implode(', ', array_fill(0, count($data), '?'));
    $pdo->prepare("INSERT INTO tx_themecamino_list_item ($cols) VALUES ($vals)")->execute(array_values($data));
    return (int)$pdo->lastInsertId();
}

function insertFileRef(PDO $pdo, int $fileUid, int $foreignUid, string $tablename, string $fieldname, int $sorting = 1, string $title = '', string $alt = ''): void
{
    $now = time();
    $pdo->prepare(
        "INSERT INTO sys_file_reference
            (pid, tstamp, crdate, uid_local, uid_foreign, tablenames, fieldname,
             sorting_foreign, title, alternative, description, link, hidden, deleted, crop, autoplay, sys_language_uid)
         VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', '', 0, 0, '', 0, 0)"
    )->execute([$now, $now, $fileUid, $foreignUid, $tablename, $fieldname, $sorting, $title ?: null, $alt ?: null]);
}

// ---------------------------------------------------------------------------
// SCHRITT 1: Unsplash-Bilder herunterladen
// ---------------------------------------------------------------------------

echo "📷 Bilder herunterladen (Unsplash)...\n";

$images = [
    'hero-about'          => ['filename' => 'hero-about.jpg',          'title' => 'Unser Team',          'alt' => 'Landolsi Webdesign Team',      'url' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=1200&h=800&fit=crop&auto=format&q=80'],
    'leistung-webdesign'  => ['filename' => 'leistung-webdesign.jpg',  'title' => 'Webdesign',            'alt' => 'Modernes Webdesign',           'url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&h=800&fit=crop&auto=format&q=80'],
    'leistung-typo3'      => ['filename' => 'leistung-typo3.jpg',      'title' => 'TYPO3 Entwicklung',   'alt' => 'TYPO3 CMS Entwicklung',        'url' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1200&h=800&fit=crop&auto=format&q=80'],
    'leistung-seo'        => ['filename' => 'leistung-seo.jpg',        'title' => 'SEO & Performance',   'alt' => 'Analytics Dashboard',          'url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1200&h=800&fit=crop&auto=format&q=80'],
    'referenz-1'          => ['filename' => 'referenz-1.jpg',          'title' => 'Referenzprojekt 1',   'alt' => 'Modernes Webprojekt',          'url' => 'https://images.unsplash.com/photo-1559136555-9303baea8ebd?w=1200&h=800&fit=crop&auto=format&q=80'],
    'referenz-2'          => ['filename' => 'referenz-2.jpg',          'title' => 'Referenzprojekt 2',   'alt' => 'TYPO3 Projekt',                'url' => 'https://images.unsplash.com/photo-1467232004584-a241de8bcf5d?w=1200&h=800&fit=crop&auto=format&q=80'],
    'referenz-3'          => ['filename' => 'referenz-3.jpg',          'title' => 'Referenzprojekt 3',   'alt' => 'Responsive Website',           'url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=600&h=600&fit=crop&auto=format&q=80'],
];

foreach ($images as $key => $img) {
    $targetPath = $demoDir . '/' . $img['filename'];
    if (file_exists($targetPath) && filesize($targetPath) > 10000) {
        echo "  ✓ {$img['filename']} (bereits vorhanden)\n";
        continue;
    }
    $ctx  = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => true, 'user_agent' => 'Mozilla/5.0 (compatible; Setup)']]);
    $data = @file_get_contents($img['url'], false, $ctx);
    if ($data && strlen($data) > 10000) {
        file_put_contents($targetPath, $data);
        echo '  ✓ ' . $img['filename'] . ' (' . round(strlen($data) / 1024) . " KB)\n";
    } else {
        echo "  ⚠ {$img['filename']} – Download fehlgeschlagen (Skript läuft weiter ohne Bild)\n";
    }
}

// ---------------------------------------------------------------------------
// SCHRITT 2: sys_file-Einträge anlegen
// ---------------------------------------------------------------------------

echo "\n🗄  sys_file-Einträge anlegen...\n";

$fileIds = [];
foreach ($images as $key => $img) {
    $identifier     = '/demo/' . $img['filename'];
    $identifierHash = sha1($identifier);
    $folderHash     = sha1('/demo/');
    $filePath       = $demoDir . '/' . $img['filename'];
    $fileSize       = file_exists($filePath) ? filesize($filePath) : 0;

    $stmt = $pdo->prepare('SELECT uid FROM sys_file WHERE identifier_hash = ? AND storage = 1 LIMIT 1');
    $stmt->execute([$identifierHash]);
    $existingUid = $stmt->fetchColumn();

    if ($existingUid) {
        $fileIds[$key] = (int)$existingUid;
        echo "  ✓ {$img['filename']} bereits in sys_file (uid={$existingUid})\n";
        continue;
    }

    $pdo->prepare(
        "INSERT INTO sys_file
            (pid, tstamp, storage, type, identifier, identifier_hash, folder_hash,
             extension, mime_type, name, size, missing, sha1, creation_date, modification_date)
         VALUES (0, ?, 1, 2, ?, ?, ?, 'jpg', 'image/jpeg', ?, ?, 0, '', ?, ?)"
    )->execute([$now, $identifier, $identifierHash, $folderHash, $img['filename'], $fileSize, $now, $now]);

    $uid           = (int)$pdo->lastInsertId();
    $fileIds[$key] = $uid;
    echo "  ✓ {$img['filename']} → sys_file uid={$uid}\n";

    // sys_file_metadata
    $pdo->prepare(
        "INSERT INTO sys_file_metadata
            (file, pid, tstamp, title, alternative, description)
         VALUES (?, 0, ?, ?, ?, '')"
    )->execute([$uid, $now, $img['title'], $img['alt']]);
}

// ---------------------------------------------------------------------------
// SCHRITT 3: Seiten anlegen
// ---------------------------------------------------------------------------

echo "\n📄 Seiten anlegen...\n";

$pagesConfig = [
    'about'      => ['title' => 'Über uns',   'slug' => '/ueber-uns',   'sorting' => 128],
    'services'   => ['title' => 'Leistungen', 'slug' => '/leistungen',  'sorting' => 256],
    'references' => ['title' => 'Referenzen', 'slug' => '/referenzen',  'sorting' => 384],
    'contact'    => ['title' => 'Kontakt',     'slug' => '/kontakt',     'sorting' => 512],
];

$pageIds = [];
foreach ($pagesConfig as $key => $page) {
    $stmt = $pdo->prepare('SELECT uid FROM pages WHERE slug = ? AND deleted = 0 LIMIT 1');
    $stmt->execute([$page['slug']]);
    $existingUid = $stmt->fetchColumn();

    if ($existingUid) {
        $pageIds[$key] = (int)$existingUid;
        echo "  ✓ '{$page['title']}' bereits vorhanden (uid={$existingUid})\n";
        continue;
    }

    $pdo->prepare(
        "INSERT INTO pages
            (pid, sorting, doktype, title, slug, nav_title, tstamp, crdate,
             sys_language_uid, l10n_parent, backend_layout_next_level,
             hidden, deleted, nav_hide, is_siteroot,
             perms_userid, perms_groupid, perms_user, perms_group, perms_everybody)
         VALUES (1, ?, 1, ?, ?, ?, ?, ?, 0, 0, 'pagets__CaminoContentpage',
                 0, 0, 0, 0, 1, 0, 31, 31, 0)"
    )->execute([$page['sorting'], $page['title'], $page['slug'], $page['title'], $now, $now]);

    $pageIds[$key] = (int)$pdo->lastInsertId();
    echo "  ✓ '{$page['title']}' angelegt (uid={$pageIds[$key]})\n";
}

// ---------------------------------------------------------------------------
// SCHRITT 4: Inhaltselemente anlegen
// ---------------------------------------------------------------------------

echo "\n🧩 Inhaltselemente anlegen...\n";

$contentIds = [];

// ··· HOME-PAGE (pid=1) ···

// Stage colPos=2: Fullscreen Hero
$contentIds['home_hero'] = insertContent($pdo, [
    'pid'                       => 1,
    'sorting'                   => 128,
    'colPos'                    => 2,
    'CType'                     => 'camino_hero_text_only',
    'header'                    => 'Webdesign, der begeistert.',
    'subheader'                 => 'Professionelle TYPO3-Websites für Unternehmen, die online wachsen wollen.',
    'tx_themecamino_link'       => 't3://page?uid=' . $pageIds['contact'],
    'tx_themecamino_link_label' => 'Jetzt Kontakt aufnehmen',
    'tx_themecamino_link_config'=> 'primary',
]);

// Content colPos=0: 3 Textteasers (Leistungsübersicht)
$homeTeasers = [
    ['header' => 'Webdesign',       'bodytext' => '<p>Individuelle, responsive Websites, die Ihre Marke perfekt inszenieren – modern, schnell und barrierefrei gestaltet.</p>',                                           'tx_themecamino_link' => 't3://page?uid=' . $pageIds['services'], 'tx_themecamino_link_label' => 'Mehr erfahren', 'sorting' => 256, 'frame_class' => 'bg-10'],
    ['header' => 'TYPO3 CMS',       'bodytext' => '<p>Leistungsstarkes Content-Management mit TYPO3 14 LTS – skalierbar, sicher und zukunftssicher für Ihren digitalen Auftritt.</p>',                                  'tx_themecamino_link' => 't3://page?uid=' . $pageIds['services'], 'tx_themecamino_link_label' => 'Mehr erfahren', 'sorting' => 384, 'frame_class' => 'bg-80'],
    ['header' => 'SEO & Performance','bodytext' => '<p>Bessere Rankings, schnellere Ladezeiten und mehr organische Reichweite – technisches SEO und Core Web Vitals nachhaltig und messbar umgesetzt.</p>',                'tx_themecamino_link' => 't3://page?uid=' . $pageIds['services'], 'tx_themecamino_link_label' => 'Mehr erfahren', 'sorting' => 512, 'frame_class' => 'bg-10'],
];
foreach ($homeTeasers as $i => $t) {
    $contentIds["home_teaser_$i"] = insertContent($pdo, array_merge($t, ['pid' => 1, 'colPos' => 0, 'CType' => 'camino_textteaser']));
}
echo "  ✓ Home: hero + 3 Textteasers\n";

// ··· ÜBER UNS ···
$aboutPid = $pageIds['about'];

$contentIds['about_hero'] = insertContent($pdo, [
    'pid'        => $aboutPid, 'sorting' => 128, 'colPos' => 2,
    'CType'      => 'camino_hero_text_only',
    'header'     => 'Über uns',
    'subheader'  => 'Leidenschaft für gutes Webdesign seit über 10 Jahren.',
]);

$contentIds['about_text'] = insertContent($pdo, [
    'pid'      => $aboutPid, 'sorting' => 256, 'colPos' => 0,
    'CType'    => 'text',
    'header'   => 'Wir gestalten digitale Erlebnisse',
    'bodytext' => '<p><strong>Landolsi Webdesign</strong> steht für professionelle Webentwicklung mit Herzblut. Als TYPO3-Agentur aus Leidenschaft entwickeln wir maßgeschneiderte Lösungen für mittelständische Unternehmen, Agenturen und gemeinnützige Organisationen.</p><p>Unser Ansatz verbindet technische Exzellenz mit durchdachtem Design – damit Ihre Website nicht nur schön aussieht, sondern auch funktioniert und messbare Ergebnisse liefert.</p><p>Wir setzen auf moderne Technologien wie TYPO3 14 LTS, DDEV für lokale Entwicklung, GitHub Actions für CI/CD und bewährte DevOps-Praktiken für ein stabiles, wartungsarmes Deployment.</p>',
]);

// Hero Small mit Team-Bild
$contentIds['about_team'] = insertContent($pdo, [
    'pid'      => $aboutPid, 'sorting' => 384, 'colPos' => 0,
    'CType'    => 'camino_hero_small',
    'header'   => 'Unser Team',
    'subheader'=> 'Menschen, die Technik und Design verbinden.',
    'image'    => isset($fileIds['hero-about']) ? 1 : 0,
]);

// Textteaser: Werte
$aboutTeasers = [
    ['header' => 'Qualität',      'bodytext' => '<p>Kein Copy-Paste, kein Einheitsbrei. Jedes Projekt wird individuell konzipiert und sorgfältig umgesetzt.</p>', 'frame_class' => 'bg-10', 'sorting' => 512],
    ['header' => 'Transparenz',   'bodytext' => '<p>Offene Kommunikation, ehrliche Zeitplanung und klare Preisgestaltung – von Anfang bis Ende.</p>',              'frame_class' => 'bg-80', 'sorting' => 640],
    ['header' => 'Partnerschaft', 'bodytext' => '<p>Wir denken langfristig. Unser Ziel ist eine nachhaltige Zusammenarbeit, kein einmaliges Projekt.</p>',          'frame_class' => 'bg-10', 'sorting' => 768],
];
foreach ($aboutTeasers as $i => $t) {
    $contentIds["about_teaser_$i"] = insertContent($pdo, array_merge($t, ['pid' => $aboutPid, 'colPos' => 0, 'CType' => 'camino_textteaser']));
}

echo "  ✓ Über uns: hero + text + hero_small + 3 Textteasers\n";

// ··· LEISTUNGEN ···
$servicesPid = $pageIds['services'];

$contentIds['services_hero'] = insertContent($pdo, [
    'pid'      => $servicesPid, 'sorting' => 128, 'colPos' => 2,
    'CType'    => 'camino_hero_text_only',
    'header'   => 'Unsere Leistungen',
    'subheader'=> 'Alles aus einer Hand – von der Konzeption bis zum Launch.',
]);

$serviceTeasers = [
    [
        'key'       => 'services_t0',
        'image_key' => 'leistung-webdesign',
        'header'    => 'Webdesign & UI/UX',
        'subheader' => 'Design',
        'bodytext'  => '<p>Wir entwerfen Websites, die begeistern: nutzerfreundlich, modern und konversionsoptimiert. Jedes Design wird individuell nach Ihren Anforderungen entwickelt – mit Fokus auf Usability, Markenidentität und Performance.</p>',
        'tx_themecamino_link'       => 't3://page?uid=' . $servicesPid,
        'tx_themecamino_link_label' => 'Mehr erfahren',
        'sorting'   => 256,
    ],
    [
        'key'       => 'services_t1',
        'image_key' => 'leistung-typo3',
        'header'    => 'TYPO3 Entwicklung',
        'subheader' => 'CMS',
        'bodytext'  => '<p>TYPO3 14 LTS als Basis für Ihre Website: erweiterbar, wartungsarm und zukunftssicher. Wir bauen das, was Sie brauchen – mit sauberer Composer-Architektur, Site Sets und modernem DevOps-Workflow.</p>',
        'tx_themecamino_link'       => 't3://page?uid=' . $servicesPid,
        'tx_themecamino_link_label' => 'Mehr erfahren',
        'sorting'   => 384,
    ],
    [
        'key'       => 'services_t2',
        'image_key' => 'leistung-seo',
        'header'    => 'SEO & Performance',
        'subheader' => 'Marketing',
        'bodytext'  => '<p>Technisches SEO, Core Web Vitals und strukturierte Inhalte – wir sorgen dafür, dass Ihre Website bei Google gefunden wird und schnell lädt. Lighthouse-Score-Verbesserungen von 40+ Punkten sind unser Standard.</p>',
        'tx_themecamino_link'       => 't3://page?uid=' . $servicesPid,
        'tx_themecamino_link_label' => 'Mehr erfahren',
        'sorting'   => 512,
    ],
];

$imageKeyMap = [];
foreach ($serviceTeasers as $t) {
    $contentIds[$t['key']] = insertContent($pdo, [
        'pid'                       => $servicesPid,
        'sorting'                   => $t['sorting'],
        'colPos'                    => 0,
        'CType'                     => 'camino_textmedia_teaser',
        'header'                    => $t['header'],
        'subheader'                 => $t['subheader'],
        'bodytext'                  => $t['bodytext'],
        'tx_themecamino_link'       => $t['tx_themecamino_link'],
        'tx_themecamino_link_label' => $t['tx_themecamino_link_label'],
        'image'                     => isset($fileIds[$t['image_key']]) ? 1 : 0,
    ]);
    $imageKeyMap[$t['key']] = $t['image_key'];
}
echo "  ✓ Leistungen: hero + 3 Textmedia-Teasers\n";

// ··· REFERENZEN ···
$refPid = $pageIds['references'];

$contentIds['refs_hero'] = insertContent($pdo, [
    'pid'      => $refPid, 'sorting' => 128, 'colPos' => 2,
    'CType'    => 'camino_hero_text_only',
    'header'   => 'Referenzen',
    'subheader'=> 'Projekte, auf die wir stolz sind.',
]);

$contentIds['refs_grid'] = insertContent($pdo, [
    'pid'                          => $refPid, 'sorting' => 256, 'colPos' => 0,
    'CType'                        => 'camino_textmedia_teaser_grid',
    'header'                       => 'Ausgewählte Projekte',
    'subheader'                    => 'Ein Auszug unserer Arbeit',
    'bodytext'                     => '<p>Von der Konzeption über die Entwicklung bis zur Übergabe – unsere Projekte entstehen in enger Zusammenarbeit mit unseren Kunden.</p>',
    'tx_themecamino_list_elements' => 3,
]);
echo "  ✓ Referenzen: hero + Teaser-Grid\n";

// ··· KONTAKT ···
$contactPid = $pageIds['contact'];

$contentIds['contact_hero'] = insertContent($pdo, [
    'pid'      => $contactPid, 'sorting' => 128, 'colPos' => 2,
    'CType'    => 'camino_hero_text_only',
    'header'   => 'Kontakt',
    'subheader'=> 'Sprechen Sie uns an – wir freuen uns auf Ihr Projekt.',
]);

$contentIds['contact_text'] = insertContent($pdo, [
    'pid'      => $contactPid, 'sorting' => 256, 'colPos' => 0,
    'CType'    => 'text',
    'header'   => 'So erreichen Sie uns',
    'bodytext' => '<p><strong>Landolsi Webdesign</strong><br>Professionelle Webentwicklung &amp; TYPO3 Agentur</p><p>📧 <a href="mailto:info@landolsi.de">info@landolsi.de</a><br>🌐 <a href="https://landolsi.de" target="_blank" rel="noopener">landolsi.de</a></p><p>Wir melden uns innerhalb von 24 Stunden bei Ihnen zurück.</p>',
]);

$contentIds['contact_teaser_0'] = insertContent($pdo, [
    'pid'       => $contactPid, 'sorting' => 384, 'colPos' => 0,
    'CType'     => 'camino_textteaser',
    'header'    => 'Projektanfrage',
    'bodytext'  => '<p>Haben Sie ein Projekt im Kopf? Schildern Sie uns Ihre Idee – kostenlos und unverbindlich. Wir erstellen Ihnen ein individuelles Angebot.</p>',
    'frame_class'                   => 'bg-10',
    'tx_themecamino_link'           => 'mailto:info@landolsi.de',
    'tx_themecamino_link_label'     => 'Anfrage senden',
]);

$contentIds['contact_teaser_1'] = insertContent($pdo, [
    'pid'       => $contactPid, 'sorting' => 512, 'colPos' => 0,
    'CType'     => 'camino_textteaser',
    'header'    => 'TYPO3 Support',
    'bodytext'  => '<p>Sie haben bereits eine TYPO3-Installation und benötigen Hilfe oder Weiterentwicklung? Wir übernehmen auch bestehende Projekte zuverlässig.</p>',
    'frame_class'                   => 'bg-80',
    'tx_themecamino_link'           => 'mailto:info@landolsi.de',
    'tx_themecamino_link_label'     => 'Support anfragen',
]);
echo "  ✓ Kontakt: hero + text + 2 Textteasers\n";

// ··· FOOTER (auf Root-Seite uid=1, slideMode vererbt an alle Unterseiten) ···

$contentIds['footer_left'] = insertContent($pdo, [
    'pid' => 1, 'sorting' => 128, 'colPos' => 11,
    'CType' => 'camino_linklist', 'header' => 'Landolsi Webdesign',
    'tx_themecamino_list_elements' => 2,
]);

$contentIds['footer_nav'] = insertContent($pdo, [
    'pid' => 1, 'sorting' => 128, 'colPos' => 12,
    'CType' => 'camino_linklist', 'header' => 'Navigation',
    'tx_themecamino_list_elements' => 5,
]);

$contentIds['footer_services'] = insertContent($pdo, [
    'pid' => 1, 'sorting' => 128, 'colPos' => 13,
    'CType' => 'camino_linklist', 'header' => 'Leistungen',
    'tx_themecamino_list_elements' => 3,
]);

$contentIds['footer_contact'] = insertContent($pdo, [
    'pid' => 1, 'sorting' => 128, 'colPos' => 14,
    'CType' => 'camino_linklist', 'header' => 'Kontakt',
    'tx_themecamino_list_elements' => 3,
]);

$contentIds['footer_meta'] = insertContent($pdo, [
    'pid' => 1, 'sorting' => 128, 'colPos' => 10,
    'CType' => 'camino_linklist', 'header' => '',
    'tx_themecamino_list_elements' => 1,
]);
echo "  ✓ Footer: 5 Spalten angelegt\n";

// ---------------------------------------------------------------------------
// SCHRITT 5: Linklist-Einträge (Footer)
// ---------------------------------------------------------------------------

echo "\n🔗 Linklist-Einträge anlegen...\n";

// Footer-Left: Kurzinfo + Link zur Startseite
insertListItem($pdo, $contentIds['footer_left'], 'tt_content', 1, ['link_label' => 'Zur Startseite', 'link' => 't3://page?uid=1']);
insertListItem($pdo, $contentIds['footer_left'], 'tt_content', 2, ['link_label' => 'Über uns',        'link' => 't3://page?uid=' . $pageIds['about']]);

// Footer-Nav: Seitennavigation
$navLinks = [
    ['link_label' => 'Home',       'link' => 't3://page?uid=1'],
    ['link_label' => 'Über uns',   'link' => 't3://page?uid=' . $pageIds['about']],
    ['link_label' => 'Leistungen', 'link' => 't3://page?uid=' . $pageIds['services']],
    ['link_label' => 'Referenzen', 'link' => 't3://page?uid=' . $pageIds['references']],
    ['link_label' => 'Kontakt',    'link' => 't3://page?uid=' . $pageIds['contact']],
];
foreach ($navLinks as $i => $item) {
    insertListItem($pdo, $contentIds['footer_nav'], 'tt_content', $i + 1, $item);
}

// Footer-Services: Leistungslinks
$serviceLinks = [
    ['link_label' => 'Webdesign & UI/UX',  'link' => 't3://page?uid=' . $pageIds['services']],
    ['link_label' => 'TYPO3 Entwicklung',  'link' => 't3://page?uid=' . $pageIds['services']],
    ['link_label' => 'SEO & Performance',  'link' => 't3://page?uid=' . $pageIds['services']],
];
foreach ($serviceLinks as $i => $item) {
    insertListItem($pdo, $contentIds['footer_services'], 'tt_content', $i + 1, $item);
}

// Footer-Contact: Kontaktdaten
insertListItem($pdo, $contentIds['footer_contact'], 'tt_content', 1, ['link_label' => 'info@landolsi.de',        'link' => 'mailto:info@landolsi.de']);
insertListItem($pdo, $contentIds['footer_contact'], 'tt_content', 2, ['link_label' => 'landolsi.de',              'link' => 'https://landolsi.de']);
insertListItem($pdo, $contentIds['footer_contact'], 'tt_content', 3, ['link_label' => 'Jetzt anfragen',           'link' => 'mailto:info@landolsi.de']);

// Footer-Meta: Copyright
insertListItem($pdo, $contentIds['footer_meta'], 'tt_content', 1, [
    'link_label' => '© ' . date('Y') . ' Landolsi Webdesign',
    'link'       => 'https://landolsi.de',
]);

echo "  ✓ Footer-Linklist-Einträge erstellt\n";

// ---------------------------------------------------------------------------
// SCHRITT 6: Teaser-Grid-Einträge (Referenzen)
// ---------------------------------------------------------------------------

echo "\n🎨 Referenzen-Grid-Einträge anlegen...\n";

$gridItems = [
    [
        'header'     => 'Landolsi.de – Corporate Website',
        'text'       => 'Kompletter TYPO3 14 Relaunch für ein Webdesign-Studio. Entwicklung mit Camino-Theme, optimierter Performance (Lighthouse 95+) und technischer SEO-Grundoptimierung.',
        'category'   => 'Webdesign',
        'link'       => 'https://landolsi.de',
        'link_label' => 'Zum Projekt',
        'image_key'  => 'referenz-1',
    ],
    [
        'header'     => 'CMS-Migration zu TYPO3 14',
        'text'       => 'Migration einer bestehenden Unternehmenswebsite zu TYPO3 14 LTS inkl. vollständiger Datenmigration, Template-Neuentwicklung und Redaktionsschulung.',
        'category'   => 'TYPO3',
        'link'       => 'https://landolsi.de',
        'link_label' => 'Mehr erfahren',
        'image_key'  => 'referenz-2',
    ],
    [
        'header'     => 'SEO & Core Web Vitals',
        'text'       => 'Technische SEO-Analyse und Performance-Optimierung: Lighthouse-Score von 45 auf 94 gesteigert, organischer Traffic +65 % in nur 6 Monaten.',
        'category'   => 'SEO',
        'link'       => 'https://landolsi.de',
        'link_label' => 'Mehr erfahren',
        'image_key'  => 'referenz-3',
    ],
];

$gridItemIds = [];
foreach ($gridItems as $i => $item) {
    $imageKey = $item['image_key'];
    $hasImage = (isset($fileIds[$imageKey]) && file_exists($demoDir . '/' . $images[$imageKey]['filename'])) ? 1 : 0;
    $itemId   = insertListItem($pdo, $contentIds['refs_grid'], 'tt_content', $i + 1, [
        'header'     => $item['header'],
        'text'       => $item['text'],
        'category'   => $item['category'],
        'link'       => $item['link'],
        'link_label' => $item['link_label'],
        'images'     => $hasImage,
    ]);
    $gridItemIds[$imageKey] = $itemId;
    echo "  ✓ Grid-Item: {$item['header']}\n";
}

// ---------------------------------------------------------------------------
// SCHRITT 7: sys_file_reference anlegen
// ---------------------------------------------------------------------------

echo "\n🖼  Datei-Referenzen anlegen...\n";

// Über uns – hero_small: Team-Bild
if (isset($fileIds['hero-about'])) {
    insertFileRef($pdo, $fileIds['hero-about'], $contentIds['about_team'], 'tt_content', 'image', 1, 'Unser Team', 'Landolsi Webdesign Team');
    echo "  ✓ Über uns: Team-Bild verknüpft\n";
}

// Leistungen – textmedia_teasers: je 1 Bild
foreach ($imageKeyMap as $contentKey => $imgKey) {
    if (isset($fileIds[$imgKey])) {
        insertFileRef($pdo, $fileIds[$imgKey], $contentIds[$contentKey], 'tt_content', 'image', 1);
        echo "  ✓ Leistungen '{$contentKey}': Bild verknüpft ({$imgKey})\n";
    }
}

// Referenzen – Grid-Items: je 1 Bild
foreach ($gridItemIds as $imgKey => $itemId) {
    if (isset($fileIds[$imgKey])) {
        insertFileRef($pdo, $fileIds[$imgKey], $itemId, 'tx_themecamino_list_item', 'images', 1);
        echo "  ✓ Referenz-Item uid={$itemId}: Bild verknüpft ({$imgKey})\n";
    }
}

// ---------------------------------------------------------------------------
// ERGEBNIS
// ---------------------------------------------------------------------------

echo "\n" . str_repeat('=', 50) . "\n";
echo "✅ Demo-Inhalte erfolgreich erstellt!\n";
echo str_repeat('=', 50) . "\n\n";

echo "Seiten:\n";
echo "  uid=1         → Home\n";
foreach ($pageIds as $key => $uid) {
    echo "  uid={$uid}     → {$pagesConfig[$key]['title']}\n";
}

echo "\nNächster Schritt:\n";
echo "  ddev exec vendor/bin/typo3 cache:flush\n\n";
echo "Dann im Browser testen:\n";
echo "  https://camino14.ddev.site/\n";
echo "  https://camino14.ddev.site/ueber-uns\n";
echo "  https://camino14.ddev.site/leistungen\n";
echo "  https://camino14.ddev.site/referenzen\n";
echo "  https://camino14.ddev.site/kontakt\n\n";
