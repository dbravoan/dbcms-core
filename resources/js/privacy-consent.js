export function createConsentClient({ endpoint, fetcher = fetch, dispatch = detail => window.dispatchEvent(new CustomEvent('cookie-consent-updated', { detail })), onChange }) {
    let state = { configured: false, preferences: { necessary: true }, revision: '', csrf: '' };
    async function receive(response, notify = true) {
        if (!response.ok) throw new Error('No se han podido guardar las preferencias. Inténtalo de nuevo.');
        const value = await response.json();
        state = { ...state, ...value, preferences: { ...value.preferences, necessary: true } };
        if (notify) dispatch(state.preferences);
        return state;
    }
    const ready = fetcher(endpoint, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } }).then(receive);
    return {
        ready,
        allows: category => state.preferences[category] === true,
        state: () => state,
        async save(preferences) {
            await ready;
            const previous = state.preferences;
            const saved = await receive(await fetcher(endpoint, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': state.csrf }, body: JSON.stringify({ revision: state.revision, preferences }) }), false);
            const changed = Object.keys(saved.preferences).some(key => key !== 'necessary' && previous[key] !== saved.preferences[key]);
            const revoked = Object.keys(previous).some(key => key !== 'necessary' && previous[key] === true && saved.preferences[key] !== true);
            // Revoke immediately. Grants wait for the new document's matching CSP.
            if (!changed || revoked || !onChange) dispatch(saved.preferences);
            if (changed) onChange?.(revoked);
            return saved;
        },
    };
}

export function refreshConsentContent(root = document) {
    for (const element of root.querySelectorAll('[data-cms-consent]')) {
        const allowed = window.dbcmsConsent?.allows(element.dataset.cmsConsent) === true;
        const template = element.querySelector(':scope > template');
        const placeholder = element.querySelector(':scope > [data-consent-placeholder]');
        let content = element.querySelector(':scope > [data-consent-content]');
        if (allowed && !content && template) {
            content = document.createElement('div');
            content.dataset.consentContent = '';
            const fragment = template.content.cloneNode(true);
            // Recreate scripts deliberately: inert templates never request third parties.
            for (const inert of fragment.querySelectorAll('script')) {
                const active = document.createElement('script');
                for (const attribute of inert.attributes) active.setAttribute(attribute.name, attribute.value);
                if (inert.nonce) active.nonce = inert.nonce;
                active.textContent = inert.textContent;
                inert.replaceWith(active);
            }
            content.append(fragment);
            element.append(content);
        }
        if (!allowed) content?.remove();
        if (placeholder) placeholder.hidden = allowed;
    }
}

export function installConsent() {
    const node = document.querySelector('[data-consent-endpoint]');
    if (!node) return;
    window.dbcmsConsent = createConsentClient({ endpoint: node.dataset.consentEndpoint, onChange(revoked) {
        if (revoked) {
            for (const item of document.cookie.split(';')) {
                const name = item.split('=')[0].trim();
                if (!/^(_ga|_gid|_gat|_gcl_|__gads|__gpi|_fbp|_fbc)/.test(name)) continue;
                for (const domain of ['', ...location.hostname.split('.').map((_, index, parts) => parts.slice(index).join('.'))]) {
                    document.cookie = `${name}=; Max-Age=0; path=/${domain ? '; domain=' + domain : ''}; SameSite=Lax`;
                }
            }
        }
        // Reload applies the saved media CSP and stops scripts from the old decision.
        location.reload();
    } });
    window.addEventListener('cookie-consent-updated', () => refreshConsentContent());
    window.dbcmsConsent.ready.catch(() => refreshConsentContent());
}

export function createConsentPanel() {
    return {
        showBanner: false, showSettings: false, busy: false, error: '', preferences: { necessary: true },
        async init() {
            window.addEventListener('open-cookie-settings', () => this.openSettings());
            try {
                const state = await window.dbcmsConsent.ready;
                this.preferences = { ...state.preferences };
                this.showBanner = !state.configured && Object.keys(state.preferences).some(key => key !== 'necessary');
            } catch { this.showBanner = true; this.error = 'No se pueden cargar las preferencias. El contenido opcional permanece bloqueado.'; }
        },
        openSettings() {
            this.previousFocus = document.activeElement;
            this.showSettings = true;
            this.$nextTick(() => this.$refs.dialog.querySelector('button, input:not(:disabled)')?.focus());
        },
        closeSettings() { this.showSettings = false; this.previousFocus?.focus(); },
        trapFocus(event) {
            const controls = [...this.$refs.dialog.querySelectorAll('button:not(:disabled), input:not(:disabled), a[href]')];
            const first = controls[0], last = controls.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        },
        async save(preferences) {
            this.busy = true; this.error = '';
            try {
                const state = await window.dbcmsConsent.save(preferences);
                this.preferences = { ...state.preferences };
                this.showBanner = false; this.closeSettings();
            } catch (error) { this.error = error.message; }
            finally { this.busy = false; }
        },
        all(value) { return Object.fromEntries(Object.keys(this.preferences).map(key => [key, key === 'necessary' || value])); },
        acceptAll() { return this.save(this.all(true)); },
        acceptNecessary() { return this.save(this.all(false)); },
        saveCustom() { return this.save(this.preferences); },
    };
}
