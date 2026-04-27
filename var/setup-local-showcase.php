<?php

declare(strict_types=1);

/**
 * Rebuild the local TYPO3 showcase content from scratch.
 *
 * Creates a bilingual DE/EN demo site with a curated homepage carousel,
 * service grids, reference grids and footer content. Local development only.
 *
 * Run:
 *   ddev exec php var/setup-local-showcase.php
 */

$pdo = new PDO('mysql:host=db;dbname=db;charset=utf8mb4', 'db', 'db', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$timestamp = time();
$fileadminPath = '/var/www/html/public/fileadmin';
$demoDir = $fileadminPath . '/demo';

if (!is_dir($demoDir) && !mkdir($demoDir, 0755, true) && !is_dir($demoDir)) {
    throw new RuntimeException('Could not create demo directory: ' . $demoDir);
}

echo "\nRebuild local TYPO3 showcase\n";
echo str_repeat('=', 36) . "\n\n";

function insertRecord(PDO $pdo, string $table, array $data): int
{
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})")
        ->execute(array_values($data));

    return (int)$pdo->lastInsertId();
}

function executeStatement(PDO $pdo, string $sql, array $params = []): void
{
    $pdo->prepare($sql)->execute($params);
}

function downloadImage(string $url, string $targetPath): void
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 45,
            'follow_location' => true,
            'user_agent' => 'Landolsi TYPO3 Showcase Setup',
        ],
    ]);

    $data = @file_get_contents($url, false, $context);
    if ($data === false || strlen($data) < 10000) {
        throw new RuntimeException('Download failed for ' . $url);
    }

    file_put_contents($targetPath, $data);
}

function ensureDemoFile(PDO $pdo, string $demoDir, int $timestamp, array $image): int
{
    $targetPath = $demoDir . '/' . $image['filename'];
    if (!file_exists($targetPath) || filesize($targetPath) < 10000) {
        downloadImage($image['url'], $targetPath);
        echo 'Downloaded ' . $image['filename'] . "\n";
    }

    $identifier = '/demo/' . $image['filename'];
    $identifierHash = sha1($identifier);
    $folderHash = sha1('/demo/');
    $size = filesize($targetPath);

    $stmt = $pdo->prepare('SELECT uid FROM sys_file WHERE storage = 1 AND identifier_hash = ? LIMIT 1');
    $stmt->execute([$identifierHash]);
    $existingUid = $stmt->fetchColumn();

    if ($existingUid) {
        executeStatement(
            $pdo,
            'UPDATE sys_file SET tstamp = ?, size = ?, name = ?, missing = 0, mime_type = ?, extension = ? WHERE uid = ?',
            [$timestamp, $size, $image['filename'], 'image/jpeg', 'jpg', $existingUid]
        );

        $metaStmt = $pdo->prepare('SELECT uid FROM sys_file_metadata WHERE file = ? LIMIT 1');
        $metaStmt->execute([$existingUid]);
        $metaUid = $metaStmt->fetchColumn();
        if ($metaUid) {
            executeStatement(
                $pdo,
                'UPDATE sys_file_metadata SET tstamp = ?, title = ?, alternative = ?, description = ? WHERE uid = ?',
                [$timestamp, $image['title'], $image['alt'], $image['description'], $metaUid]
            );
        } else {
            insertRecord($pdo, 'sys_file_metadata', [
                'file' => (int)$existingUid,
                'pid' => 0,
                'tstamp' => $timestamp,
                'title' => $image['title'],
                'alternative' => $image['alt'],
                'description' => $image['description'],
            ]);
        }

        return (int)$existingUid;
    }

    $fileUid = insertRecord($pdo, 'sys_file', [
        'pid' => 0,
        'tstamp' => $timestamp,
        'storage' => 1,
        'type' => 2,
        'identifier' => $identifier,
        'identifier_hash' => $identifierHash,
        'folder_hash' => $folderHash,
        'extension' => 'jpg',
        'mime_type' => 'image/jpeg',
        'name' => $image['filename'],
        'size' => $size,
        'missing' => 0,
        'sha1' => '',
        'creation_date' => $timestamp,
        'modification_date' => $timestamp,
    ]);

    insertRecord($pdo, 'sys_file_metadata', [
        'file' => $fileUid,
        'pid' => 0,
        'tstamp' => $timestamp,
        'title' => $image['title'],
        'alternative' => $image['alt'],
        'description' => $image['description'],
    ]);

    return $fileUid;
}

function insertPage(PDO $pdo, int $timestamp, array $data): int
{
    $defaults = [
        'pid' => 1,
        'sorting' => 128,
        'doktype' => 1,
        'title' => '',
        'slug' => '',
        'nav_title' => '',
        'tstamp' => $timestamp,
        'crdate' => $timestamp,
        'sys_language_uid' => 0,
        'l10n_parent' => 0,
        'l10n_source' => 0,
        'backend_layout' => '',
        'backend_layout_next_level' => 'pagets__CaminoContentpage',
        'hidden' => 0,
        'deleted' => 0,
        'nav_hide' => 0,
        'is_siteroot' => 0,
        'perms_userid' => 1,
        'perms_groupid' => 0,
        'perms_user' => 31,
        'perms_group' => 31,
        'perms_everybody' => 0,
    ];

    return insertRecord($pdo, 'pages', array_merge($defaults, $data));
}

function insertContent(PDO $pdo, int $timestamp, array $data): int
{
    $defaults = [
        'pid' => 1,
        'tstamp' => $timestamp,
        'crdate' => $timestamp,
        'sorting' => 128,
        'sys_language_uid' => 0,
        'l18n_parent' => 0,
        'l10n_source' => 0,
        'colPos' => 0,
        'hidden' => 0,
        'deleted' => 0,
        'CType' => 'text',
        'header' => '',
        'header_layout' => 0,
        'header_position' => '',
        'subheader' => '',
        'bodytext' => '',
        'header_link' => '',
        'image' => 0,
        'assets' => 0,
        'imagewidth' => 0,
        'imageheight' => 0,
        'imageorient' => 0,
        'imageborder' => 0,
        'image_zoom' => 0,
        'imagecols' => 0,
        'frame_class' => 'default',
        'space_before_class' => '',
        'space_after_class' => '',
        'tx_themecamino_link' => '',
        'tx_themecamino_link_label' => '',
        'tx_themecamino_link_config' => '',
        'tx_themecamino_link_icon' => '',
        'tx_themecamino_header_style' => 0,
        'tx_themecamino_list_elements' => 0,
        't3ver_oid' => 0,
        't3ver_wsid' => 0,
        't3ver_state' => 0,
        't3ver_stage' => 0,
    ];

    return insertRecord($pdo, 'tt_content', array_merge($defaults, $data));
}

