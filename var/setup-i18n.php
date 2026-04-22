<?php
/**
 * Setup Multilingual Translations (Deutsch/English)
 *
 * Creates English (languageId=1) translation overlays for:
 *   - pages
 *   - tt_content
 *   - tx_themecamino_list_item
 *   - sys_file_reference (for translated content with images)
 *
 * Run: ddev exec php var/setup-i18n.php
 * Idempotent: re-running updates existing translations.
 */

$pdo = new PDO('mysql:host=db;dbname=db;charset=utf8mb4', 'db', 'db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ts = time();

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function upsertPage(PDO $pdo, int $l10nParent, array $data): int
{
    $stmt = $pdo->prepare(
        'SELECT uid FROM pages WHERE sys_language_uid=1 AND l10n_parent=? AND deleted=0 LIMIT 1'
    );
    $stmt->execute([$l10nParent]);
    $uid = $stmt->fetchColumn();

    if ($uid) {
        $set = implode(', ', array_map(fn($k) => "$k=:$k", array_keys($data)));
        $pdo->prepare("UPDATE pages SET $set WHERE uid=:uid")->execute([...$data, 'uid' => $uid]);
        echo "  [UPDATE] pages uid=$uid ({$data['title']})\n";
        return (int)$uid;
    }

    $cols = implode(', ', array_keys($data));
    $vals = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
    $pdo->prepare("INSERT INTO pages ($cols) VALUES ($vals)")->execute($data);
    $uid = (int)$pdo->lastInsertId();
    echo "  [INSERT] pages uid=$uid ({$data['title']})\n";
    return $uid;
}

function upsertContent(PDO $pdo, int $l18nParent, array $data): int
{
    $stmt = $pdo->prepare(
        'SELECT uid FROM tt_content WHERE sys_language_uid=1 AND l18n_parent=? AND deleted=0 LIMIT 1'
    );
    $stmt->execute([$l18nParent]);
    $uid = $stmt->fetchColumn();

    if ($uid) {
        $set = implode(', ', array_map(fn($k) => "$k=:$k", array_keys($data)));
        $pdo->prepare("UPDATE tt_content SET $set WHERE uid=:uid")->execute([...$data, 'uid' => $uid]);
        $label = $data['header'] ?: "(no header)";
        echo "  [UPDATE] tt_content uid=$uid ($label)\n";
        return (int)$uid;
    }

    $cols = implode(', ', array_keys($data));
    $vals = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
    $pdo->prepare("INSERT INTO tt_content ($cols) VALUES ($vals)")->execute($data);
    $uid = (int)$pdo->lastInsertId();
    $label = $data['header'] ?: "(no header)";
    echo "  [INSERT] tt_content uid=$uid ($label)\n";
    return $uid;
}

function upsertListItem(PDO $pdo, int $l10nParent, array $data): int
{
    $stmt = $pdo->prepare(
        'SELECT uid FROM tx_themecamino_list_item WHERE sys_language_uid=1 AND l10n_parent=? AND deleted=0 LIMIT 1'
    );
    $stmt->execute([$l10nParent]);
    $uid = $stmt->fetchColumn();

    if ($uid) {
        $set = implode(', ', array_map(fn($k) => "$k=:$k", array_keys($data)));
        $pdo->prepare("UPDATE tx_themecamino_list_item SET $set WHERE uid=:uid")->execute([...$data, 'uid' => $uid]);
        echo "  [UPDATE] list_item uid=$uid (l10n_parent=$l10nParent)\n";
        return (int)$uid;
    }

    $cols = implode(', ', array_keys($data));
    $vals = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
    $pdo->prepare("INSERT INTO tx_themecamino_list_item ($cols) VALUES ($vals)")->execute($data);
    $uid = (int)$pdo->lastInsertId();
    echo "  [INSERT] list_item uid=$uid (l10n_parent=$l10nParent)\n";
    return $uid;
}

function upsertFileRef(PDO $pdo, int $uidLocal, int $uidForeign, string $tablenames, string $fieldname, int $sortingForeign): void
{
    $stmt = $pdo->prepare(
        'SELECT uid FROM sys_file_reference WHERE sys_language_uid=1 AND uid_foreign=? AND tablenames=? AND fieldname=? AND deleted=0 LIMIT 1'
    );
    $stmt->execute([$uidForeign, $tablenames, $fieldname]);
    $uid = $stmt->fetchColumn();
    if ($uid) {
        echo "  [SKIP]   sys_file_reference already exists for uid_foreign=$uidForeign\n";
        return;
    }
    $pdo->prepare(
        'INSERT INTO sys_file_reference
            (pid, tstamp, crdate, uid_local, uid_foreign, tablenames, fieldname,
             sorting_foreign, title, alternative, description, link,
             hidden, deleted, crop, autoplay, sys_language_uid)
         VALUES
            (0, :ts, :ts, :ul, :uf, :tn, :fn, :sf, NULL, NULL, NULL, \'\',
             0, 0, NULL, 0, 1)'
    )->execute([
        ':ts' => time(), ':ul' => $uidLocal, ':uf' => $uidForeign,
        ':tn' => $tablenames, ':fn' => $fieldname, ':sf' => $sortingForeign,
    ]);
    $uid = (int)$pdo->lastInsertId();
    echo "  [INSERT] sys_file_reference uid=$uid (file=$uidLocal → {$tablenames}.{$uidForeign})\n";
}

function getOrig(PDO $pdo, int $uid): array
{
    return $pdo->query(
        "SELECT pid, colPos, sorting, CType, header_link, subheader FROM tt_content WHERE uid=$uid"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
}

// ---------------------------------------------------------------------------
// PAGES
// ---------------------------------------------------------------------------

echo "\n=== Pages ===\n";

$pageMap = [];

$pageMap[1] = upsertPage($pdo, 1, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 1, 'l10n_source' => 1,
    'title' => 'Home', 'slug' => '/', 'doktype' => 1,
    // backend_layout must be set on translated pages too, otherwise PAGEVIEW breaks
    'backend_layout' => 'pagets__CaminoStartpage',
    'backend_layout_next_level' => 'pagets__CaminoContentpage',
    'hidden' => 0, 'deleted' => 0, 'sorting' => 256,
]);
$pageMap[6] = upsertPage($pdo, 6, [
    'pid' => 1, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 6, 'l10n_source' => 6,
    'title' => 'About Us', 'slug' => '/about-us', 'doktype' => 1,
    'backend_layout_next_level' => 'pagets__CaminoContentpage',
    'hidden' => 0, 'deleted' => 0, 'sorting' => 256,
]);
$pageMap[7] = upsertPage($pdo, 7, [
    'pid' => 1, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 7, 'l10n_source' => 7,
    'title' => 'Services', 'slug' => '/services', 'doktype' => 1,
    'backend_layout_next_level' => 'pagets__CaminoContentpage',
    'hidden' => 0, 'deleted' => 0, 'sorting' => 256,
]);
$pageMap[8] = upsertPage($pdo, 8, [
    'pid' => 1, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 8, 'l10n_source' => 8,
    'title' => 'Portfolio', 'slug' => '/portfolio', 'doktype' => 1,
    'backend_layout_next_level' => 'pagets__CaminoContentpage',
    'hidden' => 0, 'deleted' => 0, 'sorting' => 256,
]);
$pageMap[9] = upsertPage($pdo, 9, [
    'pid' => 1, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 9, 'l10n_source' => 9,
    'title' => 'Contact', 'slug' => '/contact', 'doktype' => 1,
    'backend_layout_next_level' => 'pagets__CaminoContentpage',
    'hidden' => 0, 'deleted' => 0, 'sorting' => 256,
]);

// ---------------------------------------------------------------------------
// CONTENT ELEMENTS – Home (pid=1)
// ---------------------------------------------------------------------------

echo "\n=== Home Page Content ===\n";

$orig = getOrig($pdo, 52);
upsertContent($pdo, 52, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 52,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Web Design That Inspires.',
    'subheader' => 'Your Partner for Professional TYPO3 Websites',
    'header_link' => 'https://landolsi.de/kontakt',
    'bodytext' => '', 'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 53);
upsertContent($pdo, 53, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 53,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Web Design',
    'bodytext' => '<p>Individual, responsive websites that showcase your brand perfectly – modern, fast and accessible.</p>',
    'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 54);
upsertContent($pdo, 54, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 54,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'TYPO3 CMS',
    'bodytext' => '<p>Powerful content management with TYPO3 14 LTS – scalable, secure and future-proof for your digital presence.</p>',
    'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 55);
upsertContent($pdo, 55, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 55,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'SEO & Performance',
    'bodytext' => '<p>Better rankings, faster load times and more organic reach – technical SEO and Core Web Vitals implemented sustainably and measurably.</p>',
    'hidden' => 0, 'deleted' => 0,
]);

// ---------------------------------------------------------------------------
// CONTENT ELEMENTS – Über uns / About Us (pid=6)
// ---------------------------------------------------------------------------

echo "\n=== About Us Page Content ===\n";

$orig = getOrig($pdo, 56);
upsertContent($pdo, 56, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 56,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'About Us',
    'subheader' => 'Passion for web development.',
    'bodytext' => '', 'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 57);
upsertContent($pdo, 57, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 57,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'We Create Digital Experiences',
    'bodytext' => '<p><strong>Landolsi Webdesign</strong> stands for professional web development with passion. As a dedicated TYPO3 agency, we develop tailor-made solutions for medium-sized companies and ambitious projects.</p><p>We work with TYPO3 14 LTS, modern Composer architecture and proven workflows – from concept to deployment on CloudPanel.</p>',
    'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 58);
$uid58en = upsertContent($pdo, 58, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 58,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Our Team', 'subheader' => '',
    'image' => 1, 'bodytext' => '', 'hidden' => 0, 'deleted' => 0,
]);
upsertFileRef($pdo, 8, $uid58en, 'tt_content', 'image', 1);

$orig = getOrig($pdo, 59);
upsertContent($pdo, 59, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 59,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Quality',
    'bodytext' => '<p>No copy-paste, no one-size-fits-all. Every project is individually conceived and carefully executed.</p>',
    'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 60);
upsertContent($pdo, 60, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 60,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Transparency',
    'bodytext' => '<p>Open communication, honest scheduling and clear pricing – from start to finish.</p>',
    'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 61);
upsertContent($pdo, 61, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 61,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Partnership',
    'bodytext' => '<p>We think long-term. Our goal is a sustainable partnership, not a one-time project.</p>',
    'hidden' => 0, 'deleted' => 0,
]);

// ---------------------------------------------------------------------------
// CONTENT ELEMENTS – Leistungen / Services (pid=7)
// ---------------------------------------------------------------------------

echo "\n=== Services Page Content ===\n";

$orig = getOrig($pdo, 62);
upsertContent($pdo, 62, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 62,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Our Services',
    'subheader' => 'What we offer',
    'bodytext' => '', 'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 63);
$uid63en = upsertContent($pdo, 63, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 63,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Web Design & UI/UX',
    'image' => 1,
    'bodytext' => '<p>We design websites that inspire: user-friendly, modern and conversion-optimised. Every design is developed individually according to your requirements – with a focus on usability, brand identity and measurable results.</p>',
    'hidden' => 0, 'deleted' => 0,
]);
upsertFileRef($pdo, 9, $uid63en, 'tt_content', 'image', 1);

$orig = getOrig($pdo, 64);
$uid64en = upsertContent($pdo, 64, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 64,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'TYPO3 Development',
    'image' => 1,
    'bodytext' => '<p>TYPO3 14 LTS as the foundation for your website: extensible, low-maintenance and future-proof. We build what you need – with clean Composer architecture, Site Sets and a modern DevOps workflow.</p>',
    'hidden' => 0, 'deleted' => 0,
]);
upsertFileRef($pdo, 10, $uid64en, 'tt_content', 'image', 1);

$orig = getOrig($pdo, 65);
$uid65en = upsertContent($pdo, 65, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 65,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'SEO & Performance',
    'image' => 1,
    'bodytext' => '<p>Technical SEO, Core Web Vitals and structured content – we ensure your website is found on Google and loads quickly. Lighthouse score improvements of 40+ points are our benchmark.</p>',
    'hidden' => 0, 'deleted' => 0,
]);
upsertFileRef($pdo, 11, $uid65en, 'tt_content', 'image', 1);

// ---------------------------------------------------------------------------
// CONTENT ELEMENTS – Referenzen / Portfolio (pid=8)
// ---------------------------------------------------------------------------

echo "\n=== Portfolio Page Content ===\n";

$orig = getOrig($pdo, 66);
upsertContent($pdo, 66, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 66,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Portfolio',
    'subheader' => 'Projects we are proud of.',
    'bodytext' => '', 'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 67);
$uid67en = upsertContent($pdo, 67, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 67,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Selected Projects',
    'bodytext' => '<p>From conception through development to handover – our projects are created in close collaboration with our clients.</p>',
    'hidden' => 0, 'deleted' => 0,
]);

// ---------------------------------------------------------------------------
// CONTENT ELEMENTS – Kontakt / Contact (pid=9)
// ---------------------------------------------------------------------------

echo "\n=== Contact Page Content ===\n";

$orig = getOrig($pdo, 68);
upsertContent($pdo, 68, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 68,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Contact',
    'subheader' => "Let's talk.",
    'bodytext' => '', 'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 69);
upsertContent($pdo, 69, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 69,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Get in Touch',
    'bodytext' => '<p><strong>Landolsi Webdesign</strong><br>Professional Web Development &amp; TYPO3 Agency</p><p>📧 <a href="mailto:info@landolsi.de">info@landolsi.de</a><br>🌐 <a href="https://landolsi.de" target="_blank">landolsi.de</a><br>📍 Germany</p>',
    'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 70);
upsertContent($pdo, 70, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 70,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Project Inquiry',
    'header_link' => 'https://landolsi.de/kontakt',
    'bodytext' => '<p>Have a project in mind? Tell us your idea – free and without obligation. We will create an individual proposal for you.</p>',
    'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 71);
upsertContent($pdo, 71, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 71,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'TYPO3 Support',
    'header_link' => 'https://landolsi.de/kontakt',
    'bodytext' => '<p>Do you already have a TYPO3 installation and need help or further development? We reliably take over existing projects too.</p>',
    'hidden' => 0, 'deleted' => 0,
]);

// ---------------------------------------------------------------------------
// FOOTER CONTENT ELEMENTS (pid=1, colPos 10–14)
// ---------------------------------------------------------------------------

echo "\n=== Footer Content ===\n";

$orig = getOrig($pdo, 72);
$uid72en = upsertContent($pdo, 72, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 72,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Landolsi Webdesign', 'bodytext' => '', 'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 73);
$uid73en = upsertContent($pdo, 73, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 73,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Navigation', 'bodytext' => '', 'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 74);
$uid74en = upsertContent($pdo, 74, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 74,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Services', 'bodytext' => '', 'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 75);
$uid75en = upsertContent($pdo, 75, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 75,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => 'Contact', 'bodytext' => '', 'hidden' => 0, 'deleted' => 0,
]);

$orig = getOrig($pdo, 76);
$uid76en = upsertContent($pdo, 76, [
    'pid' => $orig['pid'], 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l18n_parent' => 76,
    'CType' => $orig['CType'], 'colPos' => $orig['colPos'], 'sorting' => $orig['sorting'],
    'header' => '', 'bodytext' => '', 'hidden' => 0, 'deleted' => 0,
]);

// ---------------------------------------------------------------------------
// LIST ITEMS – Footer "Landolsi Webdesign" column (→ uid72en)
// ---------------------------------------------------------------------------

echo "\n=== Footer List Items ===\n";

upsertListItem($pdo, 35, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 35, 'l10n_source' => 35,
    'uid_foreign' => $uid72en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => 'To Homepage', 'link' => 't3://page?uid=1',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 1,
]);
upsertListItem($pdo, 36, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 36, 'l10n_source' => 36,
    'uid_foreign' => $uid72en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => 'About Us', 'link' => 't3://page?uid=6',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 2,
]);

// Footer "Navigation" column (→ uid73en)
upsertListItem($pdo, 37, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 37, 'l10n_source' => 37,
    'uid_foreign' => $uid73en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => 'Home', 'link' => 't3://page?uid=1',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 1,
]);
upsertListItem($pdo, 38, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 38, 'l10n_source' => 38,
    'uid_foreign' => $uid73en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => 'About Us', 'link' => 't3://page?uid=6',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 2,
]);
upsertListItem($pdo, 39, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 39, 'l10n_source' => 39,
    'uid_foreign' => $uid73en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => 'Services', 'link' => 't3://page?uid=7',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 3,
]);
upsertListItem($pdo, 40, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 40, 'l10n_source' => 40,
    'uid_foreign' => $uid73en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => 'Portfolio', 'link' => 't3://page?uid=8',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 4,
]);
upsertListItem($pdo, 41, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 41, 'l10n_source' => 41,
    'uid_foreign' => $uid73en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => 'Contact', 'link' => 't3://page?uid=9',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 5,
]);

