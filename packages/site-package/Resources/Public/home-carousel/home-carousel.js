(function () {
    'use strict';

    const HOMEPAGE_SLIDES = {
        de: {
            label: 'Highlights von Landolsi Webdesign',
            prev: 'Vorherige Folie',
            next: 'Naechste Folie',
            slides: [
                {
                    eyebrow: 'TYPO3 14 + Camino',
                    title: 'Saubere Projekte, die Redakteuren wirklich Spass machen',
                    text: 'Wir verbinden modernes Webdesign mit klaren TYPO3-Workflows, damit Inhalte schnell gepflegt und Deployments entspannt ausgeliefert werden.',
                    ctaLabel: 'Leistungen ansehen',
                    ctaHref: '/leistungen',
                    image: '/fileadmin/demo/carousel-build.jpg',
                    alt: 'Laptop mit TYPO3 Projektarbeit',
                },
                {
                    eyebrow: 'Brand & UX',
                    title: 'Websites mit Haltung, Klarheit und einem starken visuellen Einstieg',
                    text: 'Von der ersten Storyline bis zur letzten CTA entsteht eine Oberfläche, die Vertrauen schafft und Nutzer klar durch die Seite führt.',
                    ctaLabel: 'Zu den Projekten',
                    ctaHref: '/projekte',
                    image: '/fileadmin/demo/carousel-brand.jpg',
                    alt: 'Kreatives Meeting für Markenauftritt',
                },
                {
                    eyebrow: 'SEO & Performance',
                    title: 'Schneller, strukturierter und bereit für organisches Wachstum',
                    text: 'Core Web Vitals, technische SEO und wartbare Composer-Setups sorgen dafür, dass gute Inhalte auch sichtbar werden.',
                    ctaLabel: 'Kontakt aufnehmen',
                    ctaHref: '/kontakt',
                    image: '/fileadmin/demo/carousel-growth.jpg',
                    alt: 'Performance Zahlen auf einem Dashboard',
                },
            ],
        },
        en: {
            label: 'Landolsi Webdesign highlights',
            prev: 'Previous slide',
            next: 'Next slide',
            slides: [
                {
                    eyebrow: 'TYPO3 14 + Camino',
                    title: 'Clean projects that editors genuinely enjoy working with',
                    text: 'We combine modern web design with focused TYPO3 workflows so content teams can ship updates quickly and comfortably.',
                    ctaLabel: 'View services',
                    ctaHref: '/en/services',
                    image: '/fileadmin/demo/carousel-build.jpg',
                    alt: 'Laptop with TYPO3 project work',
                },
                {
                    eyebrow: 'Brand & UX',
                    title: 'Websites with clarity, confidence and a strong visual opening',
                    text: 'From the first storyline to the final CTA, every screen is shaped to build trust and guide users with ease.',
                    ctaLabel: 'See projects',
                    ctaHref: '/en/projects',
                    image: '/fileadmin/demo/carousel-brand.jpg',
                    alt: 'Creative brand meeting',
                },
                {
                    eyebrow: 'SEO & Performance',
                    title: 'Faster, more structured and ready for measurable organic growth',
                    text: 'Core Web Vitals, technical SEO and maintainable Composer setups help strong content perform where it matters.',
                    ctaLabel: 'Get in touch',
                    ctaHref: '/en/contact',
                    image: '/fileadmin/demo/carousel-growth.jpg',
                    alt: 'Performance dashboard review',
                },
            ],
        },
    };

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildHomepageCarousel(language) {
        const content = HOMEPAGE_SLIDES[language];
        if (!content) {
            return '';
        }

        const slides = content.slides.map(function (slide, index) {
            const isActive = index === 0 ? ' is-active' : '';
            const ariaHidden = index === 0 ? 'false' : 'true';
            const loading = index === 0 ? 'eager' : 'lazy';
            const fetchPriority = index === 0 ? ' fetchpriority="high"' : '';
            return (
                '<article class="lp-carousel__slide' + isActive + '" data-lp-carousel-slide aria-hidden="' + ariaHidden + '">' +
                    '<div class="lp-carousel__copy">' +
                        '<p class="lp-carousel__eyebrow">' + escapeHtml(slide.eyebrow) + '</p>' +
                        '<h2 class="lp-carousel__title">' + escapeHtml(slide.title) + '</h2>' +
                        '<p class="lp-carousel__text">' + escapeHtml(slide.text) + '</p>' +
                        '<div class="lp-carousel__actions">' +
                            '<a class="lp-carousel__link" href="' + escapeHtml(slide.ctaHref) + '">' + escapeHtml(slide.ctaLabel) + '</a>' +
                        '</div>' +
                    '</div>' +
                    '<div class="lp-carousel__media">' +
                        '<img class="lp-carousel__image" src="' + escapeHtml(slide.image) + '" alt="' + escapeHtml(slide.alt) + '" loading="' + loading + '"' + fetchPriority + ' width="1200" height="800">' +
                    '</div>' +
                '</article>'
            );
        }).join('');

        const dots = content.slides.map(function (slide, index) {
            const isActive = index === 0 ? ' is-active' : '';
            const pressed = index === 0 ? 'true' : 'false';
            return '<button class="lp-carousel__dot' + isActive + '" type="button" data-lp-carousel-dot aria-label="' + escapeHtml(slide.title) + '" aria-pressed="' + pressed + '"></button>';
        }).join('');

        return (
            '<section class="lp-carousel" data-lp-carousel aria-label="' + escapeHtml(content.label) + '">' +
                '<div class="lp-carousel__viewport">' + slides + '</div>' +
                '<div class="lp-carousel__controls">' +
                    '<button class="lp-carousel__control" type="button" data-lp-carousel-prev aria-label="' + escapeHtml(content.prev) + '">&#8249;</button>' +
                    '<button class="lp-carousel__control" type="button" data-lp-carousel-next aria-label="' + escapeHtml(content.next) + '">&#8250;</button>' +
                '</div>' +
                '<div class="lp-carousel__pagination">' + dots + '</div>' +
            '</section>'
        );
    }

    function ensureHomepageCarousel() {
        const path = window.location.pathname.replace(/\/+$/, '') || '/';
        if (path !== '/' && path !== '/en') {
            return;
        }
        if (document.querySelector('[data-lp-carousel]')) {
            return;
        }

        const content = document.querySelector('main.page__content');
        if (!content) {
            return;
        }

        const language = document.documentElement.lang && document.documentElement.lang.toLowerCase().indexOf('de') === 0 ? 'de' : 'en';
        content.insertAdjacentHTML('afterbegin', buildHomepageCarousel(language));
    }

    function initCarousel(root) {
        const slides = Array.from(root.querySelectorAll('[data-lp-carousel-slide]'));
        const dots = Array.from(root.querySelectorAll('[data-lp-carousel-dot]'));
        const prev = root.querySelector('[data-lp-carousel-prev]');
        const next = root.querySelector('[data-lp-carousel-next]');

        if (slides.length < 2) {
            return;
        }

        let activeIndex = 0;
        let timer = null;
        const reduceMotion = typeof window.matchMedia === 'function'
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function render(index) {
            activeIndex = (index + slides.length) % slides.length;

            slides.forEach(function (slide, slideIndex) {
                const isActive = slideIndex === activeIndex;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });

            dots.forEach(function (dot, dotIndex) {
                const isActive = dotIndex === activeIndex;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        }

        function stopAutoplay() {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        function startAutoplay() {
            if (reduceMotion) {
                return;
            }
            stopAutoplay();
            timer = window.setInterval(function () {
                render(activeIndex + 1);
            }, 6500);
        }

        function goTo(index) {
            render(index);
            startAutoplay();
        }

        if (prev) {
            prev.addEventListener('click', function () {
                goTo(activeIndex - 1);
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                goTo(activeIndex + 1);
            });
        }

        dots.forEach(function (dot, index) {
            dot.addEventListener('click', function () {
                goTo(index);
            });
        });

        root.addEventListener('mouseenter', stopAutoplay);
        root.addEventListener('mouseleave', startAutoplay);
        root.addEventListener('focusin', stopAutoplay);
        root.addEventListener('focusout', startAutoplay);

        render(0);
        startAutoplay();
    }

    document.addEventListener('DOMContentLoaded', function () {
        ensureHomepageCarousel();
        document.querySelectorAll('[data-lp-carousel]').forEach(initCarousel);
    });
})();