function insertListItem(PDO $pdo, int $timestamp, int $foreignUid, string $tableName, int $sorting, array $data): int
{
    $defaults = [
        'pid' => 0,
        'tstamp' => $timestamp,
        'crdate' => $timestamp,
        'deleted' => 0,
        'hidden' => 0,
        'sorting_foreign' => $sorting,
        'sys_language_uid' => 0,
        'l10n_parent' => 0,
        'l10n_source' => 0,
        'l10n_diffsource' => '',
        't3ver_oid' => 0,
        't3ver_wsid' => 0,
        't3ver_state' => 0,
        't3ver_stage' => 0,
        'uid_foreign' => $foreignUid,
        'tablename' => $tableName,
        'fieldname' => 'tx_themecamino_list_elements',
        'category' => '',
        'date' => null,
        'header' => '',
        'images' => 0,
        'link' => '',
        'link_config' => '',
        'link_icon' => '',
        'link_label' => '',
        'text' => '',
    ];

    return insertRecord($pdo, 'tx_themecamino_list_item', array_merge($defaults, $data));
}

function insertFileReference(
    PDO $pdo,
    int $timestamp,
    int $fileUid,
    int $foreignUid,
    string $tableName,
    string $fieldName,
    int $sorting,
    int $languageUid = 0,
    string $title = '',
    string $alt = ''
): void {
    insertRecord($pdo, 'sys_file_reference', [
        'pid' => 0,
        'tstamp' => $timestamp,
        'crdate' => $timestamp,
        'uid_local' => $fileUid,
        'uid_foreign' => $foreignUid,
        'tablenames' => $tableName,
        'fieldname' => $fieldName,
        'sorting_foreign' => $sorting,
        'title' => $title ?: null,
        'alternative' => $alt ?: null,
        'description' => null,
        'link' => '',
        'hidden' => 0,
        'deleted' => 0,
        'crop' => '',
        'autoplay' => 0,
        'sys_language_uid' => $languageUid,
    ]);
}

function resetSiteTree(PDO $pdo): void
{
    $pdo->beginTransaction();

    try {
        executeStatement($pdo, "DELETE FROM sys_file_reference WHERE tablenames IN ('tt_content', 'tx_themecamino_list_item')");
        executeStatement($pdo, 'DELETE FROM tx_themecamino_list_item');
        executeStatement($pdo, 'DELETE FROM tt_content');
        executeStatement($pdo, 'DELETE FROM pages WHERE uid <> 1');
        executeStatement(
            $pdo,
            "UPDATE pages
             SET title = 'Home',
                 nav_title = 'Home',
                 slug = '/',
                 backend_layout = 'pagets__CaminoStartpage',
                 backend_layout_next_level = 'pagets__CaminoContentpage',
                 is_siteroot = 1,
                 hidden = 0,
                 deleted = 0,
                 doktype = 1,
                 sys_language_uid = 0,
                 l10n_parent = 0,
                 l10n_source = 0
             WHERE uid = 1"
        );
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

$images = [
    'hero-home' => [
        'filename' => 'hero-home.jpg',
        'title' => 'Strategieworkshop',
        'alt' => 'Team bei einem TYPO3 Strategie-Workshop',
        'description' => 'Hero image for the homepage',
        'url' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1600&h=1100&fit=crop&auto=format&q=80',
    ],
    'carousel-brand' => [
        'filename' => 'carousel-brand.jpg',
        'title' => 'Brand Experience',
        'alt' => 'Kreatives Meeting vor einem Bildschirm',
        'description' => 'Carousel slide image for brand experience',
        'url' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?w=1600&h=1100&fit=crop&auto=format&q=80',
    ],
    'carousel-build' => [
        'filename' => 'carousel-build.jpg',
        'title' => 'TYPO3 Delivery',
        'alt' => 'Laptop mit Code und TYPO3 Projektarbeit',
        'description' => 'Carousel slide image for TYPO3 delivery',
        'url' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1600&h=1100&fit=crop&auto=format&q=80',
    ],
    'carousel-growth' => [
        'filename' => 'carousel-growth.jpg',
        'title' => 'Growth & Performance',
        'alt' => 'Team betrachtet Performance Zahlen auf einem Display',
        'description' => 'Carousel slide image for performance growth',
        'url' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=1600&h=1100&fit=crop&auto=format&q=80',
    ],
    'hero-about' => [
        'filename' => 'hero-about.jpg',
        'title' => 'Unser Team',
        'alt' => 'Landolsi Webdesign Team',
        'description' => 'Team photo for the about page',
        'url' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=1200&h=800&fit=crop&auto=format&q=80',
    ],
    'leistung-webdesign' => [
        'filename' => 'leistung-webdesign.jpg',
        'title' => 'Webdesign',
        'alt' => 'Modernes Webdesign auf einem Bildschirm',
        'description' => 'Service image for web design',
        'url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&h=800&fit=crop&auto=format&q=80',
    ],
    'leistung-typo3' => [
        'filename' => 'leistung-typo3.jpg',
        'title' => 'TYPO3 Entwicklung',
        'alt' => 'Code Editor für TYPO3 Entwicklung',
        'description' => 'Service image for TYPO3 development',
        'url' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1200&h=800&fit=crop&auto=format&q=80',
    ],
    'leistung-seo' => [
        'filename' => 'leistung-seo.jpg',
        'title' => 'SEO & Performance',
        'alt' => 'Analytics Dashboard für SEO und Performance',
        'description' => 'Service image for SEO and performance',
        'url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1200&h=800&fit=crop&auto=format&q=80',
    ],
    'referenz-1' => [
        'filename' => 'referenz-1.jpg',
        'title' => 'Project Launch',
        'alt' => 'Modernes Website Projekt in heller Agentur',
        'description' => 'Reference image one',
        'url' => 'https://images.unsplash.com/photo-1559136555-9303baea8ebd?w=1200&h=800&fit=crop&auto=format&q=80',
    ],
    'referenz-2' => [
        'filename' => 'referenz-2.jpg',
        'title' => 'Content Workflow',
        'alt' => 'Workspace für redaktionelle TYPO3 Workflows',
        'description' => 'Reference image two',
        'url' => 'https://images.unsplash.com/photo-1467232004584-a241de8bcf5d?w=1200&h=800&fit=crop&auto=format&q=80',
    ],
    'referenz-3' => [
        'filename' => 'referenz-3.jpg',
        'title' => 'Growth Project',
        'alt' => 'Portrait mit Fokus auf digitales Wachstum',
        'description' => 'Reference image three',
        'url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=900&h=900&fit=crop&auto=format&q=80',
    ],
];

echo "Reset site tree and content...\n";
resetSiteTree($pdo);
echo "Reset complete.\n\n";

echo "Ensure Unsplash images and FAL records...\n";
$fileIds = [];
foreach ($images as $key => $image) {
    $fileIds[$key] = ensureDemoFile($pdo, $demoDir, $timestamp, $image);
}
echo "Images ready.\n\n";

$pageIds = [];
$pageIds['about'] = insertPage($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'title' => 'Über uns',
    'nav_title' => 'Über uns',
    'slug' => '/ueber-uns',
]);
$pageIds['services'] = insertPage($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 256,
    'title' => 'Leistungen',
    'nav_title' => 'Leistungen',
    'slug' => '/leistungen',
]);
$pageIds['portfolio'] = insertPage($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 384,
    'title' => 'Projekte',
    'nav_title' => 'Projekte',
    'slug' => '/projekte',
]);
$pageIds['contact'] = insertPage($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 512,
    'title' => 'Kontakt',
    'nav_title' => 'Kontakt',
    'slug' => '/kontakt',
]);
$pageIds['footer'] = insertPage($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 896,
    'doktype' => 254,
    'title' => 'Footer navigation',
    'nav_title' => 'Footer navigation',
    'slug' => '/footer-navigation',
    'nav_hide' => 1,
]);
$pageIds['privacy'] = insertPage($pdo, $timestamp, [
    'pid' => $pageIds['footer'],
    'sorting' => 128,
    'title' => 'Datenschutz',
    'nav_title' => 'Datenschutz',
    'slug' => '/datenschutz',
    'nav_hide' => 1,
]);
$pageIds['imprint'] = insertPage($pdo, $timestamp, [
    'pid' => $pageIds['footer'],
    'sorting' => 256,
    'title' => 'Impressum',
    'nav_title' => 'Impressum',
    'slug' => '/impressum',
    'nav_hide' => 1,
]);

