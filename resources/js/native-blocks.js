// The package owns behavior; shared core rendering owns appearance and overlays.
const selector = '[data-cms-accordion],[data-cms-tabs],[data-cms-carousel],[data-cms-counter],[data-cms-countdown],[data-cms-alert],[data-cms-lottie],[data-cms-playlist],[data-cms-hotspots],[data-cms-reading-progress]';
const instances = new WeakMap();
let registered = false;

export function clamp(value, minimum, maximum, fallback = minimum) {
    const number = Number(value);
    return Number.isFinite(number) ? Math.max(minimum, Math.min(maximum, number)) : fallback;
}

export function counterValue(start, end, progress) {
    const fraction = clamp(progress, 0, 1);
    return Number(start) + (Number(end) - Number(start)) * (1 - (1 - fraction) ** 3);
}

export function countdownDeadline({ mode, deadline, duration }, now = Date.now()) {
    if (mode === 'visit') return now + clamp(duration, 1, 31536000, 900) * 1000;
    // Never guess a browser timezone for editor-entered dates.
    const parts = /^(\d{4})-(\d\d)-(\d\d)T(\d\d):(\d\d)(?::(\d\d))?(Z|[+-]\d\d:\d\d)$/.exec(deadline ?? '');
    if (!parts) return null;
    const [, year, month, day, hours, minutes, seconds = '0'] = parts;
    const calendar = new Date(0); calendar.setUTCFullYear(Number(year), Number(month) - 1, Number(day));
    if (calendar.getUTCFullYear() !== Number(year) || calendar.getUTCMonth() + 1 !== Number(month) || calendar.getUTCDate() !== Number(day) || Number(hours) > 23 || Number(minutes) > 59 || Number(seconds) > 59) return null;
    const parsed = Date.parse(deadline);
    return Number.isFinite(parsed) ? parsed : null;
}

export function remainingTime(deadline, now = Date.now()) {
    const total = Math.max(0, Math.ceil((deadline - now) / 1000));
    return { total, days: Math.floor(total / 86400), hours: Math.floor(total / 3600) % 24, minutes: Math.floor(total / 60) % 60, seconds: total % 60 };
}

export function carouselBreakpoints(data = {}) {
    return {
        slidesPerView: clamp(data.slidesPerViewMobile, 1, 6, 1),
        slidesPerGroup: clamp(data.slidesPerGroupMobile, 1, 6, 1),
        breakpoints: {
            768: { slidesPerView: clamp(data.slidesPerViewTablet, 1, 6, 1), slidesPerGroup: clamp(data.slidesPerGroupTablet, 1, 6, 1) },
            1024: { slidesPerView: clamp(data.slidesPerView, 1, 6, 1), slidesPerGroup: clamp(data.slidesPerGroup, 1, 6, 1) },
        },
    };
}

export function countdownExpiry(data = {}, { preview = false, base = 'https://localhost/' } = {}) {
    if (data.expiredAction === 'hide') return { action: 'hide', url: null };
    if (data.expiredAction === 'redirect' && !preview && data.redirectUrl && !data.redirectUrl.startsWith('//')) {
        try {
            const url = new URL(data.redirectUrl, base);
            if (['http:', 'https:'].includes(url.protocol) && !url.username && !url.password) return { action: 'redirect', url: url.href };
        } catch { /* An invalid stored destination falls back to the final message. */ }
    }
    return { action: 'message', url: null };
}

