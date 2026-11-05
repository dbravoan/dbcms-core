/**
 * dbCMS front-office bundle.
 *
 * Alpine is bundled locally (no CDN) and registered globally so the existing
 * `x-data`/`x-show` markup keeps working unchanged.
 */
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { createBlockManager } from './block-manager';
import { createGalleryEditor } from './gallery-editor';
import { createVisitorGeoSelector } from './visitor-geo';
import { installConsent, createConsentPanel } from './privacy-consent';

window.Alpine = Alpine;

// The front themes use `x-collapse` for their own collapsible blocks, so the
// plugin is bundled locally (no CDN) and registered before boot.
Alpine.plugin(collapse);
Alpine.data('blockManager', createBlockManager);
Alpine.data('galleryEditor', createGalleryEditor);
Alpine.data('visitorGeoSelector', createVisitorGeoSelector);
installConsent();
Alpine.data('cookieConsent', createConsentPanel);

import registerModules from 'virtual:dbcms-modules';
registerModules(Alpine);

/**
 * Shared navigation state.
 *
 * The toggle button and the collapsible panel live in different places of the
 * header (and, for some themes, outside the inner flex row), so a global store
 * is used instead of a per-component `x-data`.
 */
Alpine.store('nav', { open: false });

// Close the front navigation when clicking outside any nav component.
document.addEventListener(
    'click',
    (event) => {
        const store = window.Alpine?.store?.('nav');

        if (!store?.open) {
            return;
        }

        if (!(event.target instanceof Element) || !event.target.closest('[data-front-nav]')) {
            store.open = false;
        }
    },
    true,
);

// Close on route-level navigation and on resize to desktop.
window.addEventListener('resize', () => {
    const store = window.Alpine?.store?.('nav');

    if (store?.open && window.innerWidth >= 768) {
        store.open = false;
    }
});

// Filter panels: expanded by default (so they work without JavaScript) and
// folded on small screens, where the summary row takes over.
document.querySelectorAll('details[data-filter-collapsible]').forEach((panel) => {
    if (window.matchMedia('(max-width: 767px)').matches) {
        panel.removeAttribute('open');
    }
});

Alpine.start();

/**
 * CSP-safe progressive enhancement.
 *
 * The site's Content-Security-Policy forbids inline event handlers
 * (`onchange="…"`, `onclick="…"`), so every interactive affordance is wired
 * here through data attributes instead:
 *
 *   [data-auto-submit]   → submits its form as soon as it changes
 *   [data-dispatch]="x"  → dispatches a bubbling CustomEvent named x
 *   [data-dismiss]       → removes the closest [data-dismissable] element
 */
document.addEventListener('change', (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    const control = target.closest('[data-auto-submit]');

    if (!control || !(control instanceof HTMLElement)) {
        return;
    }

    const form = control.closest('form');

    if (!form) {
        return;
    }

    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
    } else {
        form.submit();
    }
});

document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    const dispatcher = target.closest('[data-dispatch]');

    if (dispatcher instanceof HTMLElement) {
        window.dispatchEvent(new CustomEvent(dispatcher.dataset.dispatch ?? ''));
    }

    const dismiss = target.closest('[data-dismiss]');

    if (dismiss instanceof HTMLElement) {
        dismiss.closest('[data-dismissable]')?.remove();
    }
});

// Filter panels and other canonicalizing forms announce they are working.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    if (form.matches('[data-filter-form]')) {
        form.setAttribute('aria-busy', 'true');
    }
});