$pageIdsEn = [];
$pageIdsEn['home'] = insertPage($pdo, $timestamp, [
    'pid' => 0,
    'sorting' => 256,
    'title' => 'Home',
    'nav_title' => 'Home',
    'slug' => '/',
    'sys_language_uid' => 1,
    'l10n_parent' => 1,
    'l10n_source' => 1,
    'backend_layout' => 'pagets__CaminoStartpage',
    'backend_layout_next_level' => 'pagets__CaminoContentpage',
]);
$pageIdsEn['about'] = insertPage($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'title' => 'About Us',
    'nav_title' => 'About Us',
    'slug' => '/about-us',
    'sys_language_uid' => 1,
    'l10n_parent' => $pageIds['about'],
    'l10n_source' => $pageIds['about'],
]);
$pageIdsEn['services'] = insertPage($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 256,
    'title' => 'Services',
    'nav_title' => 'Services',
    'slug' => '/services',
    'sys_language_uid' => 1,
    'l10n_parent' => $pageIds['services'],
    'l10n_source' => $pageIds['services'],
]);
$pageIdsEn['portfolio'] = insertPage($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 384,
    'title' => 'Projects',
    'nav_title' => 'Projects',
    'slug' => '/projects',
    'sys_language_uid' => 1,
    'l10n_parent' => $pageIds['portfolio'],
    'l10n_source' => $pageIds['portfolio'],
]);
$pageIdsEn['contact'] = insertPage($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 512,
    'title' => 'Contact',
    'nav_title' => 'Contact',
    'slug' => '/contact',
    'sys_language_uid' => 1,
    'l10n_parent' => $pageIds['contact'],
    'l10n_source' => $pageIds['contact'],
]);
$pageIdsEn['privacy'] = insertPage($pdo, $timestamp, [
    'pid' => $pageIds['footer'],
    'sorting' => 128,
    'title' => 'Privacy',
    'nav_title' => 'Privacy',
    'slug' => '/privacy',
    'nav_hide' => 1,
    'sys_language_uid' => 1,
    'l10n_parent' => $pageIds['privacy'],
    'l10n_source' => $pageIds['privacy'],
]);
$pageIdsEn['imprint'] = insertPage($pdo, $timestamp, [
    'pid' => $pageIds['footer'],
    'sorting' => 256,
    'title' => 'Imprint',
    'nav_title' => 'Imprint',
    'slug' => '/imprint',
    'nav_hide' => 1,
    'sys_language_uid' => 1,
    'l10n_parent' => $pageIds['imprint'],
    'l10n_source' => $pageIds['imprint'],
]);

echo "Create bilingual content...\n";

$homeHero = insertContent($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'colPos' => 2,
    'CType' => 'camino_hero',
    'header' => 'Digitale Auftritte mit Klarheit, Tempo und TYPO3-Kompetenz.',
    'subheader' => 'Landolsi Webdesign entwickelt hochwertige TYPO3-Websites mit Camino, sauberem Setup und einem Redaktionsworkflow, der sich leicht anfuehlt.',
    'tx_themecamino_link' => 't3://page?uid=' . $pageIds['contact'],
    'tx_themecamino_link_label' => 'Projekt besprechen',
    'tx_themecamino_link_config' => 'primary',
    'image' => 1,
]);
insertFileReference($pdo, $timestamp, $fileIds['hero-home'], $homeHero, 'tt_content', 'image', 1, 0, 'Startseiten-Hero', 'Strategieworkshop');

$homeHeroEn = insertContent($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'sys_language_uid' => 1,
    'l18n_parent' => $homeHero,
    'l10n_source' => $homeHero,
    'colPos' => 2,
    'CType' => 'camino_hero',
    'header' => 'Digital experiences with clarity, speed and solid TYPO3 execution.',
    'subheader' => 'Landolsi Webdesign builds refined TYPO3 websites with Camino, clean project setup and a content workflow that feels easy.',
    'tx_themecamino_link' => 't3://page?uid=' . $pageIds['contact'],
    'tx_themecamino_link_label' => 'Discuss your project',
    'tx_themecamino_link_config' => 'primary',
    'image' => 1,
]);
insertFileReference($pdo, $timestamp, $fileIds['hero-home'], $homeHeroEn, 'tt_content', 'image', 1, 1, 'Homepage hero', 'Strategy workshop');

$homeTeasersDe = [
    [
        'header' => 'Strategie & Konzeption',
        'bodytext' => '<p>Wir bringen Positionierung, Seitenstruktur und Nutzerführung früh zusammen, damit Design und Technik später sauber ineinandergreifen.</p>',
        'frame_class' => 'bg-10',
        'sorting' => 256,
    ],
    [
        'header' => 'TYPO3 & Composer',
        'bodytext' => '<p>TYPO3 14, Composer, DDEV und reproduzierbare Deployments sorgen für ein Projektsetup, das nicht nur heute, sondern auch in einem Jahr noch Freude macht.</p>',
        'frame_class' => 'bg-80',
        'sorting' => 384,
    ],
    [
        'header' => 'SEO & Betrieb',
        'bodytext' => '<p>Von Core Web Vitals bis zur Produktionsübergabe bleibt der Fokus auf Sichtbarkeit, Performance und einer wartbaren Architektur.</p>',
        'frame_class' => 'bg-10',
        'sorting' => 512,
    ],
];