// Footer "Services" column (→ uid74en)
upsertListItem($pdo, 42, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 42, 'l10n_source' => 42,
    'uid_foreign' => $uid74en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => 'Web Design & UI/UX', 'link' => 't3://page?uid=7',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 1,
]);
upsertListItem($pdo, 43, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 43, 'l10n_source' => 43,
    'uid_foreign' => $uid74en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => 'TYPO3 Development', 'link' => 't3://page?uid=7',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 2,
]);
upsertListItem($pdo, 44, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 44, 'l10n_source' => 44,
    'uid_foreign' => $uid74en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => 'SEO & Performance', 'link' => 't3://page?uid=7',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 3,
]);

// Footer "Contact" column (→ uid75en)
upsertListItem($pdo, 45, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 45, 'l10n_source' => 45,
    'uid_foreign' => $uid75en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => 'info@landolsi.de', 'link' => 'mailto:info@landolsi.de',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 1,
]);
upsertListItem($pdo, 46, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 46, 'l10n_source' => 46,
    'uid_foreign' => $uid75en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => 'landolsi.de', 'link' => 'https://landolsi.de',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 2,
]);
upsertListItem($pdo, 47, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 47, 'l10n_source' => 47,
    'uid_foreign' => $uid75en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => 'Get in Touch', 'link' => 'https://landolsi.de/kontakt',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 3,
]);

