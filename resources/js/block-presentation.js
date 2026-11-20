const selector = '[data-scroll-magic],[data-scroll-scrub],[data-scroll-video]';
const effects = new Set(['fadeIn', 'fadeInUp', 'fadeInDown', 'fadeInLeft', 'fadeInRight', 'zoomIn', 'bounceIn', 'flipInX']);
const time = value => /^(?:\d+(?:\.\d+)?|\.\d+)(?:ms|s)$/.test(value || '') ? value : '0s';

/** One observer and lifecycle for authored layers, including dynamically mounted overlays. */
export function createPresentationRuntime(win = window, doc = document) {
    const states = new Map();
    const reduced = win.matchMedia?.('(prefers-reduced-motion: reduce)');
    let frame = null;
    const isReduced = () => reduced?.matches === true;
    const descendants = (root, query) => [...(root.querySelectorAll?.(query) || [])];
    const media = node => descendants(node, ':scope > [data-cms-presentation-media] [data-cms-presentation-motion]');
    const videos = node => media(node).filter(item => item.tagName === 'VIDEO');
    const authorized = video => {
        const gate = video.closest?.('[data-cms-consent]');
        return !gate || win.dbcmsConsent?.allows(gate.dataset.cmsConsent) === true;
    };
    const visible = state => state.visible && !doc.hidden && !state.node.hidden && !state.node.closest?.('[hidden]');
    function finishEntrance(state) {
        state.entrance?.classList.remove('cms-motion-running');
        for (const key of ['--cms-motion-name', '--cms-motion-delay', '--cms-motion-duration']) state.entrance?.style.removeProperty(key);
    }
    function stop(state) {
        for (const video of videos(state.node)) video.pause?.();
        for (const target of media(state.node)) target.style.removeProperty('--cms-scroll-transform');
        state.entrance?.style.removeProperty('--cms-scroll-transform');
        finishEntrance(state);
    }
    function reveal(state, replay = false) {
        const node = state.node;
        node.classList.add('is-revealed');
        if (isReduced() || !visible(state)) return;
        const effect = node.dataset.scrollEffect || node.dataset.scrollMagic;
        if (!state.entrance || !effects.has(effect) || (state.revealed && !replay)) return;
        state.revealed = true;
        const target = state.entrance;
        target.style.setProperty('--cms-motion-name', `cms-${effect}`);
        target.style.setProperty('--cms-motion-delay', time(node.dataset.scrollDelay));
        target.style.setProperty('--cms-motion-duration', time(node.dataset.scrollDuration || '1s'));
        target.classList.remove('cms-motion-running');
        // A single restart on explicit replay; ordinary scroll never replays entrance motion.
        if (replay) void target.offsetWidth;
        target.classList.add('cms-motion-running');
    }
    function update(state, replay = false) {
        if (isReduced()) { stop(state); state.node.classList.add('is-revealed'); return; }
        if (!visible(state)) { for (const video of videos(state.node)) video.pause?.(); return; }
        reveal(state, replay);
        const node = state.node;
        const mode = node.dataset.scrollScrub;
        const owned = media(node);
        const targets = owned.length ? owned : (state.entrance ? [state.entrance] : []);
        let progress = 0;
        if (mode && mode !== 'none') {
            const rect = node.getBoundingClientRect();
            const height = win.innerHeight || doc.documentElement.clientHeight || 1;
            progress = Math.max(0, Math.min(1, (height - rect.top) / Math.max(1, height + rect.height)));
        }
        for (const target of targets) {
            if (mode === 'rotate') target.style.setProperty('--cms-scroll-transform', `rotate(${((progress - .5) * 360).toFixed(2)}deg) scale(1.2)`);
            else if (mode === 'parallax') target.style.setProperty('--cms-scroll-transform', `translate3d(0,${((progress - .5) * 80).toFixed(2)}px,0) scale(1.08)`);
            else target.style.removeProperty('--cms-scroll-transform');
        }
        for (const video of videos(node)) {
            if (!authorized(video)) { video.pause?.(); continue; }
            if (mode === 'playback') {
                video.pause?.();
                if (Number.isFinite(video.duration) && video.duration > 0) video.currentTime = progress * video.duration;
            } else {
                try { video.play?.()?.catch?.(() => {}); } catch { /* Browser autoplay remains optional. */ }
            }
        }
    }
    const observer = win.IntersectionObserver ? new win.IntersectionObserver(entries => {
        for (const entry of entries) {
            const state = states.get(entry.target);
            if (state) { state.visible = entry.isIntersecting; update(state); }
        }
    }, { threshold: .01 }) : null;
    function initialize(root = doc) {
        const nodes = [...(root.matches?.(selector) ? [root] : []), ...descendants(root, selector)];
        for (const node of nodes) {
            if (states.has(node)) continue;
            const rect = node.getBoundingClientRect();
            const state = { node, entrance: node.querySelector?.(':scope > [data-cms-presentation-entrance]'), visible: !observer && rect.bottom > 0 && rect.top < win.innerHeight, revealed: false };
            state.finish = event => { if (event.target === state.entrance) finishEntrance(state); };
            state.entrance?.addEventListener('animationend', state.finish);
            states.set(node, state);
            observer?.observe(node);
            update(state);
        }
        // Consent insertion can add media to an already observed surface.
        const owner = root.closest?.('[data-cms-presentation]');
        if (owner && states.has(owner)) update(states.get(owner));
    }
    function cleanup(root = doc) {
        if (root.tagName === 'VIDEO' && root.dataset?.cmsPresentationMotion !== undefined) root.pause?.();
        for (const video of descendants(root, '[data-cms-presentation-video]')) video.pause?.();
        for (const [node, state] of states) {
            if (root === doc || root === node || root.contains?.(node)) {
                stop(state); state.entrance?.removeEventListener('animationend', state.finish); observer?.unobserve(node); states.delete(node);
            }
        }
    }
    function refresh() {
        frame = null;
        for (const [node, state] of states) {
            if (node.isConnected === false) { stop(state); state.entrance?.removeEventListener('animationend', state.finish); observer?.unobserve(node); states.delete(node); }
            else {
                if (!observer) {
                    const rect = node.getBoundingClientRect();
                    state.visible = rect.bottom > 0 && rect.top < win.innerHeight;
                }
                if (!observer || (node.dataset.scrollScrub && node.dataset.scrollScrub !== 'none')) update(state);
            }
        }
    }
    const schedule = () => { if (frame === null) frame = win.requestAnimationFrame(refresh); };
    const mounted = event => initialize(event.detail?.root || event.target || doc);
    const replay = () => { for (const state of states.values()) update(state, true); };
    const message = event => {
        const origin = new URL(doc.baseURI || win.location.href).origin;
        if (doc.documentElement.hasAttribute('data-cms-preview') && event.source === win.parent && event.origin === origin
            && event.data?.source === 'cms-block-preview' && event.data?.type === 'replay') replay();
    };
    const changed = () => { for (const state of states.values()) update(state); };
    win.addEventListener('scroll', schedule, { passive: true });
    win.addEventListener('resize', schedule, { passive: true });
    win.addEventListener('message', message);
    win.addEventListener('cookie-consent-updated', changed);
    doc.addEventListener('visibilitychange', changed);
    doc.addEventListener('cms-content-mounted', mounted);
    win.addEventListener('cms-content-mounted', mounted);
    reduced?.addEventListener?.('change', changed);
    const mutations = win.MutationObserver ? new win.MutationObserver(records => {
        for (const record of records) {
            for (const node of record.removedNodes || []) cleanup(node);
            for (const node of record.addedNodes || []) initialize(node);
        }
    }) : null;
    mutations?.observe(doc.documentElement, { subtree: true, childList: true });
    initialize();
    return { initialize, cleanup, replay, destroy() {
        cleanup(); observer?.disconnect(); mutations?.disconnect();
        if (frame !== null) win.cancelAnimationFrame?.(frame);
        win.removeEventListener('scroll', schedule); win.removeEventListener('resize', schedule); win.removeEventListener('message', message);
        win.removeEventListener('cookie-consent-updated', changed); doc.removeEventListener('visibilitychange', changed); doc.removeEventListener('cms-content-mounted', mounted); win.removeEventListener('cms-content-mounted', mounted);
        reduced?.removeEventListener?.('change', changed);
    } };
}

let installed;
export function installPresentation() {
    installed ??= createPresentationRuntime();
    return installed;
}