$homeTeasersEn = [
    [
        'header' => 'Strategy & concept',
        'bodytext' => '<p>We align positioning, page structure and user journeys early so design and implementation work together from day one.</p>',
        'frame_class' => 'bg-10',
        'sorting' => 256,
    ],
    [
        'header' => 'TYPO3 & Composer',
        'bodytext' => '<p>TYPO3 14, Composer, DDEV and reproducible deployments create a project setup that still feels clean and maintainable later on.</p>',
        'frame_class' => 'bg-80',
        'sorting' => 384,
    ],
    [
        'header' => 'SEO & operations',
        'bodytext' => '<p>From Core Web Vitals to production handover, we keep the focus on visibility, performance and long-term maintainability.</p>',
        'frame_class' => 'bg-10',
        'sorting' => 512,
    ],
];

foreach ($homeTeasersDe as $index => $teaser) {
    $deUid = insertContent($pdo, $timestamp, array_merge($teaser, [
        'pid' => 1,
        'colPos' => 0,
        'CType' => 'camino_textteaser',
        'tx_themecamino_link' => 't3://page?uid=' . $pageIds['services'],
        'tx_themecamino_link_label' => 'Mehr erfahren',
    ]));
    insertContent($pdo, $timestamp, array_merge($homeTeasersEn[$index], [
        'pid' => 1,
        'sys_language_uid' => 1,
        'l18n_parent' => $deUid,
        'l10n_source' => $deUid,
        'colPos' => 0,
        'CType' => 'camino_textteaser',
        'tx_themecamino_link' => 't3://page?uid=' . $pageIds['services'],
        'tx_themecamino_link_label' => 'Learn more',
    ]));
}

$aboutHero = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['about'],
    'sorting' => 128,
    'colPos' => 2,
    'CType' => 'camino_hero_small',
    'header' => 'Über uns',
    'subheader' => 'Strategie, Gestaltung und TYPO3-Umsetzung aus einer Hand.',
    'image' => 1,
]);
insertFileReference($pdo, $timestamp, $fileIds['hero-about'], $aboutHero, 'tt_content', 'image', 1, 0, 'Unser Team', 'Landolsi Team');

$aboutHeroEn = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['about'],
    'sorting' => 128,
    'sys_language_uid' => 1,
    'l18n_parent' => $aboutHero,
    'l10n_source' => $aboutHero,
    'colPos' => 2,
    'CType' => 'camino_hero_small',
    'header' => 'About Us',
    'subheader' => 'Strategy, design and TYPO3 delivery in one focused workflow.',
    'image' => 1,
]);
insertFileReference($pdo, $timestamp, $fileIds['hero-about'], $aboutHeroEn, 'tt_content', 'image', 1, 1, 'Our team', 'Landolsi team');

$aboutText = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['about'],
    'sorting' => 256,
    'colPos' => 0,
    'CType' => 'text',
    'header' => 'Digitale Projekte mit Substanz',
    'bodytext' => '<p><strong>Landolsi Webdesign</strong> steht für Websites, die nicht nur gut aussehen, sondern intern genauso gut funktionieren. Wir denken Design, Content, TYPO3-Setup und Deployment als zusammenhängenden Ablauf.</p><p>Gerade mit TYPO3 14 und Camino entstehen dadurch schnelle, wartbare Projekte, die Redaktionen Sicherheit geben und Unternehmen einen starken digitalen Auftritt liefern.</p>',
]);

insertContent($pdo, $timestamp, [
    'pid' => $pageIds['about'],
    'sorting' => 256,
    'sys_language_uid' => 1,
    'l18n_parent' => $aboutText,
    'l10n_source' => $aboutText,
    'colPos' => 0,
    'CType' => 'text',
    'header' => 'Digital projects with substance',
    'bodytext' => '<p><strong>Landolsi Webdesign</strong> creates websites that feel strong externally and stay clear internally. We connect design, content structure, TYPO3 setup and deployment into one coherent workflow.</p><p>With TYPO3 14 and Camino this leads to maintainable projects that editors trust and businesses can confidently grow on top of.</p>',
]);

$aboutTeasersDe = [
    ['header' => 'Saubere Prozesse', 'bodytext' => '<p>Von der lokalen Entwicklung bis zum Launch bleibt jeder Schritt nachvollziehbar, versioniert und technisch belastbar.</p>', 'frame_class' => 'bg-10', 'sorting' => 384],
    ['header' => 'Design mit Haltung', 'bodytext' => '<p>Marke, Typografie, Bildwelt und Interaktion werden so kombiniert, dass die Website sofort professionell wirkt.</p>', 'frame_class' => 'bg-80', 'sorting' => 512],
    ['header' => 'Langfristige Betreuung', 'bodytext' => '<p>Nach dem Go-live endet das Projekt nicht - wir begleiten Weiterentwicklung, Inhalte und Performance mit Blick aufs Ganze.</p>', 'frame_class' => 'bg-10', 'sorting' => 640],
];
$aboutTeasersEn = [
    ['header' => 'Clean processes', 'bodytext' => '<p>From local development to launch, every step stays transparent, versioned and technically reliable.</p>', 'frame_class' => 'bg-10', 'sorting' => 384],
    ['header' => 'Design with intent', 'bodytext' => '<p>Brand, typography, imagery and interaction are shaped together so the site feels polished immediately.</p>', 'frame_class' => 'bg-80', 'sorting' => 512],
    ['header' => 'Long-term support', 'bodytext' => '<p>The project does not stop at go-live - we stay involved in growth, content workflows and performance over time.</p>', 'frame_class' => 'bg-10', 'sorting' => 640],
];

foreach ($aboutTeasersDe as $index => $teaser) {
    $deUid = insertContent($pdo, $timestamp, array_merge($teaser, [
        'pid' => $pageIds['about'],
        'colPos' => 0,
        'CType' => 'camino_textteaser',
    ]));
    insertContent($pdo, $timestamp, array_merge($aboutTeasersEn[$index], [
        'pid' => $pageIds['about'],
        'sys_language_uid' => 1,
        'l18n_parent' => $deUid,
        'l10n_source' => $deUid,
        'colPos' => 0,
        'CType' => 'camino_textteaser',
    ]));
}