// Footer copyright (→ uid76en)
upsertListItem($pdo, 48, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 48, 'l10n_source' => 48,
    'uid_foreign' => $uid76en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'link_label' => '© 2026 Landolsi Webdesign', 'link' => 'https://landolsi.de',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 1,
]);

// ---------------------------------------------------------------------------
// LIST ITEMS – Portfolio grid (→ uid67en)
// ---------------------------------------------------------------------------

echo "\n=== Portfolio Grid Items ===\n";

$item49en = upsertListItem($pdo, 49, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 49, 'l10n_source' => 49,
    'uid_foreign' => $uid67en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'header' => 'Landolsi.de – Corporate Website',
    'link_label' => 'View Project', 'link' => 'https://landolsi.de',
    'text' => 'Complete TYPO3 14 relaunch for a web design studio. Development with Camino theme, optimised performance (Lighthouse 95+) and technical SEO fundamentals.',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 1,
]);
upsertFileRef($pdo, 12, $item49en, 'tx_themecamino_list_item', 'images', 1);

$item50en = upsertListItem($pdo, 50, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 50, 'l10n_source' => 50,
    'uid_foreign' => $uid67en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'header' => 'CMS Migration to TYPO3 14',
    'link_label' => 'Learn More', 'link' => 'https://landolsi.de',
    'text' => 'Migration of an existing corporate website to TYPO3 14 LTS including full data migration, template redevelopment and editor training.',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 2,
]);
upsertFileRef($pdo, 13, $item50en, 'tx_themecamino_list_item', 'images', 1);