export function visitCountdownState({ data, bridge, storage, key }, now = Date.now()) {
    if (data.persistence === 'memory' || !data.persistence) return { status: 'ready', deadline: countdownDeadline({ ...data, mode: 'visit' }, now) };
    if (data.storageEnabled !== 'true' || !['session', 'local'].includes(data.persistence) || !key || !data.instanceId || !data.definitionRevision) return { status: 'unavailable', deadline: null };
    if (!['preferences', 'analytics', 'marketing', 'external'].includes(data.storagePurpose) || bridge?.allows?.(data.storagePurpose) !== true) return { status: 'denied', deadline: null };
    try {
        // Even obtaining the Storage object is deferred until the shared bridge grants it.
        const destination = storage(data.persistence);
        const maximumExpiry = now + clamp(data.storageDays, 1, 365, 30) * 86400000;
        const instancePrefix = key.slice(0, key.lastIndexOf('.') + 1);
        if (typeof destination.key === 'function') {
            for (let index = destination.length - 1; index >= 0; index--) {
                const candidate = destination.key(index);
                if (!candidate || candidate === key || !candidate.startsWith('dbcms.countdown.v1.')) continue;
                let expired = true;
                try { const entry = JSON.parse(destination.getItem(candidate)); expired = !Number.isFinite(entry.expiresAt) || entry.expiresAt <= now; } catch { /* Invalid optional entries are discarded after authorization. */ }
                if (expired || candidate.startsWith(instancePrefix)) destination.removeItem(candidate);
            }
        }
        const previous = destination.getItem(key);
        if (previous) {
            try {
                const value = JSON.parse(previous);
                if (Number.isFinite(value.deadline) && Number.isFinite(value.expiresAt) && value.expiresAt > now && value.expiresAt <= maximumExpiry && value.revision === data.definitionRevision) return { status: 'ready', deadline: value.deadline };
            } catch { /* Replace invalid data only after authorization. */ }
            destination.removeItem(key);
        }
        const deadline = countdownDeadline({ ...data, mode: 'visit' }, now);
        destination.setItem(key, JSON.stringify({ deadline, expiresAt: maximumExpiry, revision: data.definitionRevision }));
        return { status: 'ready', deadline };
    } catch { return { status: 'unavailable', deadline: null }; }
}

export function validAnimationData(data) {
    if (!data || typeof data !== 'object' || !Array.isArray(data.layers) || !Number.isFinite(data.fr)) return false;
    // The light player has no expressions engine. Refuse all linked assets/fonts
    // as well, so even an imported library file cannot contact another origin.
    const pending = [data];
    let count = 0;
    while (pending.length) {
        const node = pending.pop();
        if (++count > 100000) return false;
        if (!node || typeof node !== 'object') continue;
        if (typeof node.p === 'string' && !/^data:image\/(?:png|jpeg|webp);base64,[A-Za-z0-9+/=]+$/.test(String(node.u || '') + node.p)) return false;
        for (const [key, value] of Object.entries(node)) {
            if (['__proto__', 'constructor', 'prototype'].includes(key)) return false;
            if ((key === 'x' && typeof value === 'string') || (['u', 'fPath', 'url', 'href', 'src'].includes(key) && typeof value === 'string' && value !== '')) return false;
            if (value && typeof value === 'object') pending.push(value);
        }
    }
    return true;
}

