import { getAtPath, treeRows, childSlots, newNodeId } from './block-editor-tree.js';
import { mediaIconButton } from './media-field.js';
import { t } from './editor-labels.js';

const raw = value => globalThis.Alpine?.raw?.(value) ?? value;
const designMounts = new WeakMap();
const sides = ['top', 'right', 'bottom', 'left'];
const sideLabels = () => [t('side_top'), t('side_right'), t('side_bottom'), t('side_left')];
const controlClass = 'admin-control';

export function appearanceScope(device = 'base', state = 'base') { return state === 'base' ? device : state; }
export function splitDimension(value = '') {
    const text = String(value); const match = /^(-?(?:\d+(?:\.\d+)?|\.\d+))(px|rem|em|%|vh|vw|svh|dvh|ch|ms|s)?$/.exec(text);
    return match ? { amount: match[1], unit: match[2] || '', custom: false } : { amount: text, unit: '', custom: text !== '' };
}
export function expandBox(value = '') {
    const parts = String(value).trim().match(/(?:[^\s(]+\([^)]*\)|[^\s]+)/g) || [];
    if (!parts.length) return ['', '', '', ''];
    if (parts.length === 1) return [parts[0], parts[0], parts[0], parts[0]];
    if (parts.length === 2) return [parts[0], parts[1], parts[0], parts[1]];
    if (parts.length === 3) return [parts[0], parts[1], parts[2], parts[1]];
    return parts.slice(0, 4);
}
export function schemaDesignFields(definition = {}) {
    return Object.fromEntries(Object.entries(definition.schema || {}).filter(([key, field]) => field.group === 'design' || field.presentation?.group === 'design' || (definition.type === 'container' && ['layout', 'grid_columns', 'gap'].includes(key))).map(([key, field]) => {
        if (definition.type !== 'container') return [key, field];
        const clear = { layout: ['display', 'flex_direction', 'flex_wrap'], grid_columns: ['grid_template_columns'], gap: ['gap', 'row_gap', 'column_gap'] }[key];
        return [key, { ...field, ...(key === 'grid_columns' && !field.show_when ? { show_when: { field: 'layout', equals: 'grid' } } : {}), ...(clear ? { clear_appearance_on_edit: clear } : {}) }];
    }));
}
export function clearOwnerDesignOverrides(target, field) { for (const key of field.clear_appearance_on_edit || []) if (target.appearance?.base) delete target.appearance.base[key]; }
export function schemaContentFields(definition = {}) {
    const design = schemaDesignFields(definition);
    return Object.fromEntries(Object.entries(definition.schema || {}).filter(([key]) => !Object.hasOwn(design, key)));
}
export function clearLegacyOverlap(target, key, scope = 'base') {
    const border = /^border_(width|style|color)$/.exec(key);
    if (border) {
        for (const side of ['top', 'bottom']) {
            const legacy = target[`border_${side}`];
            if (!legacy || !legacy.enabled || typeof legacy !== 'object') continue;
            target.appearance ??= {}; target.appearance.base ??= {};
            for (const [part, fallback] of Object.entries({ width: '1px', style: 'solid', color: 'var(--cms-accent)' })) {
                const common = `border_${part}`; const sideKey = `border_${side}_${part}`;
                if (!Object.hasOwn(target.appearance.base, common) && !Object.hasOwn(target.appearance.base, sideKey)) target.appearance.base[sideKey] = legacy[part] ?? fallback;
                delete legacy[part];
            }
            delete legacy.enabled;
            if (!Object.keys(legacy).length) delete target[`border_${side}`];
        }
        for (const side of sides) if (target.appearance?.[scope]) delete target.appearance[scope][`border_${side}_${border[1]}`];
    }
    if (scope !== 'base') return;
    if (key === 'background_color') delete target.bg_color;
    if (key === 'padding' || key.startsWith('padding_')) delete target.padding;
}

