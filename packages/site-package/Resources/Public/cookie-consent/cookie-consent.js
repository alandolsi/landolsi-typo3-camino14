(function () {
    'use strict';

    const STORAGE_KEY = 'landolsi_cookie_consent_v1';
    const EVENT_NAME = 'landolsi:consent-updated';

    const TEXTS = {
        de: {
            bannerTitle: 'Privatsphaere-Einstellungen',
            bannerText: 'Wir verwenden technisch notwendige Speicherungen fuer den Betrieb der Website. Komfort-Funktionen wie das Farbschema speichern wir nur, wenn Sie zustimmen.',
            acceptAll: 'Alle akzeptieren',
            necessaryOnly: 'Nur notwendige',
            customize: 'Einstellungen',
            settingsTitle: 'Cookie- und Datenschutz-Einstellungen',
            settingsIntro: 'Hier koennen Sie festlegen, welche Kategorien aktiviert werden. Technisch notwendige Speicherungen sind fuer den Betrieb erforderlich.',
            close: 'Schliessen',
            save: 'Auswahl speichern',
            manage: 'Datenschutz',
            groups: {
                necessary: {
                    title: 'Technisch notwendig',
                    text: 'Erforderlich fuer Grundfunktionen wie die Speicherung Ihrer Consent-Auswahl. Diese Kategorie ist immer aktiv.',
                },
                preferences: {
                    title: 'Komfort',
                    text: 'Speichert freiwillige Einstellungen wie das gewaehlte Camino-Farbschema auf diesem Geraet.',
                },
                statistics: {
                    title: 'Statistik',
                    text: 'Erlaubt Reichweitenmessung mit OpenPanel, damit wir Seitenaufrufe und Nutzung der Demo-Website auswerten koennen.',
                },
                marketing: {
                    title: 'Marketing',
                    text: 'Reserviert fuer externe Marketing- oder Einbettungsdienste. Aktuell sind keine Marketing-Dienste aktiv.',
                },
            },
        },
        en: {
            bannerTitle: 'Privacy preferences',
            bannerText: 'We use technically necessary storage to operate this website. Comfort features such as the color scheme are stored only if you agree.',
            acceptAll: 'Accept all',
            necessaryOnly: 'Necessary only',
            customize: 'Settings',
            settingsTitle: 'Cookie and privacy settings',
            settingsIntro: 'Choose which categories should be active. Technically necessary storage is required for website operation.',
            close: 'Close',
            save: 'Save selection',
            manage: 'Privacy',
            groups: {
                necessary: {
                    title: 'Technically necessary',
                    text: 'Required for basic functions such as storing your consent choice. This category is always active.',
                },
                preferences: {
                    title: 'Preferences',
                    text: 'Stores voluntary settings such as the selected Camino color scheme on this device.',
                },
                statistics: {
                    title: 'Statistics',
                    text: 'Allows analytics with OpenPanel so we can evaluate page views and usage of the demo website.',
                },
                marketing: {
                    title: 'Marketing',
                    text: 'Reserved for external marketing or embed services. No marketing services are currently active.',
                },
            },
        },
    };

    const DEFAULT_CONSENT = {
        necessary: true,
        preferences: false,
        statistics: false,
        marketing: false,
    };

    function getLanguage() {
        return document.documentElement.lang
            && document.documentElement.lang.toLowerCase().indexOf('de') === 0 ? 'de' : 'en';
    }

    function safeParse(value) {
        try {
            return value ? JSON.parse(value) : null;
        } catch (e) {
            return null;
        }
    }

    function readConsent() {
        const stored = safeParse(window.localStorage.getItem(STORAGE_KEY));
        if (!stored || stored.version !== 1 || !stored.categories) {
            return null;
        }

        return Object.assign({}, DEFAULT_CONSENT, stored.categories, { necessary: true });
    }

    function writeConsent(categories) {
        const consent = Object.assign({}, DEFAULT_CONSENT, categories, { necessary: true });
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify({
            version: 1,
            updatedAt: new Date().toISOString(),
            categories: consent,
        }));
        window.dispatchEvent(new CustomEvent(EVENT_NAME, { detail: consent }));
        return consent;
    }

    function hasConsent(category) {
        const consent = readConsent();
        return Boolean(consent && consent[category]);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildSwitch(name, active, disabled) {
        return (
            '<label class="cookie-consent-settings__switch">' +
                '<input type="checkbox" name="' + escapeHtml(name) + '"' + (active ? ' checked' : '') + (disabled ? ' disabled' : '') + '>' +
                '<span class="cookie-consent-settings__switch-track" aria-hidden="true"></span>' +
            '</label>'
        );
    }

    function buildSettingsDialog(texts, currentConsent) {
        const categories = ['necessary', 'preferences', 'statistics', 'marketing'];
        const groups = categories.map(function (category) {
            return (
                '<div class="cookie-consent-settings__group">' +
                    '<div>' +
                        '<h3 class="cookie-consent-settings__group-title">' + escapeHtml(texts.groups[category].title) + '</h3>' +
                        '<p class="cookie-consent-settings__group-text">' + escapeHtml(texts.groups[category].text) + '</p>' +
                    '</div>' +
                    buildSwitch(category, currentConsent[category], category === 'necessary') +
                '</div>'
            );
        }).join('');

        const wrapper = document.createElement('div');
        wrapper.className = 'cookie-consent-settings';
        wrapper.hidden = true;
        wrapper.innerHTML = (
            '<div class="cookie-consent-settings__dialog" role="dialog" aria-modal="true" aria-labelledby="cookie-consent-settings-title">' +
                '<div class="cookie-consent-settings__header">' +
                    '<div>' +
                        '<h2 id="cookie-consent-settings-title" class="cookie-consent-settings__title">' + escapeHtml(texts.settingsTitle) + '</h2>' +
                        '<p class="cookie-consent-settings__intro">' + escapeHtml(texts.settingsIntro) + '</p>' +
                    '</div>' +
                    '<button class="cookie-consent-settings__close" type="button" data-cookie-consent-close aria-label="' + escapeHtml(texts.close) + '">×</button>' +
                '</div>' +
                '<form data-cookie-consent-form>' +
                    '<div class="cookie-consent-settings__groups">' + groups + '</div>' +
                    '<div class="cookie-consent-settings__actions">' +
                        '<button class="cookie-consent__button cookie-consent__button--soft" type="button" data-cookie-consent-necessary>' + escapeHtml(texts.necessaryOnly) + '</button>' +
                        '<button class="cookie-consent__button cookie-consent__button--primary" type="submit">' + escapeHtml(texts.save) + '</button>' +
                    '</div>' +
                '</form>' +
            '</div>'
        );

        return wrapper;
    }

    function buildBanner(texts) {
        const banner = document.createElement('div');
        banner.className = 'cookie-consent';
        banner.setAttribute('role', 'region');
        banner.setAttribute('aria-label', texts.bannerTitle);
        banner.innerHTML = (
            '<div class="cookie-consent__panel">' +
                '<div>' +
                    '<h2 class="cookie-consent__title">' + escapeHtml(texts.bannerTitle) + '</h2>' +
                    '<p class="cookie-consent__text">' + escapeHtml(texts.bannerText) + '</p>' +
                '</div>' +
                '<div class="cookie-consent__actions">' +
                    '<button class="cookie-consent__button cookie-consent__button--soft" type="button" data-cookie-consent-necessary>' + escapeHtml(texts.necessaryOnly) + '</button>' +
                    '<button class="cookie-consent__button" type="button" data-cookie-consent-customize>' + escapeHtml(texts.customize) + '</button>' +
                    '<button class="cookie-consent__button cookie-consent__button--primary" type="button" data-cookie-consent-all>' + escapeHtml(texts.acceptAll) + '</button>' +
                '</div>' +
            '</div>'
        );
        return banner;
    }

    function buildManageButton(texts) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'cookie-consent-manage';
        button.textContent = texts.manage;
        return button;
    }

    function initConsentBanner() {
        const language = getLanguage();
        const texts = TEXTS[language];
        const existingConsent = readConsent();
        const currentConsent = existingConsent || DEFAULT_CONSENT;
        const banner = buildBanner(texts);
        const settings = buildSettingsDialog(texts, currentConsent);
        const manage = buildManageButton(texts);

        function closeBanner() {
            banner.hidden = true;
            settings.hidden = true;
            manage.hidden = false;
        }

        function openSettings() {
            const consent = readConsent() || DEFAULT_CONSENT;
            settings.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                input.checked = Boolean(consent[input.name]);
            });
            settings.hidden = false;
            settings.querySelector('[data-cookie-consent-close]').focus();
        }

        function save(categories) {
            writeConsent(categories);
            closeBanner();
        }

        banner.querySelector('[data-cookie-consent-all]').addEventListener('click', function () {
            save({ preferences: true, statistics: true, marketing: true });
        });

        banner.querySelector('[data-cookie-consent-necessary]').addEventListener('click', function () {
            save({ preferences: false, statistics: false, marketing: false });
        });

        banner.querySelector('[data-cookie-consent-customize]').addEventListener('click', openSettings);
        manage.addEventListener('click', openSettings);

        settings.querySelector('[data-cookie-consent-close]').addEventListener('click', function () {
            settings.hidden = true;
        });

        settings.querySelector('[data-cookie-consent-necessary]').addEventListener('click', function () {
            save({ preferences: false, statistics: false, marketing: false });
        });

        settings.querySelector('[data-cookie-consent-form]').addEventListener('submit', function (event) {
            event.preventDefault();
            const form = event.currentTarget;
            save({
                preferences: form.elements.preferences.checked,
                statistics: form.elements.statistics.checked,
                marketing: form.elements.marketing.checked,
            });
        });

        settings.addEventListener('click', function (event) {
            if (event.target === settings) {
                settings.hidden = true;
            }
        });

        document.body.appendChild(banner);
        document.body.appendChild(settings);
        document.body.appendChild(manage);

        if (existingConsent) {
            banner.hidden = true;
            manage.hidden = false;
        } else {
            manage.hidden = true;
        }
    }

    window.LandolsiConsent = {
        get: readConsent,
        has: hasConsent,
        update: writeConsent,
        reset: function () {
            window.localStorage.removeItem(STORAGE_KEY);
            window.location.reload();
        },
        eventName: EVENT_NAME,
    };

    document.addEventListener('DOMContentLoaded', initConsentBanner);
})();