$servicesHero = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['services'],
    'sorting' => 128,
    'colPos' => 2,
    'CType' => 'camino_hero_text_only',
    'header' => 'Leistungen für anspruchsvolle TYPO3-Projekte',
    'subheader' => 'Konzeption, Design, Entwicklung und Optimierung in einem belastbaren Setup.',
]);
$servicesHeroEn = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['services'],
    'sorting' => 128,
    'sys_language_uid' => 1,
    'l18n_parent' => $servicesHero,
    'l10n_source' => $servicesHero,
    'colPos' => 2,
    'CType' => 'camino_hero_text_only',
    'header' => 'Services for ambitious TYPO3 projects',
    'subheader' => 'Strategy, design, implementation and optimisation in one reliable workflow.',
]);

$servicesIntro = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['services'],
    'sorting' => 256,
    'colPos' => 0,
    'CType' => 'text',
    'header' => 'Was wir für digitale Marken aufsetzen',
    'bodytext' => '<p>Wir arbeiten an Websites nicht in isolierten Disziplinen, sondern entlang eines sauberen Gesamtprozesses: Strategie, Designsystem, TYPO3-Umsetzung und Performance greifen direkt ineinander.</p>',
]);
$servicesIntroEn = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['services'],
    'sorting' => 256,
    'sys_language_uid' => 1,
    'l18n_parent' => $servicesIntro,
    'l10n_source' => $servicesIntro,
    'colPos' => 0,
    'CType' => 'text',
    'header' => 'What we build for digital brands',
    'bodytext' => '<p>We do not treat websites as isolated disciplines. Strategy, design systems, TYPO3 implementation and performance are developed as one connected delivery flow.</p>',
]);

$servicesGrid = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['services'],
    'sorting' => 384,
    'colPos' => 0,
    'CType' => 'camino_textmedia_teaser_grid',
    'header' => 'Drei Schwerpunkte, ein roter Faden',
    'subheader' => 'Gestaltung, CMS und Sichtbarkeit',
    'bodytext' => '<p>Jeder Schwerpunkt zahlt auf dieselbe Erfahrung ein: eine Website, die professionell wirkt, intern leicht pflegbar bleibt und langfristig gefunden wird.</p>',
    'tx_themecamino_list_elements' => 3,
]);
$servicesGridEn = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['services'],
    'sorting' => 384,
    'sys_language_uid' => 1,
    'l18n_parent' => $servicesGrid,
    'l10n_source' => $servicesGrid,
    'colPos' => 0,
    'CType' => 'camino_textmedia_teaser_grid',
    'header' => 'Three focus areas, one connected workflow',
    'subheader' => 'Design, CMS and visibility',
    'bodytext' => '<p>Each focus area supports the same outcome: a website that feels premium, stays easy to edit internally and performs sustainably.</p>',
    'tx_themecamino_list_elements' => 3,
]);

$serviceItemsDe = [
    [
        'image' => 'leistung-webdesign',
        'category' => 'Design',
        'header' => 'Webdesign & UX',
        'text' => 'Individuelle Interfaces, klare Seitendramaturgie und starke Einstiege für Unternehmen, die digital professionell wirken wollen.',
        'link_label' => 'Zu Webdesign',
        'link' => 'https://landolsi.de/leistungen/webdesign',
    ],
    [
        'image' => 'leistung-typo3',
        'category' => 'CMS',
        'header' => 'TYPO3 Entwicklung',
        'text' => 'Composer-basierte TYPO3-Projekte mit Camino, Site Sets und einer Architektur, die Redaktionen und Deployments gleichermaßen unterstützt.',
        'link_label' => 'Zu TYPO3',
        'link' => 'https://landolsi.de/leistungen/typo3',
    ],
    [
        'image' => 'leistung-seo',
        'category' => 'Performance',
        'header' => 'SEO & Betrieb',
        'text' => 'Technische SEO, Core Web Vitals und sauberer Betrieb sorgen dafür, dass gute Inhalte langfristig sichtbar und messbar werden.',
        'link_label' => 'Zu SEO',
        'link' => 'https://landolsi.de/leistungen/seo',
    ],
];

$serviceItemsEn = [
    [
        'image' => 'leistung-webdesign',
        'category' => 'Design',
        'header' => 'Web design & UX',
        'text' => 'Tailored interfaces, clear storytelling and strong first impressions for businesses that want to look premium online.',
        'link_label' => 'Explore web design',
        'link' => 'https://landolsi.de/leistungen/webdesign',
    ],
    [
        'image' => 'leistung-typo3',
        'category' => 'CMS',
        'header' => 'TYPO3 delivery',
        'text' => 'Composer-based TYPO3 projects with Camino, Site Sets and an architecture that supports editors and deployments equally well.',
        'link_label' => 'Explore TYPO3',
        'link' => 'https://landolsi.de/leistungen/typo3',
    ],
    [
        'image' => 'leistung-seo',
        'category' => 'Performance',
        'header' => 'SEO & operations',
        'text' => 'Technical SEO, Core Web Vitals and disciplined operations help strong content stay visible and measurable over time.',
        'link_label' => 'Explore SEO',
        'link' => 'https://landolsi.de/leistungen/seo',
    ],
];

foreach ($serviceItemsDe as $index => $item) {
    $sorting = ($index + 1) * 128;
    $deUid = insertListItem($pdo, $timestamp, $servicesGrid, 'tt_content', $sorting, [
        'category' => $item['category'],
        'header' => $item['header'],
        'images' => 1,
        'link' => $item['link'],
        'link_label' => $item['link_label'],
        'text' => $item['text'],
    ]);
    insertFileReference($pdo, $timestamp, $fileIds[$item['image']], $deUid, 'tx_themecamino_list_item', 'images', 1, 0, $item['header'], $item['header']);

    $enUid = insertListItem($pdo, $timestamp, $servicesGridEn, 'tt_content', $sorting, [
        'sys_language_uid' => 1,
        'l10n_parent' => $deUid,
        'l10n_source' => $deUid,
        'category' => $serviceItemsEn[$index]['category'],
        'header' => $serviceItemsEn[$index]['header'],
        'images' => 1,
        'link' => $serviceItemsEn[$index]['link'],
        'link_label' => $serviceItemsEn[$index]['link_label'],
        'text' => $serviceItemsEn[$index]['text'],
    ]);
    insertFileReference($pdo, $timestamp, $fileIds[$serviceItemsEn[$index]['image']], $enUid, 'tx_themecamino_list_item', 'images', 1, 1, $serviceItemsEn[$index]['header'], $serviceItemsEn[$index]['header']);
}

$portfolioHero = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['portfolio'],
    'sorting' => 128,
    'colPos' => 2,
    'CType' => 'camino_hero_text_only',
    'header' => 'Ausgewählte Projekte',
    'subheader' => 'Beispiele für Layout, Content-Führung und TYPO3-Umsetzung.',
]);
$portfolioHeroEn = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['portfolio'],
    'sorting' => 128,
    'sys_language_uid' => 1,
    'l18n_parent' => $portfolioHero,
    'l10n_source' => $portfolioHero,
    'colPos' => 2,
    'CType' => 'camino_hero_text_only',
    'header' => 'Selected projects',
    'subheader' => 'Examples of layout quality, content flow and TYPO3 delivery.',
]);

