/**
 * dbCMS front-office bundle.
 *
 * Alpine is bundled locally (no CDN) and registered globally so the existing
 * `x-data`/`x-show` markup keeps working unchanged.
 */
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { createGalleryEditor } from './gallery-editor';
import { createVisitorGeoSelector } from './visitor-geo';
import { installConsent, createConsentPanel } from './privacy-consent';
import { installOverlays } from './overlays';
import registerBlocks from 'virtual:dbcms-blocks';

window.Alpine = Alpine;
if (document.documentElement.hasAttribute('data-cms-preview')) {
    document.addEventListener('submit', event => event.preventDefault(), true);
    document.addEventListener('click', event => {
        if (event.target.closest?.('a[href]')) event.preventDefault();
    }, true);
}

// The front themes use `x-collapse` for their own collapsible blocks, so the
// plugin is bundled locally (no CDN) and registered before boot.
Alpine.plugin(collapse);
Alpine.data('galleryEditor', createGalleryEditor);
Alpine.data('visitorGeoSelector', createVisitorGeoSelector);
installConsent();
Alpine.data('cookieConsent', createConsentPanel);

import registerModules from 'virtual:dbcms-modules';
registerModules(Alpine);
installOverlays();
registerBlocks(Alpine);

let presentationLoading;
const initializePresentation = () => {
    if (!presentationLoading && document.querySelector('[data-scroll-magic],[data-scroll-scrub],[data-scroll-video]')) {
        presentationLoading = import('./block-presentation').then(({ installPresentation }) => {
            const runtime = installPresentation();
            presentationObserver.disconnect();
            return runtime;
        });
        presentationLoading.catch(error => { presentationLoading = null; console.error('No se pudo iniciar la presentación CMS.', error); });
    }
};
const presentationObserver = new MutationObserver(initializePresentation);
presentationObserver.observe(document.documentElement, { childList: true, subtree: true });
initializePresentation();
if (document.documentElement.hasAttribute('data-cms-preview')) {
    import('./block-preview').then(({ installBlockPreview }) => installBlockPreview()).catch(error => console.error('No se pudo iniciar la previsualización CMS.', error));
}

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

async function startApplication() {
    if (document.querySelector('[data-install-wizard]')) {
        const { createInstallationWizard } = await import('./install-wizard');
        Alpine.data('installationWizard', createInstallationWizard);
    }
    // Public pages never download the authoring tree, history or rich-text editor.
    // Register its Alpine provider before evaluating an actual block manager.
    if (document.querySelector('.admin-block-manager')) {
        const { createBlockManager } = await import('./block-manager');
        Alpine.data('blockManager', createBlockManager);
    }
    Alpine.start();
}
startApplication().catch(error => console.error('No se pudo iniciar la interfaz CMS.', error));

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
