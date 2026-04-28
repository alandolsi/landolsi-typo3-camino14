(function () {
    'use strict';

    function labelLogoLinks() {
        document.querySelectorAll('.header__logo, .footer__logo').forEach(function (link) {
            if (!link.textContent.trim() && !link.getAttribute('aria-label')) {
                link.setAttribute('aria-label', 'Landolsi Webdesign Startseite');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', labelLogoLinks);
})();
