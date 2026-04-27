# Content Blocks

Dieses Projekt nutzt `friendsoftypo3/content-blocks`, um projektspezifische TYPO3-Inhaltselemente direkt im Site-Package zu verwalten.

## Camino CTA

Das erste eigene Inhaltselement ist `Camino CTA`:

- Kicker
- Überschrift über das TYPO3-Standardfeld `header`
- Richtext
- Button-Beschriftung und TYPO3-Link
- optionales Vorschaubild mit Crop-Variante
- Backend-Preview mit Thumbnail und Textauszug

Die Dateien liegen unter:

```text
packages/site-package/ContentBlocks/ContentElements/camino-cta/
├── assets/icon.svg
├── config.yaml
├── language/labels.xlf
└── templates/
    ├── backend-preview.fluid.html
    └── frontend.fluid.html
```

## Lokale Pflege nach Änderungen

Nach Änderungen an `config.yaml` oder neuen Content Blocks:

```bash
ddev typo3 content-blocks:lint
ddev typo3 cache:flush -g system
ddev typo3 extension:setup --extension=site_package
```

## Nächster sinnvoller Schritt

Das aktuelle JavaScript-Homepage-Carousel kann später als eigener Content Block modelliert werden. Dafür bietet sich eine Collection aus Slides an, jeweils mit Bild, Titel, Text, Button-Link und Backend-Preview.
