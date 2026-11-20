/** One focus owner for all admin dialogs, including sibling and nested dialogs. */
const managers = new WeakMap();

export function createAdminDialogManager(browser = window) {
    const document = browser.document;
    const entries = [];
    let previousOverflow = '';
    const top = () => entries.reduce((current, entry) => {
        const priority = Number.parseInt(browser.getComputedStyle(entry.root).zIndex, 10) || 0;
        const currentPriority = current ? Number.parseInt(browser.getComputedStyle(current.root).zIndex, 10) || 0 : -Infinity;
        return priority >= currentPriority ? entry : current;
    }, null);
    const controls = entry => [...entry.panel.querySelectorAll('a[href],button,input,select,textarea,summary,iframe,audio[controls],video[controls],[contenteditable],[tabindex]')]
        .filter(element => !element.disabled && !element.matches?.(':disabled')
            && (element.tabIndex >= 0 || (element.isContentEditable && !element.hasAttribute('tabindex')))
            && element.getClientRects().length && !element.closest('[inert]'));
    const focus = entry => {
        const initial = entry.panel.querySelector('[data-admin-dialog-initial-focus], [autofocus]');
        ((initial && controls(entry).includes(initial) ? initial : controls(entry)[0]) || entry.panel).focus({ preventScroll: true });
    };
    const keydown = event => {
        const entry = top();
        if (!entry) return;
        if (event.key === 'Escape') {
            // Consume once, before Alpine's window listeners can close a lower dialog.
            event.preventDefault();
            event.stopImmediatePropagation();
            if (entry.closable) entry.close();
        }
        if (event.key === 'Tab') {
            const items = controls(entry);
            const index = items.indexOf(document.activeElement);
            if (index < 0 || (!event.shiftKey && index === items.length - 1) || (event.shiftKey && index === 0)) {
                event.preventDefault();
                (event.shiftKey ? items.at(-1) || entry.panel : items[0] || entry.panel).focus({ preventScroll: true });
            }
        }
    };
    const focusin = event => {
        const entry = top();
        if (entry && !entry.panel.contains(event.target)) focus(entry);
    };
    const message = event => {
        const entry = top();
        if (!entry?.closable || !event.source || event.data?.source !== 'cms-preview' || event.data.type !== 'escape') return;
        if (event.origin !== 'null' && event.origin !== browser.location.origin) return;
        const ownedFrame = [...entry.panel.querySelectorAll('iframe[data-cms-preview-frame]')]
            .some(frame => frame.contentWindow === event.source && frame.getClientRects().length);
        if (ownedFrame) entry.close();
    };
    return {
        open(root, panel, close, closable = true) {
            const entry = { root, panel, close, closable, previousFocus: document.activeElement };
            if (!entries.length) {
                previousOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
                browser.addEventListener('keydown', keydown, true);
                browser.addEventListener('focusin', focusin, true);
                browser.addEventListener('message', message);
            }
            entries.push(entry);
            return entry;
        },
        focus(entry) { if (top() === entry) focus(entry); },
        close(entry) {
            const index = entries.indexOf(entry);
            if (index < 0) return;
            const wasTop = top() === entry;
            entries.splice(index, 1);
            // A parent removed while a child is open must not become its return target.
            entries.forEach(item => {
                if (entry.root.contains(item.previousFocus)) item.previousFocus = entry.previousFocus;
            });
            const current = top();
            if (!entries.length) {
                document.body.style.overflow = previousOverflow;
                browser.removeEventListener('keydown', keydown, true);
                browser.removeEventListener('focusin', focusin, true);
                browser.removeEventListener('message', message);
            }
            if (!wasTop) return;
            const previous = entry.previousFocus;
            if (previous?.isConnected && (!current || current.panel.contains(previous))) previous.focus({ preventScroll: true });
            else if (current) focus(current);
        },
        requestClose(entry) { if (entry?.closable && top() === entry) entry.close(); },
    };
}

export function createAdminDialog(options = {}, browser = window) {
    if (!managers.has(browser)) managers.set(browser, createAdminDialogManager(browser));
    const manager = managers.get(browser);
    let entry = null;
    return {
        isMaximized: false,
        syncDialog(open) {
            if (Boolean(open) === Boolean(entry)) return;
            if (!open) {
                manager.close(entry);
                entry = null;
                this.isMaximized = false;
                return;
            }
            entry = manager.open(this.$el, this.$el.querySelector('[data-admin-dialog-panel]'), () => {
                this.$el.dispatchEvent(new browser.CustomEvent('admin-dialog-request-close', { bubbles: true }));
            }, options.closable !== false);
            this.$nextTick(() => { if (entry) manager.focus(entry); });
        },
        requestClose() { manager.requestClose(entry); },
        destroy() { if (entry) manager.close(entry); entry = null; },
    };
}