$portfolioGrid = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['portfolio'],
    'sorting' => 256,
    'colPos' => 0,
    'CType' => 'camino_textmedia_teaser_grid',
    'header' => 'Drei typische Projektmuster',
    'subheader' => 'Vom Relaunch bis zur Content-Plattform',
    'bodytext' => '<p>Die folgenden Beispiele zeigen, wie Design, TYPO3-Struktur und inhaltliche Priorisierung zusammenspielen können.</p>',
    'tx_themecamino_list_elements' => 3,
]);
$portfolioGridEn = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['portfolio'],
    'sorting' => 256,
    'sys_language_uid' => 1,
    'l18n_parent' => $portfolioGrid,
    'l10n_source' => $portfolioGrid,
    'colPos' => 0,
    'CType' => 'camino_textmedia_teaser_grid',
    'header' => 'Three typical project patterns',
    'subheader' => 'From relaunch to content platform',
    'bodytext' => '<p>The examples below show how design quality, TYPO3 structure and content priorities can reinforce each other.</p>',
    'tx_themecamino_list_elements' => 3,
]);

$portfolioItemsDe = [
    [
        'image' => 'referenz-1',
        'category' => 'Relaunch',
        'header' => 'Corporate Relaunch',
        'text' => 'Ein neuer digitaler Auftritt mit klarer Seitenarchitektur, stärkerer Bildwelt und einem fokussierten Conversion-Einstieg.',
        'link_label' => 'Projekt anfragen',
        'link' => 't3://page?uid=' . $pageIds['contact'],
    ],
    [
        'image' => 'referenz-2',
        'category' => 'Content',
        'header' => 'Redaktionsplattform',
        'text' => 'TYPO3 so aufgesetzt, dass mehrere Teams Inhalte sicher pflegen, erweitern und strukturiert ausspielen können.',
        'link_label' => 'Mehr erfahren',
        'link' => 't3://page?uid=' . $pageIds['about'],
    ],
    [
        'image' => 'referenz-3',
        'category' => 'Wachstum',
        'header' => 'Performance-Fokus',
        'text' => 'Ein Projekt mit Fokus auf Ladezeit, SEO-Hygiene und einer Navigation, die Nutzer schneller zur relevanten Information bringt.',
        'link_label' => 'Kontakt',
        'link' => 't3://page?uid=' . $pageIds['contact'],
    ],
];

$portfolioItemsEn = [
    [
        'image' => 'referenz-1',
        'category' => 'Relaunch',
        'header' => 'Corporate relaunch',
        'text' => 'A refreshed digital presence with clearer information architecture, stronger imagery and a more focused conversion entry point.',
        'link_label' => 'Start a project',
        'link' => 't3://page?uid=' . $pageIds['contact'],
    ],
    [
        'image' => 'referenz-2',
        'category' => 'Content',
        'header' => 'Editorial platform',
        'text' => 'TYPO3 structured so multiple teams can maintain, expand and publish content with confidence and consistency.',
        'link_label' => 'Learn more',
        'link' => 't3://page?uid=' . $pageIds['about'],
    ],
    [
        'image' => 'referenz-3',
        'category' => 'Growth',
        'header' => 'Performance focus',
        'text' => 'A project shaped around loading speed, SEO hygiene and navigation that gets users to the right information faster.',
        'link_label' => 'Contact us',
        'link' => 't3://page?uid=' . $pageIds['contact'],
    ],
];

foreach ($portfolioItemsDe as $index => $item) {
    $sorting = ($index + 1) * 128;
    $deUid = insertListItem($pdo, $timestamp, $portfolioGrid, 'tt_content', $sorting, [
        'category' => $item['category'],
        'header' => $item['header'],
        'images' => 1,
        'link' => $item['link'],
        'link_label' => $item['link_label'],
        'text' => $item['text'],
    ]);
    insertFileReference($pdo, $timestamp, $fileIds[$item['image']], $deUid, 'tx_themecamino_list_item', 'images', 1, 0, $item['header'], $item['header']);

    $enUid = insertListItem($pdo, $timestamp, $portfolioGridEn, 'tt_content', $sorting, [
        'sys_language_uid' => 1,
        'l10n_parent' => $deUid,
        'l10n_source' => $deUid,
        'category' => $portfolioItemsEn[$index]['category'],
        'header' => $portfolioItemsEn[$index]['header'],
        'images' => 1,
        'link' => $portfolioItemsEn[$index]['link'],
        'link_label' => $portfolioItemsEn[$index]['link_label'],
        'text' => $portfolioItemsEn[$index]['text'],
    ]);
    insertFileReference($pdo, $timestamp, $fileIds[$portfolioItemsEn[$index]['image']], $enUid, 'tx_themecamino_list_item', 'images', 1, 1, $portfolioItemsEn[$index]['header'], $portfolioItemsEn[$index]['header']);
}

$contactHero = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['contact'],
    'sorting' => 128,
    'colPos' => 2,
    'CType' => 'camino_hero_text_only',
    'header' => 'Lassen Sie uns über Ihr nächstes TYPO3-Projekt sprechen',
    'subheader' => 'Klar, direkt und mit einem Blick für Strategie, Design und technische Umsetzung.',
]);
$contactHeroEn = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['contact'],
    'sorting' => 128,
    'sys_language_uid' => 1,
    'l18n_parent' => $contactHero,
    'l10n_source' => $contactHero,
    'colPos' => 2,
    'CType' => 'camino_hero_text_only',
    'header' => 'Let us talk about your next TYPO3 project',
    'subheader' => 'Clear, direct and shaped by strategy, design and reliable implementation.',
]);

$contactText = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['contact'],
    'sorting' => 256,
    'colPos' => 0,
    'CType' => 'text',
    'header' => 'So erreichen Sie uns',
    'bodytext' => '<p><strong>Landolsi Webdesign</strong><br><a href="mailto:info@landolsi.de">info@landolsi.de</a><br><a href="https://landolsi.de" target="_blank" rel="noopener">landolsi.de</a></p><p>Sie haben bereits ein Lastenheft, erste Inhalte oder nur eine grobe Idee? Beides ist ein guter Startpunkt.</p>',
]);
$contactTextEn = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['contact'],
    'sorting' => 256,
    'sys_language_uid' => 1,
    'l18n_parent' => $contactText,
    'l10n_source' => $contactText,
    'colPos' => 0,
    'CType' => 'text',
    'header' => 'How to reach us',
    'bodytext' => '<p><strong>Landolsi Webdesign</strong><br><a href="mailto:info@landolsi.de">info@landolsi.de</a><br><a href="https://landolsi.de" target="_blank" rel="noopener">landolsi.de</a></p><p>Whether you already have a brief, initial content or only a rough idea - both are a perfectly good starting point.</p>',
]);