function reducedMotion() {
    return globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

function matches(root) {
    return [...(root.matches?.(selector) ? [root] : []), ...(root.querySelectorAll?.(selector) ?? [])];
}

function visible(element, callback, cleanups) {
    if (!globalThis.IntersectionObserver) { callback(); return; }
    const observer = new IntersectionObserver(entries => {
        if (entries.some(entry => entry.isIntersecting)) {
            observer.disconnect();
            callback();
        }
    }, { rootMargin: '100px' });
    observer.observe(element);
    cleanups.push(() => observer.disconnect());
}

function listen(element, name, callback, cleanups, options) {
    element.addEventListener(name, callback, options);
    cleanups.push(() => element.removeEventListener(name, callback, options));
}

function accordion(element, cleanups) {
    const panels = [...element.children].filter(node => node.tagName === 'DETAILS');
    const summaries = panels.map(panel => panel.querySelector('summary'));
    for (const panel of panels) { const icon = panel.querySelector('[data-accordion-icon]'); const update = () => { if (icon) icon.textContent = element.dataset.iconStyle === 'chevron' ? (panel.open ? '⌄' : '›') : (panel.open ? '−' : '+'); }; update(); listen(panel, 'toggle', update, cleanups); }
    if (element.dataset.multiple !== 'true') {
        let foundOpen = false;
        for (const panel of panels) {
            if (panel.open && foundOpen) panel.open = false;
            if (panel.open) foundOpen = true;
            listen(panel, 'toggle', () => {
                if (panel.open) for (const other of panels) if (other !== panel) other.open = false;
            }, cleanups);
        }
    }
    summaries.forEach((summary, index) => listen(summary, 'keydown', event => {
        let next;
        if (event.key === 'ArrowDown') next = (index + 1) % summaries.length;
        if (event.key === 'ArrowUp') next = (index - 1 + summaries.length) % summaries.length;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = summaries.length - 1;
        if (next !== undefined) { event.preventDefault(); summaries[next]?.focus(); }
    }, cleanups));
}

function tabs(element, cleanups) {
    const list = element.querySelector(':scope > [data-tab-list]');
    if (!list) return;
    const buttons = [...list.querySelectorAll('[role="tab"]')];
    const panels = [...element.querySelectorAll(':scope > [data-tab-panel]')];
    const headings = [...element.querySelectorAll(':scope > [data-tab-panel] > [data-tab-fallback-title]')];
    const contents = panels.map(panel => panel.querySelector('[data-tab-content]') || panel);
    const triggers = headings.map(heading => heading.querySelector('[data-tab-accordion-trigger]'));
    if (!buttons.length || buttons.length !== panels.length) return;
    let selected = clamp(element.dataset.initialIndex, 0, buttons.length - 1, 0);
    const media = element.dataset.responsiveMode === 'accordion' ? globalThis.matchMedia?.(`(max-width: ${clamp(element.dataset.breakpoint, 320, 1200, 767)}px)`) : null;
    const mobile = () => media?.matches === true && contents.every((content, index) => content !== panels[index]);
    const render = () => {
        if (!mobile() && selected < 0) selected = 0;
        element.classList.toggle('is-accordion', mobile()); list.hidden = mobile();
        buttons.forEach((button, index) => {
            const active = index === selected;
            button.setAttribute('aria-selected', String(active)); button.tabIndex = active ? 0 : -1;
            panels[index].hidden = !mobile() && !active;
            panels[index].setAttribute('role', mobile() ? 'region' : 'tabpanel');
            panels[index].setAttribute('aria-labelledby', mobile() ? triggers[index]?.id || button.id : button.id);
            if (mobile()) panels[index].removeAttribute('tabindex'); else panels[index].tabIndex = 0;
            if (contents[index] !== panels[index]) contents[index].hidden = mobile() && !active;
            if (headings[index]) { headings[index].hidden = !mobile(); const text = headings[index].querySelector('[data-tab-static-title]'); if (text) text.hidden = mobile(); }
            if (triggers[index]) { triggers[index].hidden = !mobile(); triggers[index].setAttribute('aria-expanded', String(active)); }
        });
    };
    const navigate = (event, index, controls, vertical) => {
        let next;
        if (event.key === (vertical ? 'ArrowDown' : 'ArrowRight')) next = (index + 1) % controls.length;
        if (event.key === (vertical ? 'ArrowUp' : 'ArrowLeft')) next = (index - 1 + controls.length) % controls.length;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = controls.length - 1;
        if (next !== undefined) { event.preventDefault(); selected = next; render(); controls[next]?.focus(); }
    };
    buttons.forEach((button, index) => {
        listen(button, 'click', () => { selected = index; render(); }, cleanups);
        listen(button, 'keydown', event => navigate(event, index, buttons, element.dataset.orientation === 'vertical'), cleanups);
        if (triggers[index]) {
            listen(triggers[index], 'click', () => { selected = selected === index ? -1 : index; render(); }, cleanups);
            listen(triggers[index], 'keydown', event => navigate(event, index, triggers, true), cleanups);
        }
    });
    if (media) listen(media, 'change', render, cleanups);
    render();
    cleanups.push(() => { element.classList.remove('is-accordion'); list.hidden = true; panels.forEach((panel, index) => { panel.hidden = false; panel.removeAttribute('role'); panel.removeAttribute('aria-labelledby'); panel.removeAttribute('tabindex'); contents[index].hidden = false; if (headings[index]) { headings[index].hidden = false; const text = headings[index].querySelector('[data-tab-static-title]'); if (text) text.hidden = false; } if (triggers[index]) triggers[index].hidden = true; }); });
}

async function carousel(element, cleanups, state) {
    const [{ default: Swiper }, modules] = await Promise.all([import('swiper'), import('swiper/modules'), import('swiper/css'), import('swiper/css/pagination')]);
    if (state.destroyed || !element.isConnected) return;
    const track = element.querySelector('[data-carousel-track]');
    const controls = element.querySelector('[data-carousel-controls]');
    const previous = element.querySelector('[data-carousel-prev]');
    const next = element.querySelector('[data-carousel-next]');
    const pagination = element.querySelector('[data-carousel-pagination]');
    if (!track) return;
    const requestedAutoplay = element.dataset.autoplay === 'true';
    const autoplay = requestedAutoplay && !reducedMotion();
    const instance = new Swiper(track, {
        modules: [modules.Navigation, modules.Pagination, modules.Keyboard, modules.A11y, modules.Autoplay],
        ...carouselBreakpoints(element.dataset),
        spaceBetween: clamp(element.dataset.gap, 0, 96, 24),
        loop: element.dataset.loop === 'true',
        speed: reducedMotion() ? 0 : 350,
        navigation: previous && next ? { prevEl: previous, nextEl: next } : false,
        pagination: pagination ? { el: pagination, clickable: true } : false,
        keyboard: { enabled: false, onlyInViewport: true },
        a11y: { enabled: true, prevSlideMessage: previous?.getAttribute('aria-label') ?? '', nextSlideMessage: next?.getAttribute('aria-label') ?? '' },
        autoplay: requestedAutoplay ? { delay: clamp(element.dataset.interval, 2000, 60000, 6000), disableOnInteraction: true, pauseOnMouseEnter: true } : false,
    });
    if (!autoplay) instance.autoplay?.stop();
    if (controls) controls.hidden = false;
    element.classList.add('is-ready');
    const pause = element.querySelector('[data-carousel-pause]');
    let explicitlyPaused = !autoplay;
    const updatePause = () => {
        if (!pause) return;
        pause.setAttribute('aria-pressed', String(explicitlyPaused));
        pause.textContent = explicitlyPaused ? pause.dataset.labelPlay : pause.dataset.labelPause;
    };
    if (pause) {
        updatePause();
        listen(pause, 'click', () => {
            explicitlyPaused = !explicitlyPaused;
            if (explicitlyPaused) instance.autoplay.stop(); else instance.autoplay.start();
            updatePause();
        }, cleanups);
    }
    // Focus never races an automatically changing slide.
    listen(element, 'focusin', () => { instance.keyboard?.enable(); instance.autoplay?.pause(); }, cleanups);
    listen(element, 'focusout', event => { if (!element.contains(event.relatedTarget)) { instance.keyboard?.disable(); if (!explicitlyPaused) instance.autoplay?.resume(); } }, cleanups);
    listen(document, 'visibilitychange', () => {
        if (document.hidden) instance.autoplay?.pause(); else if (!explicitlyPaused) instance.autoplay?.resume();
    }, cleanups);
    cleanups.push(() => { instance.destroy(true, true); element.classList.remove('is-ready'); if (controls) controls.hidden = true; });
}

function counter(element, cleanups) {
    const output = element.querySelector('[data-counter-value]');
    if (!output) return;
    const end = clamp(element.dataset.value, -1e12, 1e12, 0);
    const start = clamp(element.dataset.start, -1e12, 1e12, 0);
    const decimals = clamp(element.dataset.decimals, 0, 6, 0);
    let formatter;
    try { formatter = new Intl.NumberFormat(element.dataset.locale, { minimumFractionDigits: decimals, maximumFractionDigits: decimals }); }
    catch { formatter = new Intl.NumberFormat(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals }); }
    const duration = reducedMotion() ? 0 : clamp(element.dataset.duration, 0, 10000, 1200);
    let frame;
    let origin;
    const render = now => {
        origin ??= now;
        const fraction = duration === 0 ? 1 : Math.min(1, (now - origin) / duration);
        output.textContent = formatter.format(counterValue(start, end, fraction));
        if (fraction < 1) frame = requestAnimationFrame(render);
    };
    frame = requestAnimationFrame(render);
    cleanups.push(() => { cancelAnimationFrame(frame); output.textContent = formatter.format(end); });
}

