import assert from 'node:assert/strict';
import test from 'node:test';
import { createMediaManager } from '../../resources/js/media-manager.js';

const image = { id: 7, filename: 'photo.webp', url: '/uploads/photo.webp', title: 'Photo', is_image: true, is_video: false };
const page = (files = [image], current = 1, total = files.length) => ({ files, pagination: { current_page: current, last_page: Math.max(1, Math.ceil(total / 15)), per_page: 15, total, from: total ? 1 : null, to: files.length || null } });
const options = {
    picker: true, filesUrl: '/control/media/files', uploadUrl: '/control/media/upload', importUrl: '/control/media/import',
    updateUrl: '/control/media/__ID__', deleteUrl: '/control/media/__FILE__', csrfToken: 'csrf',
    messages: { network_error: 'Connection failed', response_error: 'Invalid response', request_error: 'Request failed', kind_error: 'Wrong kind', metadata_success: 'Saved', delete_success: 'Deleted', upload_success: 'Added' },
};
const response = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });
const environment = async run => {
    const original = { window: globalThis.window, fetch: globalThis.fetch, CustomEvent: globalThis.CustomEvent };
    const events = [];
    globalThis.window = { dispatchEvent: event => events.push(event), location: { search: '', pathname: '/control/media' }, history: { replaceState() {} } };
    globalThis.CustomEvent = class { constructor(type, options) { this.type = type; this.detail = options.detail; } };
    try { await run(events); } finally { Object.assign(globalThis, original); }
};

test('picker keeps target and requested media kind across listing, selection and closing', () => environment(async events => {
    const requests = [];
    globalThis.fetch = async url => { requests.push(url); return response(page()); };
    const manager = createMediaManager(options);
    manager.openPicker({ target: 'image-target', kind: 'image' });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(new URL(requests[0], 'https://example.test').searchParams.get('type'), 'image');
    manager.selectFile({ ...image, is_image: false });
    assert.equal(events.length, 0);
    assert.equal(manager.error, 'Wrong kind');
    manager.clickFile(image);
    manager.selectFile();
    assert.deepEqual(events.map(event => event.type), ['file-selected', 'filemanager-closed']);
    assert.equal(events[0].detail.target, 'image-target');
    assert.equal(events[0].detail.kind, 'image');
    assert.equal(events[1].detail.target, 'image-target');
    assert.equal(events[1].detail.kind, 'image');
    assert.equal(manager.open, false);
}));

test('search and per-page changes reset pagination and selection for both consumers', () => environment(async () => {
    const requests = [];
    globalThis.fetch = async url => { requests.push(url); return response(page()); };
    for (const picker of [true, false]) {
        const manager = createMediaManager({ ...options, picker });
        manager.page = 5;
        manager.search = 'Cover & notes';
        manager.type = 'doc';
        manager.perPage = 25;
        manager.selectedFile = image;
        await manager.applyFilters();
        const query = new URL(requests.at(-1), 'https://example.test').searchParams;
        assert.equal(query.get('search'), 'Cover & notes');
        assert.equal(query.get('type'), 'doc');
        assert.equal(query.get('per_page'), '25');
        assert.equal(query.get('page'), '1');
        assert.equal(manager.selectedFile, null);
    }
}));

test('late responses cannot replace a newer query or a reopened picker target', () => environment(async () => {
    const pending = [];
    globalThis.fetch = () => new Promise(resolve => pending.push(resolve));
    const manager = createMediaManager(options);
    manager.openPicker({ target: 'first', kind: 'image' });
    manager.close();
    manager.openPicker({ target: 'second', kind: 'audio' });
    pending[1](response(page([{ ...image, filename: 'song.wav', is_image: false, is_audio: true }])));
    await new Promise(resolve => setImmediate(resolve));
    pending[0](response(page()));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(manager.targetField, 'second');
    assert.equal(manager.files[0].filename, 'song.wav');
    assert.equal(manager.loading, false);
}));

for (const action of ['upload', 'import', 'metadata', 'delete']) {
    test(`a completed ${action} refresh cannot alter feedback or fields in a reopened picker`, () => environment(async () => {
        let finishOldRefresh;
        const oldRefresh = new Promise(resolve => { finishOldRefresh = resolve; });
        const audio = { id: 8, filename: 'song.wav', url: '/uploads/song.wav', is_image: false, is_audio: true };
        let holdRefresh = false;
        globalThis.fetch = async (_url, init) => {
            if (init.method) return response({ success: true, file: image });
            if (holdRefresh) {
                holdRefresh = false;
                return oldRefresh;
            }
            return response(page([audio]));
        };
        const manager = createMediaManager(options);
        manager.openPicker({ target: 'first', kind: 'image' });
        await new Promise(resolve => setImmediate(resolve));
        manager.selectedFile = { ...image };
        holdRefresh = true;
        let operation;
        if (action === 'upload') operation = manager.uploadFile({ target: { files: [new Blob(['image'])], value: 'photo.webp' } });
        if (action === 'import') {
            manager.importUrl = 'https://example.test/first.webp';
            operation = manager.importImage();
        }
        if (action === 'metadata') operation = manager.saveMetadata();
        if (action === 'delete') {
            manager.askDelete();
            operation = manager.deleteFile();
        }
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(manager.loading, true);
        assert.equal(holdRefresh, false);
        manager.close();
        manager.openPicker({ target: 'second', kind: 'audio' });
        await new Promise(resolve => setImmediate(resolve));
        manager.importUrl = 'https://example.test/second.webp';
        finishOldRefresh(response(page()));
        await operation;
        assert.equal(manager.targetField, 'second');
        assert.equal(manager.kind, 'audio');
        assert.equal(manager.files[0].filename, 'song.wav');
        assert.equal(manager.selectedFile, null);
        assert.equal(manager.error, '');
        assert.equal(manager.notice, '');
        assert.equal(manager.importUrl, 'https://example.test/second.webp');
        assert.equal(manager.busy, false);
        assert.equal(manager.loading, false);
    }));
}