$contactTeasersDe = [
    ['header' => 'Projektstart', 'bodytext' => '<p>Wir strukturieren Ziele, Umfang und nächste Schritte so, dass aus der Idee schnell ein belastbares Projekt wird.</p>', 'sorting' => 384, 'frame_class' => 'bg-10', 'label' => 'Anfrage senden', 'link' => 'mailto:info@landolsi.de'],
    ['header' => 'Weiterentwicklung', 'bodytext' => '<p>Bestehende TYPO3-Projekte übernehmen wir ebenfalls - von Redaktionsoptimierung bis Release-Vorbereitung.</p>', 'sorting' => 512, 'frame_class' => 'bg-80', 'label' => 'Support anfragen', 'link' => 'mailto:info@landolsi.de'],
];
$contactTeasersEn = [
    ['header' => 'Project kick-off', 'bodytext' => '<p>We help structure scope, goals and next steps so an initial idea quickly becomes a reliable project foundation.</p>', 'sorting' => 384, 'frame_class' => 'bg-10', 'label' => 'Send inquiry', 'link' => 'mailto:info@landolsi.de'],
    ['header' => 'Further development', 'bodytext' => '<p>We also take over existing TYPO3 projects - from editorial improvements to release preparation and rollout support.</p>', 'sorting' => 512, 'frame_class' => 'bg-80', 'label' => 'Request support', 'link' => 'mailto:info@landolsi.de'],
];

foreach ($contactTeasersDe as $index => $teaser) {
    $deUid = insertContent($pdo, $timestamp, [
        'pid' => $pageIds['contact'],
        'sorting' => $teaser['sorting'],
        'colPos' => 0,
        'CType' => 'camino_textteaser',
        'header' => $teaser['header'],
        'bodytext' => $teaser['bodytext'],
        'frame_class' => $teaser['frame_class'],
        'tx_themecamino_link' => $teaser['link'],
        'tx_themecamino_link_label' => $teaser['label'],
    ]);
    insertContent($pdo, $timestamp, [
        'pid' => $pageIds['contact'],
        'sorting' => $contactTeasersEn[$index]['sorting'],
        'sys_language_uid' => 1,
        'l18n_parent' => $deUid,
        'l10n_source' => $deUid,
        'colPos' => 0,
        'CType' => 'camino_textteaser',
        'header' => $contactTeasersEn[$index]['header'],
        'bodytext' => $contactTeasersEn[$index]['bodytext'],
        'frame_class' => $contactTeasersEn[$index]['frame_class'],
        'tx_themecamino_link' => $contactTeasersEn[$index]['link'],
        'tx_themecamino_link_label' => $contactTeasersEn[$index]['label'],
    ]);
}

$privacyText = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['privacy'],
    'sorting' => 128,
    'colPos' => 0,
    'CType' => 'text',
    'header' => 'Datenschutz',
    'bodytext' => '<p>Dies ist eine lokale Demo-Seite. Für ein reales Projekt müssen Datenschutzhinweise, Tracking-Informationen und Auftragsverarbeitung verbindlich mit den tatsächlichen Prozessen abgestimmt werden.</p>',
]);
insertContent($pdo, $timestamp, [
    'pid' => $pageIds['privacy'],
    'sorting' => 128,
    'sys_language_uid' => 1,
    'l18n_parent' => $privacyText,
    'l10n_source' => $privacyText,
    'colPos' => 0,
    'CType' => 'text',
    'header' => 'Privacy',
    'bodytext' => '<p>This is a local demo page. For a real project, privacy information, tracking details and data processing notes must be aligned with the actual live processes.</p>',
]);

$imprintText = insertContent($pdo, $timestamp, [
    'pid' => $pageIds['imprint'],
    'sorting' => 128,
    'colPos' => 0,
    'CType' => 'text',
    'header' => 'Impressum',
    'bodytext' => '<p>Dies ist eine lokale Demo-Seite für das TYPO3-14-Camino-Projekt. Vor einem echten Launch müssen alle rechtlichen Angaben für Betreiber, Kontakt und Verantwortlichkeit verbindlich gepflegt werden.</p>',
]);
insertContent($pdo, $timestamp, [
    'pid' => $pageIds['imprint'],
    'sorting' => 128,
    'sys_language_uid' => 1,
    'l18n_parent' => $imprintText,
    'l10n_source' => $imprintText,
    'colPos' => 0,
    'CType' => 'text',
    'header' => 'Imprint',
    'bodytext' => '<p>This is a local demo page for the TYPO3 14 Camino project. Before a real launch, all legal operator, contact and responsibility details need to be maintained properly.</p>',
]);

$footerBrand = insertContent($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'colPos' => 11,
    'CType' => 'camino_linklist',
    'header' => 'Landolsi Webdesign',
    'tx_themecamino_list_elements' => 2,
]);
$footerBrandEn = insertContent($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'sys_language_uid' => 1,
    'l18n_parent' => $footerBrand,
    'l10n_source' => $footerBrand,
    'colPos' => 11,
    'CType' => 'camino_linklist',
    'header' => 'Landolsi Webdesign',
    'tx_themecamino_list_elements' => 2,
]);

$footerNavigation = insertContent($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'colPos' => 12,
    'CType' => 'camino_linklist',
    'header' => 'Navigation',
    'tx_themecamino_list_elements' => 4,
]);
$footerNavigationEn = insertContent($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'sys_language_uid' => 1,
    'l18n_parent' => $footerNavigation,
    'l10n_source' => $footerNavigation,
    'colPos' => 12,
    'CType' => 'camino_linklist',
    'header' => 'Navigation',
    'tx_themecamino_list_elements' => 4,
]);

$footerServices = insertContent($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'colPos' => 13,
    'CType' => 'camino_linklist',
    'header' => 'Leistungen',
    'tx_themecamino_list_elements' => 3,
]);
$footerServicesEn = insertContent($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'sys_language_uid' => 1,
    'l18n_parent' => $footerServices,
    'l10n_source' => $footerServices,
    'colPos' => 13,
    'CType' => 'camino_linklist',
    'header' => 'Services',
    'tx_themecamino_list_elements' => 3,
]);

$footerContact = insertContent($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'colPos' => 14,
    'CType' => 'camino_linklist',
    'header' => 'Kontakt',
    'tx_themecamino_list_elements' => 3,
]);
$footerContactEn = insertContent($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'sys_language_uid' => 1,
    'l18n_parent' => $footerContact,
    'l10n_source' => $footerContact,
    'colPos' => 14,
    'CType' => 'camino_linklist',
    'header' => 'Contact',
    'tx_themecamino_list_elements' => 3,
]);