function countdown(element, cleanups) {
    const output = element.querySelector('[data-countdown-value]');
    const fallback = element.querySelector('[data-countdown-fallback]');
    const finished = element.querySelector('[data-countdown-finished]');
    const storageStatus = element.querySelector('[data-countdown-storage-status]');
    const completion = element.querySelector('[data-countdown-completion]');
    if (!output) return;
    let timer;
    let authorized = null;
    let authorizedStorage;
    const initialHidden = element.hidden;
    const setHidden = (node, hidden) => { if (node && node.hidden !== hidden) node.hidden = hidden; };
    const start = deadline => {
        clearInterval(timer);
        let expired = false;
        setHidden(storageStatus, true);
        const render = () => {
            const time = remainingTime(deadline);
            output.textContent = `${time.days}:${String(time.hours).padStart(2, '0')}:${String(time.minutes).padStart(2, '0')}:${String(time.seconds).padStart(2, '0')}`;
            setHidden(output, time.total === 0); setHidden(fallback, true); setHidden(finished, time.total > 0); setHidden(completion, time.total > 0);
            if (time.total === 0) {
                clearInterval(timer);
                if (!expired) {
                    expired = true;
                    const expiry = countdownExpiry(element.dataset, { preview: document.documentElement.hasAttribute('data-cms-preview'), base: location.href });
                    if (expiry.action === 'hide') element.hidden = true;
                    if (expiry.action === 'redirect') location.assign(expiry.url);
                }
            }
        };
        render();
        if (remainingTime(deadline).total > 0) timer = setInterval(render, 1000);
    };
    const persistent = element.dataset.mode === 'visit' && element.dataset.persistence && element.dataset.persistence !== 'memory';
    if (persistent) {
        const lineage = [];
        for (let owner = element.parentElement; owner; owner = owner.parentElement) if (owner.dataset?.cmsNode) lineage.unshift(owner.dataset.cmsNode);
        const key = `dbcms.countdown.v1.${encodeURIComponent(location.pathname)}.${encodeURIComponent(lineage.join('/'))}.${encodeURIComponent(element.dataset.instanceId || '')}.${element.dataset.definitionRevision || ''}`;
        const updateAuthorization = () => {
            const granted = window.dbcmsStorage?.allows?.(element.dataset.storagePurpose) === true;
            if (authorized === granted) return;
            if (authorized === true && !granted) { try { authorizedStorage?.removeItem(key); } catch { /* Withdrawal still stops the timer when browser storage is blocked. */ } authorizedStorage = null; }
            authorized = granted; clearInterval(timer);
            const state = visitCountdownState({ data: element.dataset, bridge: window.dbcmsStorage, storage: mode => { authorizedStorage = mode === 'session' ? window.sessionStorage : window.localStorage; return authorizedStorage; }, key });
            if (state.status === 'ready') start(state.deadline);
            else {
                element.hidden = initialHidden; setHidden(output, true); setHidden(fallback, true); setHidden(finished, true); setHidden(completion, true);
                if (storageStatus) { storageStatus.textContent = state.status === 'denied' ? storageStatus.dataset.messageDenied : storageStatus.dataset.messageUnavailable; setHidden(storageStatus, false); }
            }
        };
        listen(window, 'cms-overlays-ready', updateAuthorization, cleanups);
        listen(window, 'cookie-consent-updated', updateAuthorization, cleanups);
        window.dbcmsStorage?.ready?.then(updateAuthorization).catch(() => {});
        updateAuthorization();
    } else {
        const deadline = countdownDeadline(element.dataset);
        if (deadline !== null) start(deadline);
    }
    cleanups.push(() => { clearInterval(timer); element.hidden = initialHidden; setHidden(output, true); setHidden(fallback, false); setHidden(finished, true); setHidden(storageStatus, true); setHidden(completion, true); });
}

