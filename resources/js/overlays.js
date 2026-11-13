/** Shared accessible overlay ownership; popup modules only decide eligibility and triggers. */
export function installOverlays(root = document) {
    let active = null;
    let previousFocus = null;
    let previousOverflow = '';
    let activeAutomatic = false;
    let consentReady = !window.dbcmsConsent?.ready;
    const emit = (name, detail) => window.dispatchEvent(new CustomEvent(name, { detail }));
    const privacyOpen = () => [...root.querySelectorAll('[data-consent-endpoint] [role="dialog"], [data-cms-consent-banner], [data-consent-dialog], [data-privacy-dialog]')].some(element => !element.hidden && element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden');
    const controls = element => [...element.querySelectorAll('button:not(:disabled),a[href],input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex="0"]')].filter(item => !item.hidden && item.getClientRects().length);
    function close(id, restoreFocus = true) {
        if (!active || (id && active.dataset.overlayId !== String(id) && active.dataset.overlayAlias !== String(id))) return;
        const closed = active;
        active.hidden = true;
        active.removeAttribute('open');
        active = null;
        activeAutomatic = false;
        document.body.style.overflow = previousOverflow;
        if (restoreFocus) previousFocus?.focus?.();
        emit('cms-overlay-closed', { id: closed.dataset.overlayId });
        emit('cms-overlays-ready', {});
    }
    function open(detail) {
        const candidates = [...root.querySelectorAll('[data-cms-overlay]')];
        const scope = detail.trigger?.closest?.('[data-cms-type=container],[data-cms-type=component]');
        const element = candidates.find(item => item.dataset.overlayId === String(detail.id))
            || candidates.find(item => item.dataset.overlayAlias === String(detail.id) && scope?.contains(item))
            || candidates.find(item => item.dataset.overlayAlias === String(detail.id));
        if (!element || (detail.automatic && (!consentReady || privacyOpen())) || active) return;
        previousFocus = document.activeElement;
        previousOverflow = document.body.style.overflow;
        active = element;
        activeAutomatic = detail.automatic === true;
        element.hidden = false;
        element.setAttribute('open', '');
        const modal = element.dataset.overlayModal !== 'false';
        element.setAttribute('role', modal ? 'dialog' : 'region');
        if (modal) {
            element.setAttribute('aria-modal', 'true');
            document.body.style.overflow = 'hidden';
        }
        element.setAttribute('tabindex', '-1');
        (controls(element)[0] || element).focus();
        const { trigger, ...eventDetail } = detail;
        emit('cms-overlay-opened', { ...eventDetail, id: element.dataset.overlayId });
        emit('cms-content-mounted', { root: element });
    }
    root.addEventListener('click', event => {
        const trigger = event.target.closest?.('[data-cms-overlay-open]');
        if (trigger) { event.preventDefault(); open({ id: trigger.dataset.cmsOverlayOpen, trigger }); }
        const closeTrigger = event.target.closest?.('[data-cms-overlay-close]');
        if (closeTrigger) close(closeTrigger.dataset.cmsOverlayClose || undefined);
        if (active && event.target === active && active.dataset.overlayBackdropClose !== 'false') close();
    });
    root.addEventListener('keydown', event => {
        if (!active) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key === 'Tab' && active?.dataset.overlayModal !== 'false') {
            const items = controls(active);
            const first = items[0] || active, last = items.at(-1) || active;
            if (event.shiftKey && (document.activeElement === first || document.activeElement === active)) { event.preventDefault(); last.focus(); }
            if (!event.shiftKey && (document.activeElement === last || document.activeElement === active)) { event.preventDefault(); first.focus(); }
        }
    });
    window.addEventListener('cms-overlay-open', event => open(event.detail || {}));
    window.addEventListener('cms-overlay-close', event => close(event.detail?.id));
    window.addEventListener('cookie-consent-updated', () => emit('cms-overlays-ready', {}));
    let previousPrivacy = privacyOpen();
    new MutationObserver(() => {
        if (active && !active.isConnected) close();
        const privacy = privacyOpen();
        if (activeAutomatic && privacy) close(undefined, false);
        // Carousels, counters and editor animations must not wake every queued popup.
        if (previousPrivacy && !privacy && consentReady) emit('cms-overlays-ready', {});
        previousPrivacy = privacy;
    }).observe(root.documentElement || root, { attributes: true, attributeFilter: ['hidden', 'style'], subtree: true, childList: true });
    window.dbcmsStorage = {
        allows: purpose => Boolean(window.dbcmsConsent?.allows(purpose)),
        ready: Promise.resolve(window.dbcmsConsent?.ready).catch(() => null),
    };
    window.dbcmsStorage.ready.then(() => window.requestAnimationFrame(() => { consentReady = true; emit('cms-overlays-ready', {}); }));
    return { open, close };
}
