export const copy = value => JSON.parse(JSON.stringify(value));
export const newNodeId = () => globalThis.crypto?.randomUUID?.() ?? `node_${Date.now()}_${Math.random().toString(36).slice(2)}`;

export function schemaDefaults(schema = {}) {
    return Object.fromEntries(Object.entries(schema).map(([key, field]) => {
        if (Object.hasOwn(field, 'default')) return [key, copy(field.default)];
        if (['blocks', 'list', 'array', 'repeater'].includes(field.type)) return [key, []];
        if (field.type === 'object') return [key, schemaDefaults(field.fields)];
        if (field.type === 'json') return [key, {}];
        if (field.type === 'boolean') return [key, false];
        if (field.type === 'number' || field.type === 'integer') return [key, field.min ?? 0];
        if (field.type === 'select') return [key, (Array.isArray(field.options) ? field.options[0] : Object.keys(field.options || {})[0]) ?? ''];
        return [key, ''];
    }));
}

export function cleanEditorValue(value) {
    if (Array.isArray(value)) return value.map(cleanEditorValue);
    if (!value || typeof value !== 'object') return value;
    return Object.fromEntries(Object.entries(value).filter(([key]) => !['_col_buffer', '_columns', 'is_collapsed', '_editorKey'].includes(key)).map(([key, child]) => [key, cleanEditorValue(child)]));
}

export function getAtPath(root, path = []) {
    return path.reduce((value, key) => value?.[key], root);
}

export function childSlots(node, definition = {}) {
    const slots = [];
    const scan = (object, fields, path) => {
        for (const [key, field] of Object.entries(fields || {})) {
            if (field.type === 'blocks' && Array.isArray(object?.[key])) slots.push({ nodes: object[key], path: [...path, key] });
            if (field.type === 'object' && object?.[key]) scan(object[key], field.fields, [...path, key]);
            if (field.type === 'repeater' && Array.isArray(object?.[key])) object[key].forEach((item, index) => scan(item, field.fields, [...path, key, index]));
        }
    };
    scan(node, definition.schema, []);
    for (const [index, column] of (node?.columns || []).entries()) {
        if (Array.isArray(column?.blocks)) slots.push({ nodes: column.blocks, path: ['columns', index, 'blocks'] });
    }
    return slots;
}

export function treeRows(nodes, definitions = [], path = [], depth = 0) {
    const rows = [];
    for (const [index, node] of (nodes || []).entries()) {
        if (!node || typeof node !== 'object') continue;
        const nodePath = [...path, index];
        const definition = definitions.find(item => item.type === (node.type || 'text')) || {};
        rows.push({ node, path: nodePath, listPath: path, index, depth, name: definition.name || node.type || 'text', available: !!definition.type && !(definition.retiredPayloadKeys || []).some(key => Object.hasOwn(node, key)) });
        for (const slot of childSlots(node, definition)) rows.push(...treeRows(slot.nodes, definitions, [...nodePath, ...slot.path], depth + 1));
    }
    return rows;
}

export function cloneBlockTree(node, definitions = []) {
    const cloned = copy(node);
    const refresh = current => {
        current.id = newNodeId();
        for (const column of current.columns || []) column.id = newNodeId();
        const definition = definitions.find(item => item.type === current.type) || {};
        for (const slot of childSlots(current, definition)) slot.nodes.forEach(refresh);
    };
    refresh(cloned);
    return cloned;
}

export function containsUnavailable(node, definitions = []) {
    if (!node || typeof node !== 'object') return true;
    const definition = definitions.find(item => item.type === (node?.type || 'text'));
    if ((definition?.retiredPayloadKeys || []).some(key => Object.hasOwn(node, key))) return true;
    if (!definition && !(Array.isArray(node?.columns) && node.columns.length)) return true;
    return childSlots(node, definition).some(slot => slot.nodes.some(child => containsUnavailable(child, definitions)));
}

export class EditorHistory {
    constructor(initial, { limit = 60, coalesceMs = 500, maxBytes = 4 * 1024 * 1024 } = {}) {
        this.entries = [JSON.stringify(cleanEditorValue(initial))]; this.cursor = 0;
        this.limit = limit; this.coalesceMs = coalesceMs; this.maxBytes = maxBytes; this.lastKey = null; this.lastTime = 0;
    }
    record(value, key = '', now = Date.now()) {
        const snapshot = JSON.stringify(cleanEditorValue(value));
        if (snapshot === this.entries[this.cursor]) return false;
        this.entries.splice(this.cursor + 1);
        if (key && key === this.lastKey && now - this.lastTime <= this.coalesceMs && this.cursor > 0) this.entries[this.cursor] = snapshot;
        else { this.entries.push(snapshot); this.cursor++; }
        while (this.entries.length > 1 && (this.entries.length > this.limit || this.entries.reduce((total, entry) => total + entry.length, 0) > this.maxBytes)) { this.entries.shift(); this.cursor--; }
        this.lastKey = key; this.lastTime = now;
        return true;
    }
    boundary() { this.lastKey = null; }
    undo() { this.boundary(); return this.cursor > 0 ? JSON.parse(this.entries[--this.cursor]) : null; }
    redo() { this.boundary(); return this.cursor < this.entries.length - 1 ? JSON.parse(this.entries[++this.cursor]) : null; }
    get canUndo() { return this.cursor > 0; }
    get canRedo() { return this.cursor < this.entries.length - 1; }
}