export function lottieDirection(direction) { return direction === 'reverse' ? -1 : 1; }

async function lottie(element, cleanups, state) {
    const source = new URL(element.dataset.animationUrl ?? '', document.baseURI);
    const documentOrigin = globalThis.origin || location.origin;
    if (!element.dataset.animationUrl || source.origin !== documentOrigin || !['http:', 'https:'].includes(source.protocol)) return;
    const controller = new AbortController();
    cleanups.push(() => controller.abort());
    // Redirects are refused before following, preserving the local-only boundary.
    const response = await fetch(source.href, { signal: controller.signal, credentials: 'same-origin', redirect: 'error' });
    if (!response.ok) return;
    const declaredLength = Number(response.headers.get('content-length'));
    if (declaredLength > 5 * 1024 * 1024) return;
    const json = await response.text();
    if (json.length > 5 * 1024 * 1024) return;
    const data = JSON.parse(json);
    if (!validAnimationData(data) || state.destroyed || !element.isConnected) return;
    const { default: player } = await import('lottie-web/build/player/lottie_light.js');
    if (state.destroyed || !element.isConnected) return;
    const canvas = element.querySelector('[data-lottie-canvas]');
    if (!canvas) return;
    const instance = player.loadAnimation({ container: canvas, renderer: 'svg', animationData: data, loop: element.dataset.loop === 'true', autoplay: false });
    instance.setSpeed(clamp(element.dataset.speed, 0.1, 4, 1));
    instance.setDirection(lottieDirection(element.dataset.direction));
    const poster = element.querySelector('[data-lottie-poster]');
    const toggle = element.querySelector('[data-lottie-toggle]');
    let playing = element.dataset.autoplay === 'true' && !reducedMotion();
    const update = () => {
        if (playing) instance.play(); else instance.pause();
        if (toggle) { toggle.setAttribute('aria-pressed', String(playing)); toggle.textContent = playing ? toggle.dataset.labelPause : toggle.dataset.labelPlay; }
    };
    instance.addEventListener('DOMLoaded', () => { if (element.dataset.direction === 'reverse') instance.goToAndStop(Math.max(0, instance.totalFrames - 1), true); if (poster) poster.hidden = true; update(); });
    if (toggle) { toggle.hidden = false; listen(toggle, 'click', () => { playing = !playing; update(); }, cleanups); }
    listen(document, 'visibilitychange', () => { if (document.hidden) instance.pause(); else update(); }, cleanups);
    cleanups.push(() => { instance.destroy(); if (poster) poster.hidden = false; if (toggle) toggle.hidden = true; });
}