$footerMeta = insertContent($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'colPos' => 10,
    'CType' => 'camino_linklist',
    'header' => '',
    'tx_themecamino_list_elements' => 3,
]);
$footerMetaEn = insertContent($pdo, $timestamp, [
    'pid' => 1,
    'sorting' => 128,
    'sys_language_uid' => 1,
    'l18n_parent' => $footerMeta,
    'l10n_source' => $footerMeta,
    'colPos' => 10,
    'CType' => 'camino_linklist',
    'header' => '',
    'tx_themecamino_list_elements' => 3,
]);

$footerBrandItemsDe = [
    ['header' => 'TYPO3 14 + Camino', 'text' => 'Lokales Showcase-Projekt mit sauberem Setup, gutem Redaktionsflow und Fokus auf Performance.'],
    ['header' => 'Projekt besprechen', 'text' => 'Wenn Sie eine professionelle TYPO3-Website planen, sprechen wir gern über Strategie und Umsetzung.', 'link' => 'https://landolsi.de/kontakt', 'link_label' => 'Jetzt anfragen'],
];
$footerBrandItemsEn = [
    ['header' => 'TYPO3 14 + Camino', 'text' => 'Local showcase project with clean setup, strong editorial flow and performance in focus.'],
    ['header' => 'Discuss your project', 'text' => 'If you are planning a professional TYPO3 website, we are happy to talk strategy, scope and implementation.', 'link' => 'https://landolsi.de/kontakt', 'link_label' => 'Start the conversation'],
];

foreach ($footerBrandItemsDe as $index => $item) {
    $sorting = ($index + 1) * 128;
    $deUid = insertListItem($pdo, $timestamp, $footerBrand, 'tt_content', $sorting, $item);
    insertListItem($pdo, $timestamp, $footerBrandEn, 'tt_content', $sorting, array_merge($footerBrandItemsEn[$index], [
        'sys_language_uid' => 1,
        'l10n_parent' => $deUid,
        'l10n_source' => $deUid,
    ]));
}

$navigationItemsDe = [
    ['link_label' => 'Über uns', 'link' => 't3://page?uid=' . $pageIds['about']],
    ['link_label' => 'Leistungen', 'link' => 't3://page?uid=' . $pageIds['services']],
    ['link_label' => 'Projekte', 'link' => 't3://page?uid=' . $pageIds['portfolio']],
    ['link_label' => 'Kontakt', 'link' => 't3://page?uid=' . $pageIds['contact']],
];
$navigationItemsEn = [
    ['link_label' => 'About us', 'link' => 't3://page?uid=' . $pageIds['about']],
    ['link_label' => 'Services', 'link' => 't3://page?uid=' . $pageIds['services']],
    ['link_label' => 'Projects', 'link' => 't3://page?uid=' . $pageIds['portfolio']],
    ['link_label' => 'Contact', 'link' => 't3://page?uid=' . $pageIds['contact']],
];

foreach ($navigationItemsDe as $index => $item) {
    $sorting = ($index + 1) * 128;
    $deUid = insertListItem($pdo, $timestamp, $footerNavigation, 'tt_content', $sorting, $item);
    insertListItem($pdo, $timestamp, $footerNavigationEn, 'tt_content', $sorting, array_merge($navigationItemsEn[$index], [
        'sys_language_uid' => 1,
        'l10n_parent' => $deUid,
        'l10n_source' => $deUid,
    ]));
}

foreach ($serviceItemsDe as $index => $item) {
    $sorting = ($index + 1) * 128;
    $deUid = insertListItem($pdo, $timestamp, $footerServices, 'tt_content', $sorting, [
        'link_label' => $item['header'],
        'link' => $item['link'],
    ]);
    insertListItem($pdo, $timestamp, $footerServicesEn, 'tt_content', $sorting, [
        'sys_language_uid' => 1,
        'l10n_parent' => $deUid,
        'l10n_source' => $deUid,
        'link_label' => $serviceItemsEn[$index]['header'],
        'link' => $serviceItemsEn[$index]['link'],
    ]);
}

$footerContactItemsDe = [
    ['link_label' => 'info@landolsi.de', 'link' => 'mailto:info@landolsi.de'],
    ['link_label' => 'landolsi.de', 'link' => 'https://landolsi.de'],
    ['link_label' => 'Strategiegespräch', 'link' => 'https://landolsi.de/kontakt'],
];
$footerContactItemsEn = [
    ['link_label' => 'info@landolsi.de', 'link' => 'mailto:info@landolsi.de'],
    ['link_label' => 'landolsi.de', 'link' => 'https://landolsi.de'],
    ['link_label' => 'Strategy call', 'link' => 'https://landolsi.de/kontakt'],
];

foreach ($footerContactItemsDe as $index => $item) {
    $sorting = ($index + 1) * 128;
    $deUid = insertListItem($pdo, $timestamp, $footerContact, 'tt_content', $sorting, $item);
    insertListItem($pdo, $timestamp, $footerContactEn, 'tt_content', $sorting, array_merge($footerContactItemsEn[$index], [
        'sys_language_uid' => 1,
        'l10n_parent' => $deUid,
        'l10n_source' => $deUid,
    ]));
}

$footerMetaItemsDe = [
    ['link_label' => '© 2026 Landolsi Webdesign', 'link' => 'https://landolsi.de'],
    ['link_label' => 'Datenschutz', 'link' => 't3://page?uid=' . $pageIds['privacy']],
    ['link_label' => 'Impressum', 'link' => 't3://page?uid=' . $pageIds['imprint']],
];
$footerMetaItemsEn = [
    ['link_label' => '© 2026 Landolsi Webdesign', 'link' => 'https://landolsi.de'],
    ['link_label' => 'Privacy', 'link' => 't3://page?uid=' . $pageIds['privacy']],
    ['link_label' => 'Imprint', 'link' => 't3://page?uid=' . $pageIds['imprint']],
];

foreach ($footerMetaItemsDe as $index => $item) {
    $sorting = ($index + 1) * 128;
    $deUid = insertListItem($pdo, $timestamp, $footerMeta, 'tt_content', $sorting, $item);
    insertListItem($pdo, $timestamp, $footerMetaEn, 'tt_content', $sorting, array_merge($footerMetaItemsEn[$index], [
        'sys_language_uid' => 1,
        'l10n_parent' => $deUid,
        'l10n_source' => $deUid,
    ]));
}

echo "Showcase rebuilt.\n";
echo 'Pages: ' . count($pageIds) . " DE + " . count($pageIdsEn) . " EN\n";
echo "Remember to flush caches afterwards.\n";
