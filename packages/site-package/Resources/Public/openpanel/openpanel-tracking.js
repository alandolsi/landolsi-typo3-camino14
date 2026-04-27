(function () {
    'use strict';

    const CLIENT_ID = '31dfe4e3-f5b0-488d-91a5-99b3a5731563';
    const API_URL = 'https://stats.landolsi.de';
    const SDK_URL = 'https://cdn.jsdelivr.net/npm/@openpanel/sdk@1.3.0/+esm';
    const CONSENT_CATEGORY = 'statistics';
    const TRACKED_HOSTNAMES = ['camino14.landolsi.de'];

    let openPanel = null;
    let loadingPromise = null;
    let lastTrackedUrl = null;

    function hasStatisticsConsent() {
        return Boolean(
            TRACKED_HOSTNAMES.includes(window.location.hostname)
            && window.LandolsiConsent
            && window.LandolsiConsent.has(CONSENT_CATEGORY)
        );
    }

    function buildPageProperties() {
        return {
            url: window.location.href,
            path: window.location.pathname,
            title: document.title,
            referrer: document.referrer || undefined,
            language: document.documentElement.lang || undefined,
            hostname: window.location.hostname,
        };
    }

    async function loadOpenPanel() {
        if (openPanel) {
            return openPanel;
        }
        if (!loadingPromise) {
            loadingPromise = import(SDK_URL).then(function (module) {
                openPanel = new module.OpenPanel({
                    clientId: CLIENT_ID,
                    apiUrl: API_URL,
                    sdk: 'web',
                });
                openPanel.setGlobalProperties({
                    project: 'landolsi-typo3-camino14',
                    environment: 'production',
                });
                return openPanel;
            });
        }
        return loadingPromise;
    }

    async function trackPageView(force) {
        if (!hasStatisticsConsent()) {
            return;
        }

        const currentUrl = window.location.href;
        if (!force && currentUrl === lastTrackedUrl) {
            return;
        }

        const op = await loadOpenPanel();
        lastTrackedUrl = currentUrl;
        op.track('pageview', buildPageProperties());
    }

    function onConsentUpdated(event) {
        if (event.detail && event.detail[CONSENT_CATEGORY]) {
            trackPageView(true);
        }
    }

    function init() {
        if (hasStatisticsConsent()) {
            trackPageView(true);
        }

        if (window.LandolsiConsent && window.LandolsiConsent.eventName) {
            window.addEventListener(window.LandolsiConsent.eventName, onConsentUpdated);
        }

        window.addEventListener('popstate', function () {
            trackPageView(false);
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