$item51en = upsertListItem($pdo, 51, [
    'pid' => 0, 'tstamp' => $ts, 'crdate' => $ts,
    'sys_language_uid' => 1, 'l10n_parent' => 51, 'l10n_source' => 51,
    'uid_foreign' => $uid67en, 'tablename' => 'tt_content',
    'fieldname' => 'tx_themecamino_list_elements',
    'header' => 'SEO & Core Web Vitals',
    'link_label' => 'Learn More', 'link' => 'https://landolsi.de',
    'text' => 'Technical SEO analysis and performance optimisation: Lighthouse score raised from 45 to 94, organic traffic +65 % in just 6 months.',
    'hidden' => 0, 'deleted' => 0, 'sorting_foreign' => 3,
]);
upsertFileRef($pdo, 14, $item51en, 'tx_themecamino_list_item', 'images', 1);

// ---------------------------------------------------------------------------
// Done
// ---------------------------------------------------------------------------

echo "\n✅ All translations created/updated.\n";
echo "Next: ddev exec vendor/bin/typo3 cache:flush\n\n";
echo "URLs to test:\n";
echo "  DE: https://camino14.ddev.site/\n";
echo "  EN: https://camino14.ddev.site/en/\n";
echo "  DE Über uns: https://camino14.ddev.site/ueber-uns\n";
echo "  EN About Us: https://camino14.ddev.site/en/about-us\n";