export function canMoveNodeInto(node, destination, definitions = []) {
    const pending = [raw(node)]; const seen = new Set();
    while (pending.length) {
        const current = pending.pop(); if (!current || seen.has(current)) continue; seen.add(current);
        const definition = definitions.find(item => item.type === (current.type || 'text')) || {};
        for (const slot of childSlots(current, definition)) {
            if (raw(slot.nodes) === raw(destination)) return false;
            for (const child of slot.nodes) pending.push(raw(child));
        }
    }
    return true;
}
export function writeStyle(target, scope, key, value) {
    clearLegacyOverlap(target, key, scope);
    if (value === '' || value === null) { if (target.appearance?.[scope]) delete target.appearance[scope][key]; return; }
    target.appearance ??= {}; target.appearance[scope] ??= {}; target.appearance[scope][key] = value;
}
export function writeBoxSide(target, scope, kind, index, value, linked = false) {
    const existing = target.appearance?.[scope] || {};
    const shorthand = existing[kind] ?? (scope === 'base' ? target[kind] : undefined);
    const expanded = expandBox(shorthand);
    const values = sides.map((side, sideIndex) => existing[`${kind}_${side}`] ?? expanded[sideIndex]);
    if (linked) {
        for (const side of sides) if (target.appearance?.[scope]) delete target.appearance[scope][`${kind}_${side}`];
        writeStyle(target, scope, kind, value); return;
    }
    if (shorthand !== undefined) {
        for (const [sideIndex, side] of sides.entries()) if (values[sideIndex] !== '') writeStyle(target, scope, `${kind}_${side}`, values[sideIndex]);
        if (target.appearance?.[scope]) delete target.appearance[scope][kind];
        if (scope === 'base') delete target[kind];
    }
    writeStyle(target, scope, `${kind}_${sides[index]}`, value);
}
export function validInspectorValue(value, descriptor = {}) {
    if (value === '') return true;
    const text = String(value);
    if (/[;{}<>\\\x00-\x1f]/.test(text) || text.length > 2048) return false;
    if (descriptor.type === 'select' || descriptor.control === 'select') return Object.hasOwn(descriptor.options || {}, text) || (Array.isArray(descriptor.options) && descriptor.options.includes(text));
    if (descriptor.control === 'length') return /^(?:auto|none|normal|fit-content|max-content|min-content|var\(--[a-z0-9-]+\)|-?(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em|%|vh|vw|svh|dvh|ch|ms|s)?)$/i.test(text);
    if (descriptor.control === 'color') return /^(?:#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})|[a-z]+|var\(--[a-z0-9-]+\)|(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\([0-9a-z.,%+\-\s/]+\))$/i.test(text) && (!globalThis.CSS?.supports || globalThis.CSS.supports('color', text));
    if (descriptor.type === 'number' || descriptor.type === 'integer' || descriptor.control === 'number') return Number.isFinite(Number(text)) && (descriptor.min === undefined || Number(text) >= descriptor.min) && (descriptor.max === undefined || Number(text) <= descriptor.max);
    if (descriptor.control === 'media') { try { return ['http:', 'https:'].includes(new URL(text, 'https://cms.invalid').protocol); } catch { return false; } }
    return true;
}

function cssArguments(value) {
    const parts = []; let start = 0; let depth = 0;
    for (let index = 0; index < value.length; index++) { if (value[index] === '(') depth++; if (value[index] === ')') depth--; if (value[index] === ',' && depth === 0) { parts.push(value.slice(start, index).trim()); start = index + 1; } }
    parts.push(value.slice(start).trim()); return parts;
}
export function simpleGradientSettings(value = '', defaults = {}) {
    const fallback = { type: 'linear', angle: 135, from: 'var(--cms-surface)', to: 'var(--cms-accent)', ...defaults };
    if (!String(value).trim()) return { supported: true, empty: true, settings: fallback };
    const match = /^(linear|radial)-gradient\((.*)\)$/i.exec(String(value).trim());
    if (!match) return { supported: false, source: value };
    const parts = cssArguments(match[2]); const settings = { ...fallback, type: match[1].toLowerCase() };
    if (settings.type === 'linear') {
        const angle = /^(-?\d+(?:\.\d+)?)deg$/i.exec(parts[0]);
        settings.angle = angle ? Number(angle[1]) : 180; if (angle) parts.shift();
        if (settings.angle < 0 || settings.angle > 360) return { supported: false, source: value };
    } else if (parts[0] === 'circle') parts.shift(); else return { supported: false, source: value };
    if (parts.length !== 2) return { supported: false, source: value };
    const color = (part, stop) => part.replace(new RegExp(`\\s+${stop}%$`), '').trim();
    settings.from = color(parts[0], 0); settings.to = color(parts[1], 100);
    if (![settings.from, settings.to].every(value => validInspectorValue(value, { control: 'color' }))) return { supported: false, source: value };
    return { supported: true, empty: false, settings };
}
export function simpleGradientCss(settings) {
    if (!['linear', 'radial'].includes(settings.type) || !Number.isFinite(Number(settings.angle)) || Number(settings.angle) < 0 || Number(settings.angle) > 360 || ![settings.from, settings.to].every(value => validInspectorValue(value, { control: 'color' }))) throw new Error(t('gradient_invalid'));
    return `${settings.type}-gradient(${settings.type === 'linear' ? Number(settings.angle) + 'deg' : 'circle'}, ${settings.from} 0%, ${settings.to} 100%)`;
}
export function rootComposition(blocks, rows, selectedId, selectedPath) {
    const row = selectedId === null ? null : rows.find(item => String(item.node.id) === String(selectedId));
    if (selectedId !== null && !row) return null;
    let path = row?.path;
    if (!path) { try { path = JSON.parse(selectedPath || '[]'); } catch { return null; } }
    return path?.length && Number.isInteger(path[0]) ? blocks[path[0]] || null : null;
}

export class DebouncedPreview {
    constructor({ delay = 400, setTimer = (callback, wait) => globalThis.setTimeout(callback, wait), clearTimer = timer => globalThis.clearTimeout(timer) } = {}) { this.delay = delay; this.setTimer = setTimer; this.clearTimer = clearTimer; this.revision = 0; this.key = null; }
    schedule(key, run) {
        if (this.key === key) return;
        this.key = key; this.cancel();
        if (!key) return;
        this.timer = this.setTimer(() => { this.timer = null; this.start(run); }, this.delay);
    }
    start(run) { const revision = this.revision; const controller = new AbortController(); this.controller = controller; return run(controller, () => revision === this.revision && !controller.signal.aborted); }
    runNow(key, run) { this.key = key; this.cancel(); return this.start(run); }
    cancel() { this.revision++; if (this.timer) this.clearTimer(this.timer); this.timer = null; this.controller?.abort(); this.controller = null; }
    reset() { this.key = null; this.cancel(); }
}

function element(tag, text, className) { const node = document.createElement(tag); if (text !== undefined) node.textContent = text; if (className) node.className = className; return node; }
function action(label, callback, className = 'admin-btn admin-btn--ghost admin-btn--sm') { const node = element('button', label, className); node.type = 'button'; node.addEventListener('click', callback); return node; }
function fieldShell(label) { const host = element('div', undefined, 'cms-inspector-field'); const caption = element('label', label, 'admin-field-label'); host.append(caption); return { host, caption }; }
function options(descriptor) { return Array.isArray(descriptor.options) ? descriptor.options.map(value => [value, value]) : Object.entries(descriptor.options || {}); }
export function themeVariableOptions(descriptor = {}, shared = {}) { const values = descriptor.supports_theme_variables ? shared : descriptor.theme_variables || {}; return Array.isArray(values) ? values.map(value => [value, value]) : Object.entries(values); }

function appendAlignment(host, manager, key, descriptor, read, write) {
    const group = element('fieldset', undefined, 'cms-inspector-field'); group.dataset.inspectorField = key; group.append(element('legend', descriptor.label || key, 'admin-field-label'));
    const toolbar = element('div', undefined, 'cms-inspector-alignment'); toolbar.setAttribute('role', 'toolbar'); toolbar.setAttribute('aria-label', descriptor.label || key);
    const buttons = options(descriptor).map(([value, label]) => { const button = action(label, () => { write(value); refresh(); }); button.dataset.value = value; button.setAttribute('aria-pressed', 'false'); toolbar.append(button); return button; });
    toolbar.addEventListener('keydown', event => { const index = buttons.indexOf(document.activeElement); if (index < 0 || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return; event.preventDefault(); const next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length; buttons[next].focus(); });
    const refresh = () => { const value = String(read() ?? ''); const selected = buttons.findIndex(button => button.dataset.value === value); buttons.forEach((button, index) => { const pressed = button.dataset.value === value; button.setAttribute('aria-pressed', String(pressed)); button.classList.toggle('admin-btn--active', pressed); button.tabIndex = index === (selected < 0 ? 0 : selected) ? 0 : -1; }); };
    group._cmsInspectorRefresh = refresh; group.append(toolbar); host.append(group); refresh(); return group;
}

function appendGradient(host, manager, key, descriptor, read, write) {
    const group = element('fieldset', undefined, 'cms-inspector-field cms-inspector-gradient'); group.dataset.inspectorField = key; group.append(element('legend', descriptor.label || t('gradient'), 'admin-field-label'));
    const body = element('div', undefined, 'space-y-3'); group.append(body); let lastSource;
    const render = () => {
        lastSource = String(read() || ''); const parsed = simpleGradientSettings(lastSource, descriptor.gradient_defaults); body.replaceChildren();
        if (!parsed.supported) {
            const message = element('p', t('gradient_custom_kept'), 'admin-field-hint'); message.dataset.gradientCustom = 'true'; body.append(message, element('code', lastSource, 'cms-inspector-saved-value'), action(t('gradient_use_basic'), () => { write(simpleGradientCss(simpleGradientSettings('', descriptor.gradient_defaults).settings)); render(); })); return;
        }
        const settings = parsed.settings; const commit = () => { write(simpleGradientCss(settings), { preserveGradientDrafts: true }); lastSource = String(read() || ''); };
        const type = element('select', undefined, controlClass); type.setAttribute('aria-label', t('gradient_type'));
        for (const [value, label] of Object.entries(descriptor.gradient_types || {})) type.append(new Option(label, value)); type.value = settings.type;
        const direction = element('div', undefined, 'cms-inspector-gradient-angle'); const label = element('label', t('gradient_angle'), 'admin-field-label'); const angle = element('input', undefined, controlClass); angle.id = `gradient-angle-${newNodeId()}`; label.htmlFor = angle.id; angle.type = 'number'; angle.min = '0'; angle.max = '360'; angle.step = 'any'; angle.value = settings.angle;
        const slider = element('input'); slider.type = 'range'; slider.min = '0'; slider.max = '360'; slider.step = 'any'; slider.value = settings.angle; slider.setAttribute('aria-label', t('gradient_angle_adjust'));
        const angleKey = `${manager.inspectorSelectionKey()}:${manager.appearanceState}:${key}_angle`;
        angle.addEventListener('input', () => { const valid = angle.value !== '' && angle.checkValidity(); if (valid) delete manager.inspectorDraftValues[angleKey]; else manager.inspectorDraftValues[angleKey] = angle.value; manager.setInspectorValidity(angleKey, valid); if (valid) { settings.angle = Number(angle.value); slider.value = angle.value; commit(); } });
        slider.addEventListener('input', () => { settings.angle = Number(slider.value); angle.value = slider.value; delete manager.inspectorDraftValues[angleKey]; manager.setInspectorValidity(angleKey, true); commit(); });
        const updateDirection = () => { const hidden = settings.type !== 'linear'; direction.hidden = hidden; angle.disabled = hidden; slider.disabled = hidden; angle.value = settings.angle; slider.value = settings.angle; };
        direction.append(label, angle, slider); updateDirection();
        if (settings.type === 'linear' && manager.inspectorInvalid[angleKey] && Object.hasOwn(manager.inspectorDraftValues, angleKey)) angle.value = manager.inspectorDraftValues[angleKey];
        type.addEventListener('change', () => { settings.type = type.value; updateDirection(); delete manager.inspectorDraftValues[angleKey]; manager.setInspectorValidity(angleKey, true); commit(); }); body.append(type, direction);
        for (const [part, label] of [['from', t('gradient_from')], ['to', t('gradient_to')]]) appendControl(body, manager, `${key}_${part}`, { type: 'text', control: 'color', label, supports_theme_variables: true }, { read: () => settings[part], write: value => { settings[part] = value || simpleGradientSettings('', descriptor.gradient_defaults).settings[part]; commit(); } });
        if (parsed.empty) body.append(action(t('gradient_apply'), () => commit()));
    };
    group._cmsInspectorRefresh = (force = false) => { if (force || (!group.contains(document.activeElement) && String(read() || '') !== lastSource)) render(); };
    render(); host.append(group); return group;
}

function appendControl(host, manager, key, descriptor, { legacy = false, read, write } = {}) {
    const label = descriptor.label || key;
    const get = read || (() => legacy ? manager.presentationValue(key) : manager.appearanceValue(key));
    const set = write || ((value, options = {}) => legacy ? manager.setPresentationValue(key, value) : manager.setAppearanceValue(key, value, options));
    if (descriptor.control === 'alignment') return appendAlignment(host, manager, key, descriptor, get, set);
    if (descriptor.control === 'gradient') return appendGradient(host, manager, key, descriptor, get, set);
    const originalValue = String(get() ?? '');
    const { host: group, caption } = fieldShell(label); group.dataset.inspectorField = key;
    const row = element('div', undefined, 'cms-inspector-control');
    const input = element(descriptor.type === 'select' || descriptor.control === 'select' ? 'select' : 'input', undefined, controlClass);
    input.id = `inspector-${newNodeId()}`; caption.htmlFor = input.id;
    const error = element('span', '', 'cms-inspector-error'); error.setAttribute('role', 'status');
    const draftKey = `${manager.inspectorSelectionKey()}:${manager.appearanceState}:${key}`;
    const commit = value => {
        const valid = validInspectorValue(value, descriptor) || (descriptor.control === 'select' && descriptor.type !== 'select' && String(value) === originalValue); if (valid) delete manager.inspectorDraftValues[draftKey]; else manager.inspectorDraftValues[draftKey] = String(value); manager.setInspectorValidity(draftKey, valid);
        input.setCustomValidity(valid ? '' : t('value_review')); error.textContent = valid ? '' : t('value_invalid');
        if (valid) set(value);
    };
    let unit; let color;
    if (input.tagName === 'SELECT') {
        input.append(new Option(t('inherit_theme'), ''));
        for (const [value, title] of options(descriptor)) if (value !== '') input.append(new Option(title, value));
        input.addEventListener('change', () => commit(input.value));
    } else if (descriptor.type === 'boolean' || descriptor.control === 'boolean') {
        input.type = 'checkbox'; input.className = 'cms-inspector-checkbox'; input.addEventListener('change', () => commit(input.checked));
    } else if (descriptor.control === 'length') {
        input.type = 'text'; input.inputMode = 'decimal'; input.placeholder = t('inherit');
        unit = element('select', undefined, `${controlClass} cms-inspector-unit`); unit.setAttribute('aria-label', t('unit_of', { label }));
        unit.append(new Option('—', '')); for (const value of descriptor.units || ['px', 'rem', 'em', '%']) unit.append(new Option(value, value));
        const update = () => commit(input.value === '' ? '' : (/^-?(?:\d+(?:\.\d+)?|\.\d+)$/.test(input.value) ? input.value + unit.value : input.value));
        input.addEventListener('input', update); unit.addEventListener('change', update); row.append(input, unit);
    } else {
        input.type = 'text'; input.placeholder = t('inherit'); input.addEventListener('input', () => commit(input.value));
    }
    if (!row.contains(input)) row.append(input);
    if (descriptor.control === 'color') {
        color = element('input', undefined, 'cms-inspector-color'); color.type = 'color'; color.setAttribute('aria-label', t('choose_value', { label: label.toLowerCase() }));
        color.addEventListener('input', () => { input.value = color.value; commit(color.value); }); row.prepend(color);
    }
    const reset = action('↶', () => { commit(''); refresh(); }, 'cms-inspector-reset');
    reset.setAttribute('aria-label', t('inherit_value', { label: label.toLowerCase() }));
    reset.hidden = originalValue === '';
    row.append(reset);
    group.append(row);
    const tokens = themeVariableOptions(descriptor, manager.editorOptions.appearanceVariables).filter(([value]) => descriptor.control === 'color' && !value.includes('radius'));
    if (tokens.length) {
        const select = element('select', undefined, `${controlClass} cms-inspector-token`); select.setAttribute('aria-label', t('theme_variable_for', { label: label.toLowerCase() })); select.append(new Option(t('theme_variables'), ''));
        for (const [value, title] of tokens) select.append(new Option(typeof title === 'object' ? title.label || value : title, value.startsWith('--') ? `var(${value})` : value));
        select.addEventListener('change', () => { if (select.value) { commit(select.value); refresh(); select.value = ''; } }); group.append(select);
    }
    if (descriptor.control === 'media' || descriptor.type === 'media') {
        const video = (descriptor.media_type || 'image') !== 'image';
        row.classList.add('cms-media-field-row');
        row.insertBefore(mediaIconButton(video ? 'fa-film' : 'fa-image', t('media_library'), video ? t('media_pick_video') : t('media_pick_image'), () => manager.openInspectorMedia(key, descriptor.media_type || 'image'), 'cms-media-field-pick'), reset);
        row.insertBefore(mediaIconButton('fa-xmark', t('media_clear'), t('media_clear'), () => { commit(''); refresh(); }, 'cms-media-field-clear'), reset);
    }
    if (descriptor.description && !descriptor.description.startsWith('Valor tipado o variable del tema')) group.append(element('span', descriptor.description, 'admin-field-hint'));
    group.append(error);
    const refresh = (force = false) => {
        const value = String(get() ?? '');
        group.dataset.inherited = value === '' ? 'true' : 'false';
        reset.hidden = value === '' && !manager.inspectorInvalid[draftKey];
        if (!force && (document.activeElement === input || document.activeElement === unit || manager.inspectorInvalid[draftKey])) return;
        error.textContent = ''; input.setCustomValidity('');
        if (input.tagName === 'SELECT') { const saved = [...input.options].find(option => option.dataset.saved); if (descriptor.type === 'select') saved?.remove(); if (value && ![...input.options].some(option => option.value === value)) { const option = new Option(descriptor.type === 'select' ? t('saved_option', { value }) : t('saved_custom'), value); option.dataset.saved = 'true'; option.title = value; if (descriptor.type === 'select') option.disabled = true; input.append(option); } input.value = value; }
        else if (unit) { const dimension = splitDimension(value); input.value = dimension.amount; if (dimension.unit && ![...unit.options].some(option => option.value === dimension.unit)) unit.append(new Option(dimension.unit, dimension.unit)); unit.value = dimension.unit; }
        else if (input.type === 'checkbox') input.checked = get() === true || get() === 1 || get() === '1';
        else input.value = value;
        if (color && /^#[a-f0-9]{6}$/i.test(value)) color.value = value;
    };
    group._cmsInspectorRefresh = refresh; refresh(); if (manager.inspectorInvalid[draftKey]) { const draft = splitDimension(manager.inspectorDraftValues[draftKey]); input.value = unit ? draft.amount : manager.inspectorDraftValues[draftKey]; if (unit) unit.value = draft.unit; error.textContent = t('value_invalid'); } host.append(group); return group;
}

function designSection(host, title, hint, { open = false } = {}) {
    const section = element('details', undefined, 'cms-inspector-section');
    section.open = open;
    const header = element('summary', undefined, 'cms-inspector-summary');
    const label = element('span', title, 'cms-inspector-summary-label');
    const chevron = element('i', undefined, 'fa-solid fa-chevron-down cms-inspector-summary-chevron');
    chevron.setAttribute('aria-hidden', 'true');
    header.append(label, chevron);
    section.append(header);
    if (hint) section.append(element('p', hint, 'admin-field-hint'));
    host.append(section);
    return section;
}

const MOTION_CHOICES = Object.freeze([
    ['none', 'animation_none', 'animation_none_desc'],
    ['fadeIn', 'animation_fade_in', 'animation_fade_in_desc'],
    ['fadeInUp', 'animation_fade_in_up', 'animation_fade_in_up_desc'],
    ['fadeInDown', 'animation_fade_in_down', 'animation_fade_in_down_desc'],
    ['fadeInLeft', 'animation_fade_in_left', 'animation_fade_in_left_desc'],
    ['fadeInRight', 'animation_fade_in_right', 'animation_fade_in_right_desc'],
    ['zoomIn', 'animation_zoom_in', 'animation_zoom_in_desc'],
    ['bounceIn', 'animation_bounce_in', 'animation_bounce_in_desc'],
    ['flipInX', 'animation_flip_in_x', 'animation_flip_in_x_desc'],
    ['video-playback', 'animation_video_playback', 'animation_video_playback_desc'],
]);

function appendChoiceCards(host, { label, hint, value, choices, onPick }) {
    const section = element('div', undefined, 'cms-inspector-essentials cms-inspector-motion');
    section.append(element('h4', label, 'cms-inspector-essentials-title'));
    if (hint) section.append(element('p', hint, 'admin-field-hint'));
    const grid = element('div', undefined, 'cms-inspector-choice-grid');
    grid.setAttribute('role', 'radiogroup');
    grid.setAttribute('aria-label', label);
    for (const [choice, titleKey, descKey] of choices) {
        const button = element('button', undefined, 'cms-inspector-choice');
        button.type = 'button';
        button.setAttribute('role', 'radio');
        const selected = String(value || 'none') === choice;
        button.setAttribute('aria-checked', String(selected));
        button.classList.toggle('admin-btn--active', selected);
        button.dataset.value = choice;
        button.append(element('span', t(titleKey), 'cms-inspector-choice-title'));
        button.append(element('span', t(descKey), 'cms-inspector-choice-desc'));
        button.addEventListener('click', () => onPick(choice));
        grid.append(button);
    }
    section.append(grid);
    host.append(section);
    return section;
}

function appendMotionEssentials(host, manager, presentation) {
    if (!presentation.animation) return;
    const remount = () => { designMounts.delete(host); mountInspectorMotion(host, manager); };
    const bgType = manager.presentationValue('bg_type');
    const current = manager.presentationValue('animation') || 'none';
    const choices = MOTION_CHOICES.filter(([value]) => value !== 'video-playback' || bgType === 'video');
    appendChoiceCards(host, {
        label: t('section_motion'),
        hint: t('section_motion_hint'),
        value: current,
        choices,
        onPick: value => { manager.setPresentationValue('animation', value); remount(); },
    });
    if (!current || current === 'none' || current === 'video-playback') return;
    const timing = element('div', undefined, 'cms-inspector-essentials');
    timing.append(element('h4', t('animation_timing'), 'cms-inspector-essentials-title'));
    timing.append(element('p', t('animation_timing_hint'), 'admin-field-hint'));
    for (const key of ['animation_delay', 'animation_duration']) if (presentation[key]) appendControl(timing, manager, key, presentation[key], { legacy: true });
    host.append(timing);
}

export function mountInspectorMotion(host, manager) {
    const target = manager.presentationTarget();
    if (!target || manager.inspectorColumn()) { host.replaceChildren(); return; }
    const identity = `${manager.inspectorSelectionKey()}:motion`;
    const current = designMounts.get(host);
    if (current?.identity === identity && current.target === raw(target)) return;
    designMounts.set(host, { identity, target: raw(target) });
    manager.inspectorHosts.add(host);
    host.replaceChildren();
    appendMotionEssentials(host, manager, manager.editorOptions.presentationProperties || {});
}

function appendDesignScopes(host, manager) {
    const bar = element('div', undefined, 'cms-inspector-scopes');
    const devices = element('div', undefined, 'cms-inspector-toolbar-group');
    devices.setAttribute('role', 'group');
    devices.setAttribute('aria-label', t('design_device'));
    for (const [value, label] of [['base', t('viewport_desktop')], ['tablet', t('viewport_tablet')], ['mobile', t('viewport_mobile')]]) {
        const button = action(label, () => manager.setInspectorDevice(value));
        button.setAttribute('aria-pressed', String(manager.inspectorDevice === value));
        button.classList.toggle('admin-btn--active', manager.inspectorDevice === value);
        devices.append(button);
    }
    const states = element('div', undefined, 'cms-inspector-toolbar-group');
    states.setAttribute('role', 'group');
    states.setAttribute('aria-label', t('design_state'));
    for (const [value, label] of [['base', t('state_base')], ['hover', t('state_hover')], ['focus', t('state_focus')], ['active', t('state_active')], ['selected', t('state_selected')]]) {
        const button = action(label, () => manager.setInspectorState(value));
        button.setAttribute('aria-pressed', String(manager.inspectorState === value));
        button.classList.toggle('admin-btn--active', manager.inspectorState === value);
        states.append(button);
    }
    bar.append(devices, states);
    host.append(bar);
}

function appendSpacing(host, manager, kind, descriptor, { open = false, linkedDefault = true } = {}) {
    const title = kind === 'padding' ? t('padding') : t('margin');
    const section = designSection(host, title, undefined, { open });
    const identity = `${manager.inspectorSelectionKey()}:${manager.appearanceState}:${kind}`;
    const linked = element('input'); linked.type = 'checkbox'; linked.id = `link-${newNodeId()}`;
    linked.checked = manager.inspectorLinks[identity] ?? (linkedDefault || new Set(manager.boxValues(kind)).size <= 1);
    linked.addEventListener('change', () => { manager.inspectorLinks[identity] = linked.checked; manager.refreshInspectorControls(); });
    const label = element('label', undefined, 'cms-inspector-link admin-check-label'); label.htmlFor = linked.id; label.append(linked, document.createTextNode(` ${t('link_sides')}`)); section.append(label);
    const box = element('div', undefined, 'cms-inspector-box');
    sides.forEach((side, index) => appendControl(box, manager, `${kind}_${side}`, { ...descriptor, label: sideLabels()[index], control: 'length' }, {
        read: () => manager.boxValues(kind)[index],
        write: value => { writeBoxSide(manager.presentationTarget(true), manager.appearanceState, kind, index, value, linked.checked); manager.syncBlocksToLocale(); manager.refreshInspectorControls(); },
    }));
    section.append(box);
}

function appendBackgroundEssentials(host, manager, presentation, properties, { allowMedia = true } = {}) {
    const background = element('div', undefined, 'cms-inspector-essentials space-y-3');
    background.append(element('h4', t('section_background'), 'cms-inspector-essentials-title'));
    if (presentation.bg_type) {
        const optionsMap = allowMedia
            ? presentation.bg_type.options
            : Object.fromEntries(Object.entries(presentation.bg_type.options || {}).filter(([value]) => ['', 'none', 'color'].includes(value)));
        appendControl(background, manager, 'bg_type', { ...presentation.bg_type, options: optionsMap }, {
            legacy: true,
            write: value => { manager.setBackgroundType(value); designMounts.delete(host); mountInspectorDesign(host, manager); },
        });
    }
    const type = manager.presentationValue('bg_type');
    if ((!type || type === 'color' || type === 'none') && properties.background_color) appendControl(background, manager, 'background_color', properties.background_color);
    if (type === 'gradient' && properties.background_image) appendControl(background, manager, 'background_image', properties.background_image);
    if (allowMedia) {
        const mediaKeys = type === 'image'
            ? ['bg_image_url', 'bg_image_overlay', 'bg_behavior', 'bg_position', 'bg_size', 'bg_repeat', 'bg_external_service']
            : type === 'video'
                ? ['bg_video_url', 'bg_video_poster', 'bg_video_overlay', 'bg_video_fit', 'bg_video_position', 'bg_external_service']
                : [];
        for (const key of mediaKeys) if (presentation[key]) appendControl(background, manager, key, presentation[key], { legacy: true });
    }
    host.append(background);
}

function summaryLabel(details) {
    return details.querySelector(':scope > summary .cms-inspector-summary-label')?.textContent
        || details.querySelector(':scope > summary')?.textContent
        || '';
}

export function mountInspectorDesign(host, manager) {
    const target = manager.presentationTarget(); if (!target) { host.replaceChildren(); return; }
    const identity = `${manager.inspectorSelectionKey()}:${manager.appearanceState}:design`;
    const identityTarget = manager.inspectorColumn()?.column || target;
    const current = designMounts.get(host); if (current?.identity === identity && current.target === raw(identityTarget)) return;
    const disclosures = new Set([...host.querySelectorAll('details[open]')].map(summaryLabel));
    const restoreDisclosures = () => { for (const details of host.querySelectorAll('details')) if (disclosures.has(summaryLabel(details))) details.open = true; };
    designMounts.set(host, { identity, target: raw(identityTarget) }); manager.inspectorHosts.add(host); host.replaceChildren();
    const properties = manager.editorOptions.appearanceProperties || {};
    const presentation = manager.editorOptions.presentationProperties || {};
    const add = (section, keys, { asText = false } = {}) => {
        for (const key of keys) if (properties[key]) {
            const descriptor = asText && ['background_image', 'box_shadow', 'text_shadow'].includes(key) ? { ...properties[key], control: 'text' } : properties[key];
            appendControl(section, manager, key, descriptor);
        }
    };
    const column = !!manager.inspectorColumn();
    const root = !column && manager.isRootSelection();
    const blockType = column ? null : manager.selectedNode()?.type;
    const skipTypography = !!column || ['image', 'video', 'map', 'lottie', 'spacer', 'separator'].includes(blockType);

    appendDesignScopes(host, manager);

    if (manager.appearanceState !== 'base') {
        host.append(element('p', t('section_paint_hint'), 'admin-field-hint'));
        if (root) {
            const size = element('div', undefined, 'cms-inspector-essentials');
            size.append(element('h4', t('section_size'), 'cms-inspector-essentials-title'));
            const dimensions = element('div', undefined, 'cms-inspector-dimensions');
            size.append(dimensions);
            add(dimensions, ['width', 'height']);
            add(size, ['max_width', 'min_width', 'min_height', 'max_height']);
            host.append(size);
        }
        const paint = element('div', undefined, 'cms-inspector-essentials');
        paint.append(element('h4', t('section_paint'), 'cms-inspector-essentials-title'));
        add(paint, ['background_color', 'background_image', 'border_radius', 'border_width', 'border_style', 'border_color', 'box_shadow', 'text_shadow']);
        host.append(paint);
        restoreDisclosures(); return;
    }

    // Essentials by role: section / column / block.
    if (root || column) appendBackgroundEssentials(host, manager, presentation, properties, { allowMedia: root });
    if (root) {
        const size = element('div', undefined, 'cms-inspector-essentials');
        size.append(element('h4', t('section_size'), 'cms-inspector-essentials-title'));
        const dimensions = element('div', undefined, 'cms-inspector-dimensions');
        size.append(dimensions);
        add(dimensions, ['width', 'height']);
        if (presentation.content_width) {
            appendControl(size, manager, 'content_width', presentation.content_width, { legacy: true, write: value => { manager.setPresentationValue('content_width', value); designMounts.delete(host); mountInspectorDesign(host, manager); } });
            if (manager.presentationValue('content_width') === 'custom' && presentation.content_max_width) appendControl(size, manager, 'content_max_width', presentation.content_max_width, { legacy: true });
        }
        appendControl(size, manager, 'extent', { type: 'select', label: t('section_extent'), options: { theme: t('extent_theme'), viewport: t('extent_viewport') } }, { read: () => manager.presentationTarget()?.appearance?.extent || '', write: value => manager.setExtent(value) });
        if (properties.gap) appendControl(size, manager, 'gap', properties.gap);
        host.append(size);
    }
    if (!skipTypography) {
        const typography = element('div', undefined, 'cms-inspector-essentials');
        typography.append(element('h4', t('section_text'), 'cms-inspector-essentials-title'));
        add(typography, ['color', 'font_size', 'font_weight', 'text_align']);
        host.append(typography);
    }
    if (properties.padding) appendSpacing(host, manager, 'padding', properties.padding, { open: true, linkedDefault: true });
    if (!column && !root && properties.margin) appendSpacing(host, manager, 'margin', properties.margin, { open: true, linkedDefault: true });
    if (!column && !root && properties.border_radius) {
        const border = element('div', undefined, 'cms-inspector-essentials');
        border.append(element('h4', t('section_borders'), 'cms-inspector-essentials-title'));
        add(border, ['border_radius']);
        host.append(border);
    }
    const ownerFields = column ? {} : schemaDesignFields(manager.typeDef(blockType));
    if (Object.keys(ownerFields).length) {
        const layout = element('div', undefined, 'cms-inspector-essentials');
        layout.append(element('h4', t('section_layout'), 'cms-inspector-essentials-title'));
        const mount = element('div', undefined, 'cms-inspector-field'); layout.append(mount);
        manager.mountSchemaEditor(mount, manager.selectedNode(), 'design');
        host.append(layout);
    }
    const more = designSection(host, t('more_options'), t('more_options_hint'), { open: false });
    const moreBody = (title) => {
        const group = element('div', undefined, 'cms-inspector-essentials');
        group.append(element('h4', title, 'cms-inspector-essentials-title'));
        more.append(group);
        return group;
    };
    if (root) {
        const sizeMore = moreBody(t('size_limits') || t('section_size'));
        sizeMore.append(element('p', t('section_size_hint'), 'admin-field-hint'));
        add(sizeMore, ['max_width', 'min_width', 'min_height', 'max_height']);
    }
    if (!column && ['image', 'video'].includes(blockType)) add(more, ['object_fit']);
    if (!skipTypography) add(moreBody(t('section_text')), ['line_height', 'font_family', 'font_style', 'letter_spacing', 'text_transform', 'text_decoration']);
    if (properties.margin && (column || root)) appendSpacing(more, manager, 'margin', properties.margin);
    const borders = moreBody(t('section_borders'));
    add(borders, root || column ? ['border_radius', 'border_width', 'border_style', 'border_color', 'box_shadow'] : ['border_width', 'border_style', 'border_color', 'box_shadow', 'text_shadow']);
    if ((root || column) && presentation.container_style) appendControl(borders, manager, 'container_style', presentation.container_style, { legacy: true });
    if (!column && Object.keys(ownerFields).length === 0) {
        add(moreBody(t('section_layout')), ['display', 'flex_direction', 'flex_wrap', 'justify_content', 'align_items', 'gap']);
    }
    if (root) {
        const dividerKeys = Object.keys(presentation).filter(key => key.startsWith('divider_'));
        if (dividerKeys.length) {
            const dividers = moreBody(t('section_dividers'));
            for (const side of ['top', 'bottom']) for (const key of dividerKeys.filter(field => field.startsWith(`divider_${side}.`))) {
                if (!key.endsWith('.type') && !manager.presentationValue(`divider_${side}.type`)) continue;
                if (!key.endsWith('.type') && manager.presentationValue(`divider_${side}.type`) === 'none') continue;
                appendControl(dividers, manager, key, presentation[key], { legacy: true, ...(key.endsWith('.type') ? { write: value => { manager.setPresentationValue(key, value); designMounts.delete(host); mountInspectorDesign(host, manager); } } : {}) });
            }
        }
    }

    const advanced = designSection(host, t('tab_advanced'), t('advanced_panel_hint'), { open: false });
    const position = element('div', undefined, 'cms-inspector-essentials');
    position.append(element('h4', t('section_position'), 'cms-inspector-essentials-title'));
    if (t('section_position_hint')) position.append(element('p', t('section_position_hint'), 'admin-field-hint'));
    advanced.append(position);
    add(position, ['position', 'top', 'right', 'bottom', 'left', 'z_index', 'order', 'align_self', 'grid_column', 'grid_row', 'overflow', 'visibility', 'opacity', 'transform', 'filter', 'transition_duration']);
    const technical = element('div', undefined, 'cms-inspector-essentials');
    technical.append(element('h4', t('section_technical'), 'cms-inspector-essentials-title'));
    advanced.append(technical);
    add(technical, ['grid_template_columns', 'grid_template_rows', 'grid_auto_flow', 'row_gap', 'column_gap', 'outline_color', 'outline_width', 'outline_offset']);
    const custom = element('div', undefined, 'cms-inspector-essentials');
    custom.append(element('h4', t('section_custom'), 'cms-inspector-essentials-title'));
    if (t('section_custom_hint')) custom.append(element('p', t('section_custom_hint'), 'admin-field-hint'));
    advanced.append(custom);
    add(custom, ['background_image', 'box_shadow', 'text_shadow'], { asText: true });

    restoreDisclosures();
}

export function createInspectorFeatures() {
    const hosts = new Set(); const emptyColumnStyle = {}; const viewportObservers = new Map();
    return {
        inspectorMode: true, inspectorTab: 'content', inspectorDevice: 'base', inspectorState: 'base', inspectorMobilePane: 'edit', previewViewport: 'desktop', previewZoom: 'fit', previewScale: 1, previewStageHeight: 672, inspectorColumnId: null, inspectorColumnPath: '[]', inspectorLinks: {}, inspectorInvalid: {}, inspectorDraftValues: {}, inspectorHosts: hosts,
        appearanceState: 'base',
        setInspectorDevice(value) { this.inspectorDevice = value; this.appearanceState = appearanceScope(value, this.inspectorState); this.previewViewport = { base: 'desktop', tablet: 'tablet', mobile: 'mobile' }[value]; },
        setInspectorState(value) { this.inspectorState = value; this.appearanceState = appearanceScope(this.inspectorDevice, value); },
        inspectorColumn() {
            if (!this.inspectorColumnId) return null;
            for (const row of this.structureRows()) for (const [index, column] of (row.node.columns || []).entries()) if (String(column.id) === String(this.inspectorColumnId)) return { column, path: [...row.path, 'columns', index], owner: row.node, index };
            return null;
        },
        inspectorSelectionKey() { return `${this.activeLocale}:${this.inspectorColumn()?.column.id || this.selectedNode()?.id || this.selectedPath}`; },
        inspectorLabel() { return this.inspectorColumn() ? t('column_n', { n: this.inspectorColumn().index + 1 }) : this.typeDef(this.selectedNode()?.type).name || this.selectedNode()?.type || t('select_block'); },
        inspectorEntries() { return this.selectedNode() ? [{ key: this.inspectorSelectionKey() }] : []; },
        nextInspectorTab(direction) { const tabs = ['content', 'design']; this.inspectorTab = tabs[(tabs.indexOf(this.inspectorTab) + direction + tabs.length) % tabs.length]; this.focusInspectorTab(); },
        focusInspectorTab() { this.$nextTick?.(() => this.$el?.querySelector(`[data-inspector-tab="${this.inspectorTab}"]`)?.focus()); },
        presentationTarget(create = false) { const selected = this.inspectorColumn(); if (!selected) return this.selectedNode(); if (!selected.column.style && create) selected.column.style = {}; return selected.column.style || emptyColumnStyle; },
        selectColumn(path) { const column = getAtPath(this.blocks, path); if (!column) return; this.selectNode(path.slice(0, -2)); this.inspectorColumnId = column.id; this.inspectorColumnPath = JSON.stringify(path); this.inspectorTab = 'design'; this.sendPreviewSelection(); },
        isRootSelection() { if (this.inspectorColumn()) return false; const node = this.selectedNode(); return this.blocks.includes(node); },
        canEditMotion() { return !this.inspectorColumn() && !!this.selectedNode(); },
        selectedAppearance(create = false) { const target = this.presentationTarget(create); if (!target) return null; if (create) { target.appearance ??= {}; target.appearance[this.appearanceState] ??= {}; } return target.appearance?.[this.appearanceState] || null; },
        appearanceValue(key) { const own = this.selectedAppearance()?.[key]; if (own !== undefined) return own; const target = this.presentationTarget(); if (this.appearanceState === 'base' && key === 'background_color') return target?.bg_color ?? ''; return ''; },
        setAppearanceValue(key, value, options = {}) { const target = this.presentationTarget(true); if (!target) return; if (key === 'background_image' && !options.preserveGradientDrafts) this.clearInspectorFieldDrafts(['background_image'], this.appearanceState); writeStyle(target, this.appearanceState, key, value); this.syncBlocksToLocale(); },
        boxValues(kind) { const target = this.presentationTarget() || {}; const values = target.appearance?.[this.appearanceState] || {}; const shorthand = values[kind] ?? (this.appearanceState === 'base' ? target[kind] : ''); const expanded = expandBox(shorthand); return sides.map((side, index) => values[`${kind}_${side}`] ?? expanded[index]); },
        setExtent(value) { if (!this.isRootSelection()) return; const target = this.presentationTarget(true); if (value) { target.appearance ??= {}; target.appearance.extent = value; } else if (target.appearance) delete target.appearance.extent; this.syncBlocksToLocale(); },
        presentationValue(key) { const target = this.presentationTarget(); return key.split('.').reduce((value, part) => value?.[part], target) ?? ''; },
        clearInspectorFieldDrafts(fields, scope = 'base') { const prefix = `${this.inspectorSelectionKey()}:${scope}:`; for (const key of Object.keys(this.inspectorInvalid)) if (fields.some(field => key === prefix + field || (field === 'background_image' && key.startsWith(prefix + field + '_')))) { delete this.inspectorInvalid[key]; delete this.inspectorDraftValues[key]; } },
        setBackgroundType(value) { const target = this.presentationTarget(true); if (!target) return; if (value !== 'gradient') { if (target.appearance?.base) delete target.appearance.base.background_image; this.clearInspectorFieldDrafts(['background_image']); } if (!value || value === 'none') { if (target.appearance?.base) delete target.appearance.base.background_color; delete target.bg_color; } if (value !== 'image') this.clearInspectorFieldDrafts(['bg_image_url', 'bg_image_overlay', 'bg_behavior', 'bg_position', 'bg_size', 'bg_repeat']); if (value !== 'video') this.clearInspectorFieldDrafts(['bg_video_url', 'bg_video_poster', 'bg_video_overlay', 'bg_video_fit', 'bg_video_position']); if (!value) delete target.bg_type; else target.bg_type = value; this.syncBlocksToLocale(); },
        setPresentationValue(key, value) { const target = this.presentationTarget(true); if (!target) return; const parts = key.split('.'); let object = target; for (const part of parts.slice(0, -1)) { if (!object[part] || typeof object[part] !== 'object') { if (value === '') return; object[part] = {}; } object = object[part]; } if (value === '') delete object[parts.at(-1)]; else object[parts.at(-1)] = value; if (key === 'animation' && target.scroll_magic) delete target.scroll_magic.behavior; if (key === 'animation_delay' && target.scroll_magic) delete target.scroll_magic.delay; if (key === 'animation_duration' && target.scroll_magic) delete target.scroll_magic.duration; this.syncBlocksToLocale(); },
        setInspectorValidity(key, valid) { if (valid) delete this.inspectorInvalid[key]; else this.inspectorInvalid[key] = true; this.scheduleCompositionPreview(); },
        inspectorDraftPrefixes() { const root = this.previewComposition(); if (!root) return []; const rootIndex = this.blocks.indexOf(root); const prefixes = []; for (const row of this.structureRows().filter(item => item.path[0] === rootIndex)) { prefixes.push(`${this.activeLocale}:${row.node.id || JSON.stringify(row.path)}:`); for (const column of row.node.columns || []) if (column.id) prefixes.push(`${this.activeLocale}:${column.id}:`); } return prefixes; },
        inspectorHasInvalid() { const prefixes = this.inspectorDraftPrefixes(); return Object.keys(this.inspectorInvalid).some(key => prefixes.some(prefix => key.startsWith(prefix))); },
        reconcileInspectorDrafts() { const valid = []; for (const [locale, nodes] of Object.entries(this.sectionsByLocale)) for (const row of treeRows(nodes, this.availableTypes)) { valid.push(`${locale}:${row.node.id || JSON.stringify(row.path)}:`); for (const column of row.node.columns || []) if (column.id) valid.push(`${locale}:${column.id}:`); } for (const key of Object.keys(this.inspectorInvalid)) if (!valid.some(prefix => key.startsWith(prefix))) { delete this.inspectorInvalid[key]; delete this.inspectorDraftValues[key]; } },
        clearInspectorDrafts() { this.inspectorInvalid = {}; this.inspectorDraftValues = {}; },
        refreshInspectorControls(force = false) { for (const host of hosts) { if (!host.isConnected) { hosts.delete(host); continue; } for (const field of host.querySelectorAll('[data-inspector-field]')) field._cmsInspectorRefresh?.(force); } },
        mountInspectorDesign(host) { mountInspectorDesign(host, this); },
        mountInspectorMotion(host) { mountInspectorMotion(host, this); },
        openInspectorMedia(key, kind) { const column = this.inspectorColumn()?.column; const node = this.selectedNode(); const draftKey = `${this.inspectorSelectionKey()}:base:${key}`; this.beginMediaRequest({ mode: 'inspector', kind, onSelect: file => { const target = column ? (column.style ??= {}) : node; if (!target) return; const parts = key.split('.'); let object = target; for (const part of parts.slice(0, -1)) object = object[part] ??= {}; object[parts.at(-1)] = file.url; delete this.inspectorInvalid[draftKey]; delete this.inspectorDraftValues[draftKey]; } }); },
        appearanceClasses() { return (this.presentationTarget()?.appearance?.classes || []).join(' '); },
        setAppearanceClasses(value) { const target = this.presentationTarget(true); if (!target) return; target.appearance ??= {}; target.appearance.classes = value.split(/\s+/).filter(Boolean); this.syncBlocksToLocale(); },
        appearanceHtmlClass() { return (this.presentationTarget()?.appearance?.html_class || []).join(' '); },
        setAppearanceHtmlClass(value) {
            const target = this.presentationTarget(true);
            if (!target) return;
            target.appearance ??= {};
            const tokens = String(value || '').trim().split(/\s+/).filter(Boolean);
            if (tokens.length) target.appearance.html_class = tokens;
            else delete target.appearance.html_class;
            this.syncBlocksToLocale();
        },
        htmlClassHint() {
            const options = this.editorOptions.htmlClasses || {};
            const names = Array.isArray(options) ? options : Object.keys(options);
            const max = this.editorOptions.htmlClassMax || 16;
            if (names.length) {
                return t('html_class_hint_allowlist', { max, names: names.map((name) => (options[name] && options[name] !== name ? name + ' · ' + options[name] : name)).join(', ') });
            }
            return t('html_class_hint_open', { max });
        },
        designPresetsHint() {
            const options = this.editorOptions.appearanceClasses || [];
            const names = Array.isArray(options) ? options : Object.keys(options);
            return t('design_presets_hint', { names: names.join(', ') });
        },
        previewComposition() { return rootComposition(this.blocks, this.structureRows(), this.selectedId, this.selectedPath); },
        previewFrame() { return this.$el?.querySelector('[data-composition-preview] iframe'); },
        previewWidth() { return { desktop: 1280, tablet: 820, mobile: 390 }[this.previewViewport] || 1280; },
        previewFrameStyle() { return `width:${this.previewWidth()}px;height:${Math.round(this.previewStageHeight / this.previewScale)}px;transform:scale(${this.previewScale})`; },
        mountPreviewViewport(host) { const width = this.previewWidth(); const fit = this.previewZoom === 'fit'; const resize = () => { if (!host.clientWidth) return; this.previewScale = fit ? Math.min(1, host.clientWidth / width) : 1; this.previewStageHeight = host.clientHeight || 672; }; viewportObservers.get(host)?.disconnect(); const observer = new ResizeObserver(resize); viewportObservers.set(host, observer); observer.observe(host); resize(); },
        sendPreviewSelection() { const column = this.inspectorColumn(); this.previewFrame()?.contentWindow?.postMessage({ source: 'cms-block-preview', type: 'selection', ...(column ? { column_index: column.index } : { node_id: String(this.selectedNode()?.id || '') }) }, globalThis.location?.origin || '*'); },
        replayPreview() { this.previewFrame()?.contentWindow?.postMessage({ source: 'cms-block-preview', type: 'replay' }, globalThis.location?.origin || '*'); },
        inspectorTreeRows() { const rows = []; for (const row of this.structureRows()) { rows.push({ ...row, kind: 'node' }); for (const [index, column] of (row.node.columns || []).entries()) rows.push({ node: column, path: [...row.path, 'columns', index], depth: row.depth + 1, name: t('column_n', { n: index + 1 }), kind: 'column', available: true }); } return rows; },
        savedPresentation() { const target = this.presentationTarget() || {}; return JSON.stringify(Object.fromEntries(Object.entries(target).filter(([key]) => key === 'appearance' || key.startsWith('bg_') || key.startsWith('border_') || key.startsWith('divider_') || key.startsWith('animation') || ['padding', 'container_style', 'content_width', 'content_max_width', 'scroll_magic'].includes(key))), null, 2); },
        destroyInspector() { hosts.clear(); for (const observer of viewportObservers.values()) observer.disconnect(); viewportObservers.clear(); },
    };
}