test('HTTP validation, authorization, malformed responses and network errors remain visible', () => environment(async () => {
    const manager = createMediaManager(options);
    for (const status of [403, 419, 422, 500]) {
        globalThis.fetch = async () => response({ message: 'Denied', errors: status === 422 ? { file: ['Invalid file'] } : {} }, status);
        await manager.fetchFiles();
        assert.match(manager.error, new RegExp(`HTTP ${status}`));
        assert.match(manager.error, status === 422 ? /Invalid file/ : /Denied/);
        assert.equal(manager.loading, false);
    }
    globalThis.fetch = async () => ({ ok: false, status: 502, json: async () => { throw new Error('HTML'); } });
    await manager.fetchFiles();
    assert.match(manager.error, /Invalid response.*502/);
    globalThis.fetch = async () => { throw new TypeError('Failed to fetch'); };
    await manager.fetchFiles();
    assert.equal(manager.error, 'Connection failed');
}));

test('metadata failures preserve edits and successes preserve kind flags with CSRF', () => environment(async () => {
    const manager = createMediaManager(options);
    manager.selectedFile = { ...image, title: 'Edited title' };
    globalThis.fetch = async () => response({ success: false, message: 'Cannot save' }, 422);
    await manager.saveMetadata();
    assert.match(manager.error, /Cannot save/);
    assert.equal(manager.selectedFile.title, 'Edited title');
    assert.equal(manager.notice, '');
    const requests = [];
    globalThis.fetch = async (url, init) => {
        requests.push({ url, init });
        return response(init.method === 'PUT' ? { success: true, file: { id: image.id, title: 'Edited title' } } : page());
    };
    await manager.saveMetadata();
    assert.equal(requests[0].init.headers['X-CSRF-TOKEN'], 'csrf');
    assert.equal(manager.selectedFile.is_image, true);
    assert.equal(manager.selectedFile.url, image.url);
    assert.equal(manager.notice, 'Saved');
}));

test('deletion only sends after modal confirmation and refreshes the last page', () => environment(async () => {
    const requests = [];
    globalThis.fetch = async (url, init) => {
        requests.push({ url, init });
        return response(init.method === 'DELETE' ? { success: true } : page([], 1, 0));
    };
    const manager = createMediaManager(options);
    manager.selectedFile = { ...image };
    await manager.deleteFile();
    manager.askDelete();
    assert.equal(manager.deleteOpen, true);
    manager.cancelDelete();
    assert.equal(requests.length, 0);
    manager.askDelete();
    await manager.deleteFile();
    assert.equal(requests[0].init.method, 'DELETE');
    assert.equal(requests[0].init.headers['X-CSRF-TOKEN'], 'csrf');
    assert.equal(manager.selectedFile, null);
    assert.equal(manager.deleteOpen, false);
    assert.equal(manager.page, 1);
    assert.equal(manager.pagination.total, 0);
    assert.equal(manager.notice, 'Deleted');
}));

test('delete failures do not drop files and never report success', () => environment(async () => {
    globalThis.fetch = async () => response({ success: false });
    const manager = createMediaManager(options);
    manager.files = [image];
    manager.selectedFile = { ...image };
    manager.askDelete();
    await manager.deleteFile();
    assert.equal(manager.files.length, 1);
    assert.equal(manager.selectedFile.filename, image.filename);
    assert.equal(manager.notice, '');
    assert.equal(manager.error, 'Request failed');
}));

test('import and upload share storage endpoints, select their response and clear input state', () => environment(async () => {
    const requests = [];
    globalThis.fetch = async (url, init) => {
        requests.push({ url, init });
        return response(init.method === 'POST' ? { success: true, file: image } : page());
    };
    const manager = createMediaManager(options);
    manager.importUrl = ' https://example.test/photo.webp ';
    await manager.importImage();
    assert.deepEqual(JSON.parse(requests[0].init.body), { url: 'https://example.test/photo.webp' });
    assert.equal(manager.importUrl, '');
    assert.equal(manager.selectedFile.filename, image.filename);
    const input = { files: [new Blob(['image'])], value: 'photo.webp' };
    await manager.uploadFile({ target: input });
    assert.equal(input.value, '');
    assert.ok(requests[2].init.body instanceof FormData);
    assert.equal(requests[2].init.headers['X-CSRF-TOKEN'], 'csrf');
    assert.equal(manager.uploading, false);
}));
