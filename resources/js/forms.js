export default function registerForms() {
    if (window.dbCmsForms) return;
    const preview = () => document.documentElement?.hasAttribute('data-cms-preview') === true;
    const all = (root, selector) => Array.from(root.querySelectorAll(selector));
    const steps = form => all(form, '[data-form-step]');
    const showStep = (form, index, focus = false) => {
        const pages = steps(form);
        if (!pages.length) return;
        index = Math.max(0, Math.min(index, pages.length - 1));
        form.dataset.step = String(index);
        pages.forEach((page, i) => { page.hidden = i !== index; });
        const nav = form.querySelector('[data-form-navigation]');
        if (nav) nav.hidden = pages.length < 2;
        const previous = form.querySelector('[data-form-previous]');
        const next = form.querySelector('[data-form-next]');
        if (previous) previous.hidden = index === 0;
        if (next) next.hidden = index === pages.length - 1;
        const submit = form.querySelector('[data-form-submit]');
        if (submit) submit.hidden = index !== pages.length - 1;
        const progress = form.querySelector('[data-form-progress]');
        if (progress) progress.textContent = `${index + 1} / ${pages.length}`;
        if (focus) pages[index].querySelector('input:not([type="hidden"]),select,textarea')?.focus();
    };
    const validPage = page => {
        for (const control of all(page, 'input,select,textarea')) {
            if (!control.checkValidity()) { control.reportValidity(); return false; }
        }
        return true;
    };
    const navigate = (form, direction) => {
        const pages = steps(form), index = Number(form.dataset.step || 0);
        if (direction > 0 && pages[index] && !validPage(pages[index])) return false;
        showStep(form, index + direction, true);
        return true;
    };
    const complete = (detail, actions = []) => {
        if (preview()) return;
        window.dispatchEvent(new CustomEvent('cms-form-submitted', { detail }));
        for (const action of actions) {
            if (action.type === 'open_popup' || action.type === 'close_popup') {
                window.dispatchEvent(new CustomEvent(action.type === 'open_popup' ? 'cms-overlay-open' : 'cms-overlay-close', { detail: { id: action.target } }));
            } else if (action.type === 'redirect' && /^\/(?!\/)/.test(action.target || '') && !/[\\\x00-\x20%]/.test(action.target)) {
                window.location.assign(action.target);
            }
        }
    };
    const submit = async form => {
        if (preview()) return;
        if (form.dataset.submitting === 'true') return;
        const pages = steps(form);
        if (!pages.length && !validPage(form)) return;
        for (let index = 0; index < pages.length; index++) {
            const invalid = all(pages[index], 'input,select,textarea').find(control => !control.checkValidity());
            if (invalid) { showStep(form, index); invalid.reportValidity(); return; }
        }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        const status = form.querySelector('[data-form-status]');
        const button = form.querySelector('[type="submit"]');
        if (button) button.disabled = true;
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const result = await response.json();
            if (!response.ok || !result.success) {
                const errors = result.errors || {};
                const keys = Object.keys(errors);
                const pageIndex = pages.findIndex(page => all(page, '[name]').some(control => keys.includes(control.name)));
                if (pageIndex >= 0) showStep(form, pageIndex);
                if (status) { status.textContent = Object.values(errors).flat().join(' ') || result.message || 'No se pudo enviar el formulario.'; status.focus(); }
                return;
            }
            if (status) { status.textContent = result.message || ''; status.focus(); }
            complete({ id: Number(form.dataset.formId), slug: form.dataset.cmsForm, submission_id: result.submission_id }, result.actions || []);
        } catch (_) {
            if (status) { status.textContent = 'No se pudo enviar el formulario. Inténtalo de nuevo.'; status.focus(); }
        } finally {
            form.dataset.submitting = 'false';
            form.removeAttribute('aria-busy');
            if (button) button.disabled = false;
        }
    };
    const initialize = root => {
        if (preview()) return;
        all(root, 'form[data-cms-form]').forEach(form => {
            if (form.dataset.initialized) return;
            form.dataset.initialized = 'true';
            form.noValidate = true;
            if (form.dataset.formResult) {
                const result = JSON.parse(form.dataset.formResult);
                complete(result.detail, result.actions);
            }
            showStep(form, 0);
            form.addEventListener('submit', event => { if (event.defaultPrevented || preview()) return; event.preventDefault(); submit(form); });
            form.querySelector('[data-form-next]')?.addEventListener('click', () => navigate(form, 1));
            form.querySelector('[data-form-previous]')?.addEventListener('click', () => navigate(form, -1));
        });
    };
    window.dbCmsForms = { initialize, navigate, submit, complete };
    initialize(document);
    document.addEventListener('DOMContentLoaded', () => initialize(document));
    // The same component can be mounted later by an editor or overlay.
    document.addEventListener('cms-content-mounted', () => initialize(document));
}