export function playlistOrder(length, order = 'authored', random = Math.random) {
    const indices = Array.from({ length }, (_, index) => index);
    if (order === 'reverse') return indices.reverse();
    if (order === 'shuffle') for (let index = indices.length - 1; index > 0; index--) { const other = Math.floor(random() * (index + 1)); [indices[index], indices[other]] = [indices[other], indices[index]]; }
    return indices;
}

export function nextTrackIndex(current, length, repeat = 'none', direction = 1) {
    if (!length) return null;
    const next = current + direction;
    if (next >= 0 && next < length) return next;
    return repeat === 'all' ? (next + length) % length : null;
}

function playlist(element, cleanups) {
    let tracks;
    try { tracks = JSON.parse(element.dataset.playlistTracks || '[]'); } catch { return; }
    const player = element.querySelector('[data-playlist-player]');
    if (!player || !Array.isArray(tracks) || !tracks.length) return;
    const order = playlistOrder(tracks.length, element.dataset.order);
    const controls = element.querySelector('[data-playlist-controls]');
    const buttons = [...element.querySelectorAll('[data-playlist-select]')];
    const list = element.querySelector('[data-playlist-list]');
    const previous = element.querySelector('[data-playlist-previous]'); const next = element.querySelector('[data-playlist-next]');
    const currentLabel = element.querySelector('[data-playlist-current]');
    let current = 0;
    if (list) for (const index of order) if (buttons[index]?.parentElement) list.append(buttons[index].parentElement);
    const select = (index, play = false) => {
        if (index === null || index < 0 || index >= order.length) return;
        current = index; const track = tracks[order[current]];
        player.pause(); player.src = track.url; if (track.title) player.setAttribute('aria-label', track.title);
        if (track.poster) player.setAttribute('poster', track.poster); else player.removeAttribute('poster');
        player.querySelectorAll('track').forEach(node => node.remove());
        if (track.subtitle && player.tagName === 'VIDEO') { const subtitle = document.createElement('track'); subtitle.kind = 'subtitles'; subtitle.src = track.subtitle; subtitle.srclang = track.language; subtitle.label = track.subtitle_label; player.append(subtitle); }
        player.load();
        buttons.forEach((button, position) => { if (position === order[current]) button.setAttribute('aria-current', 'true'); else button.removeAttribute('aria-current'); });
        if (currentLabel) currentLabel.textContent = track.title;
        if (previous) previous.disabled = nextTrackIndex(current, order.length, element.dataset.repeat, -1) === null;
        if (next) next.disabled = nextTrackIndex(current, order.length, element.dataset.repeat) === null;
        if (play) player.play()?.catch(() => {});
    };
    for (const button of buttons) listen(button, 'click', () => select(order.indexOf(Number(button.dataset.playlistSelect))), cleanups);
    if (previous) listen(previous, 'click', () => select(nextTrackIndex(current, order.length, element.dataset.repeat, -1), !player.paused), cleanups);
    if (next) listen(next, 'click', () => select(nextTrackIndex(current, order.length, element.dataset.repeat), !player.paused), cleanups);
    listen(player, 'ended', () => { if (element.dataset.repeat === 'one') select(current, true); else if (element.dataset.autoAdvance === 'true') select(nextTrackIndex(current, order.length, element.dataset.repeat), true); }, cleanups);
    if (controls) controls.hidden = false;
    select(0);
    cleanups.push(() => { player.pause(); if (controls) controls.hidden = true; });
}

