/**
 * Camino Color Switcher
 * Switches between the 4 official Camino CSS theme classes.
 * Keeps the current session in sync and persists choice when preference consent is enabled.
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'camino_color_scheme';
    const SESSION_STORAGE_KEY = 'camino_color_scheme_session';

    const THEMES = [
        { id: 'theme-caramel-cream', label: 'Caramel Cream', swatch: 'caramel' },
        { id: 'theme-ocean-breeze',  label: 'Ocean Breeze',  swatch: 'ocean'   },
        { id: 'theme-forest-mist',   label: 'Forest Mist',   swatch: 'forest'  },
        { id: 'theme-violet-velvet', label: 'Violet Velvet', swatch: 'violet'  },
    ];

    // All known theme classes (for clean removal before applying new one)
    const ALL_CLASSES = THEMES.map(t => t.id);

    function preferencesAllowed() {
        return Boolean(window.LandolsiConsent && window.LandolsiConsent.has('preferences'));
    }

    function isKnownTheme(themeId) {
        return ALL_CLASSES.includes(themeId);
    }

    function readStorage(storage, key) {
        try {
            const value = storage.getItem(key);
            return isKnownTheme(value) ? value : null;
        } catch (e) {
            return null;
        }
    }

    function writeStorage(storage, key, value) {
        try {
            storage.setItem(key, value);
        } catch (e) {
            // Ignore unavailable browser storage; the theme still applies on the current page.
        }
    }

    function removeStorage(storage, key) {
        try {
            storage.removeItem(key);
        } catch (e) {
            // Ignore unavailable browser storage.
        }
    }

    function getStoredTheme() {
        if (preferencesAllowed()) {
            const persisted = readStorage(localStorage, STORAGE_KEY);
            if (persisted) {
                return persisted;
            }
        }

        return readStorage(sessionStorage, SESSION_STORAGE_KEY);
    }

    function applyTheme(themeId) {
        document.body.classList.remove(...ALL_CLASSES);
        document.body.classList.add(themeId);
        writeStorage(sessionStorage, SESSION_STORAGE_KEY, themeId);
        if (preferencesAllowed()) {
            writeStorage(localStorage, STORAGE_KEY, themeId);
        }
    }

    function buildWidget(currentTheme) {
        // Wrapper
        const widget = document.createElement('div');
        widget.className = 'color-switcher';
        widget.setAttribute('data-open', 'false');
        widget.setAttribute('role', 'region');
        widget.setAttribute('aria-label', 'Farbschema wählen');

        // Panel (list of themes)
        const panel = document.createElement('div');
        panel.className = 'color-switcher__panel';
        panel.setAttribute('role', 'listbox');
        panel.setAttribute('aria-label', 'Farbschema');
        panel.setAttribute('aria-activedescendant', currentTheme);

        THEMES.forEach(function (theme) {
            const btn = document.createElement('button');
            btn.className = 'color-switcher__option';
            btn.id = theme.id;
            btn.setAttribute('role', 'option');
            btn.setAttribute('aria-selected', theme.id === currentTheme ? 'true' : 'false');
            btn.setAttribute('data-theme', theme.id);
            btn.title = theme.label;

            const swatch = document.createElement('span');
            swatch.className = 'color-switcher__swatch color-switcher__swatch--' + theme.swatch;
            swatch.setAttribute('aria-hidden', 'true');

            const label = document.createElement('span');
            label.textContent = theme.label;

            btn.appendChild(swatch);
            btn.appendChild(label);

            btn.addEventListener('click', function () {
                applyTheme(theme.id);
                panel.querySelectorAll('.color-switcher__option').forEach(function (b) {
                    b.setAttribute('aria-selected', b.dataset.theme === theme.id ? 'true' : 'false');
                });
                panel.setAttribute('aria-activedescendant', theme.id);
                // Close panel after short delay so user sees selection
                setTimeout(function () {
                    widget.setAttribute('data-open', 'false');
                    toggle.setAttribute('aria-expanded', 'false');
                }, 300);
            });

            panel.appendChild(btn);
        });

        // Toggle button (palette SVG icon)
        const toggle = document.createElement('button');
        toggle.className = 'color-switcher__toggle';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-controls', 'color-switcher-panel');
        toggle.setAttribute('aria-label', 'Farbschema wählen');
        toggle.innerHTML =
            '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
            '<path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10a2.5 2.5 0 0 0 2.5-2.5c0-.637-.24-1.22-.633-1.658-.387-.434-.617-1.004-.617-1.592 0-1.38 1.12-2.5 2.5-2.5H18c2.21 0 4-1.79 4-4 0-4.418-4.03-8-9-8zm-5.5 9a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3-4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3 4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/>' +
            '</svg>';

        panel.id = 'color-switcher-panel';

        toggle.addEventListener('click', function () {
            const isOpen = widget.getAttribute('data-open') === 'true';
            widget.setAttribute('data-open', isOpen ? 'false' : 'true');
            toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        });

        // Close when clicking outside
        document.addEventListener('click', function (e) {
            if (!widget.contains(e.target)) {
                widget.setAttribute('data-open', 'false');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });

        widget.appendChild(panel);
        widget.appendChild(toggle);

        return widget;
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Determine active theme (consented localStorage wins, fall back to class already on body)
        const stored = getStoredTheme();
        const bodyClass = ALL_CLASSES.find(c => document.body.classList.contains(c));
        const currentTheme = stored || bodyClass || 'theme-caramel-cream';

        // Apply stored theme (overrides server-rendered class)
        if (stored && stored !== bodyClass) {
            applyTheme(stored);
        }

        document.body.appendChild(buildWidget(currentTheme));

        if (window.LandolsiConsent && window.LandolsiConsent.eventName) {
            window.addEventListener(window.LandolsiConsent.eventName, function (event) {
                const activeTheme = ALL_CLASSES.find(c => document.body.classList.contains(c)) || currentTheme;
                if (event.detail && event.detail.preferences) {
                    writeStorage(localStorage, STORAGE_KEY, activeTheme);
                } else {
                    removeStorage(localStorage, STORAGE_KEY);
                }
            });
        }
    });
})();