export function readingProgress(scrollTop, scrollHeight, viewportHeight) {
    const distance = Number(scrollHeight) - Number(viewportHeight);
    return distance <= 0 ? 100 : clamp(Number(scrollTop) / distance * 100, 0, 100);
}

function reading(element, cleanups) {
    const progress = element.querySelector('progress'); const output = element.querySelector('[data-reading-value]');
    if (!progress) return;
    let frame;
    const render = () => { frame = undefined; const scroller = document.scrollingElement || document.documentElement; const value = readingProgress(scroller.scrollTop, scroller.scrollHeight, scroller.clientHeight); progress.max = 100; progress.value = value; progress.setAttribute('aria-valuenow', String(Math.round(value))); if (output) output.textContent = String(Math.round(value)); };
    const update = () => { if (frame === undefined) frame = requestAnimationFrame(render); };
    listen(document, 'scroll', update, cleanups, { passive: true }); listen(window, 'resize', update, cleanups);
    const observer = globalThis.ResizeObserver ? new ResizeObserver(update) : null; observer?.observe(document.documentElement);
    update(); cleanups.push(() => { if (frame !== undefined) cancelAnimationFrame(frame); observer?.disconnect(); });
}

function hotspots(element, cleanups) {
    const buttons = [...element.querySelectorAll('[data-hotspot-button]')];
    const panels = [...element.querySelectorAll('[data-hotspot-panel]')];
    buttons.forEach(button => {
        const panel = panels.find(node => node.dataset.hotspotPanel === button.dataset.hotspotButton);
        if (!panel) return;
        button.hidden = false;
        listen(button, 'click', () => { panel.open = !panel.open; if (panel.open) panel.querySelector('summary')?.focus(); }, cleanups);
        listen(panel, 'toggle', () => button.setAttribute('aria-expanded', String(panel.open)), cleanups);
        listen(panel, 'keydown', event => { if (event.key === 'Escape') { event.preventDefault(); panel.open = false; button.focus(); } }, cleanups);
    });
    cleanups.push(() => buttons.forEach(button => { button.hidden = true; }));
}

export function initialize(root = document) {
    for (const element of matches(root)) {
        if (instances.has(element)) continue;
        const cleanups = [];
        const state = { destroyed: false, cleanups };
        instances.set(element, state);
        if (element.hasAttribute('data-cms-accordion')) accordion(element, cleanups);
        if (element.hasAttribute('data-cms-tabs')) tabs(element, cleanups);
        if (element.hasAttribute('data-cms-countdown')) countdown(element, cleanups);
        if (element.hasAttribute('data-cms-reading-progress')) reading(element, cleanups);
        if (element.hasAttribute('data-cms-playlist')) playlist(element, cleanups);
        if (element.hasAttribute('data-cms-hotspots')) hotspots(element, cleanups);
        if (element.hasAttribute('data-cms-alert')) {
            const close = element.querySelector('[data-alert-close]');
            if (close) { close.hidden = false; listen(close, 'click', () => { element.hidden = true; }, cleanups); cleanups.push(() => { close.hidden = true; }); }
        }
        if (element.hasAttribute('data-cms-counter')) visible(element, () => counter(element, cleanups), cleanups);
        if (element.hasAttribute('data-cms-carousel')) visible(element, () => { carousel(element, cleanups, state).catch(() => {}); }, cleanups);
        if (element.hasAttribute('data-cms-lottie')) visible(element, () => { lottie(element, cleanups, state).catch(() => {}); }, cleanups);
    }
}

export function destroy(root) {
    for (const element of matches(root)) {
        const state = instances.get(element);
        if (!state) continue;
        state.destroyed = true;
        for (const cleanup of state.cleanups.reverse()) cleanup();
        instances.delete(element);
    }
}

export default function register(Alpine) {
    if (registered || typeof document === 'undefined') return;
    registered = true;
    Alpine?.data('cmsComposable', () => ({ init() { initialize(this.$el); }, destroy() { destroy(this.$el); } }));
    initialize(document);
    const observer = new MutationObserver(records => {
        for (const record of records) {
            for (const node of record.removedNodes) if (node.nodeType === 1 && !node.isConnected) destroy(node);
            for (const node of record.addedNodes) if (node.nodeType === 1 && node.isConnected) initialize(node);
        }
    });
    observer.observe(document.documentElement, { subtree: true, childList: true });
}
