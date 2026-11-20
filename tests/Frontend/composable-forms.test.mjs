import test from 'node:test';
import assert from 'node:assert/strict';
import registerForms from '../../resources/js/forms.js';

function runtime() {
    const events = [], redirects = [];
    const document = {querySelectorAll: () => [], addEventListener() {}};
    globalThis.window = {dispatchEvent: event => events.push(event), location: {assign: path => redirects.push(path)}};
    globalThis.document = document;
    globalThis.CustomEvent = class {constructor(type, options) {this.type = type;this.detail = options.detail;}};
    globalThis.FormData = class {constructor(form) {this.form = form;}};
    registerForms();
    return {api: window.dbCmsForms, events, redirects};
}

function formFixture() {
    const controls = ['name', 'document'].map(name => ({name, valid:true, reports:0, checkValidity() {return this.valid;}, reportValidity() {this.reports++;}, focus() {}}));
    const pages = controls.map(control => ({hidden:false,querySelectorAll: () => [control],querySelector: () => control}));
    const status = {textContent:'', focus() {this.focused = true;}};
    const button = {disabled:false};
    const nodes = {'[data-form-status]':status,'[type="submit"]':button,'[data-form-navigation]':{},'[data-form-previous]':{},'[data-form-next]':{},'[data-form-submit]':{},'[data-form-progress]':{}};
    const form = {action:'/forms/test/submit',dataset:{cmsForm:'test',formId:'4'},
        querySelectorAll: selector => selector === '[data-form-step]' ? pages : controls,
        querySelector: selector => nodes[selector],setAttribute() {},removeAttribute() {},addEventListener() {}};
    return {form, controls, pages, status, button, nodes};
}

test('step navigation validates the current page and retains values', () => {
    const {api} = runtime();
    const {form, controls, pages} = formFixture();
    controls[0].valid = false;
    assert.equal(api.navigate(form, 1), false);
    assert.equal(controls[0].reports, 1);
    controls[0].valid = true;
    controls[0].value = 'Retained';
    assert.equal(api.navigate(form, 1), true);
    assert.equal(form.dataset.step, '1');
    assert.equal(pages[0].hidden, true);
    api.navigate(form, -1);
    assert.equal(controls[0].value, 'Retained');
});

test('final validation reveals an invalid earlier step and never submits it', async () => {
    const {api, events} = runtime();
    const {form, controls, pages} = formFixture();
    form.dataset.step = '1';
    controls[0].valid = false;
    globalThis.fetch = () => {throw new Error('Must not send');};
    await api.submit(form);
    assert.equal(form.dataset.step, '0');
    assert.equal(pages[0].hidden, false);
    assert.deepEqual(events, []);
});

test('server validation errors reveal the owning step without success or popup events', async () => {
    const {api, events} = runtime();
    const {form, status, button} = formFixture();
    globalThis.fetch = async () => ({ok:false,json:async () => ({errors:{document:['Archivo no válido']}})});
    await api.submit(form);
    assert.equal(form.dataset.step, '1');
    assert.equal(status.textContent, 'Archivo no válido');
    assert.equal(button.disabled, false);
    assert.deepEqual(events, []);
});

test('multipart submission emits success and popup actions only after server success', async () => {
    const {api, events, redirects} = runtime();
    const {form, button} = formFixture();
    let finish;
    globalThis.fetch = (url, options) => {
        assert.equal(url, '/forms/test/submit');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.body.form, form);
        assert.equal(options.headers.Accept, 'application/json');
        return new Promise(resolve => {finish = resolve;});
    };
    const pending = api.submit(form);
    assert.equal(button.disabled, true);
    assert.deepEqual(events, []);
    finish({ok:true,json:async () => ({success:true,message:'Recibido',submission_id:9,actions:[{type:'close_popup',target:'lead'},{type:'open_popup',target:'thanks'},{type:'redirect',target:'/gracias'}]})});
    await pending;
    assert.deepEqual(events.map(event => event.type), ['cms-form-submitted','cms-overlay-close','cms-overlay-open']);
    assert.deepEqual(events[0].detail, {id:4,slug:'test',submission_id:9});
    assert.deepEqual(events[1].detail, {id:'lead'});
    assert.deepEqual(redirects, ['/gracias']);
    assert.equal(button.disabled, false);
});

test('network failures emit no successful submission and unsafe redirects are ignored', async () => {
    const {api, events, redirects} = runtime();
    const {form, status} = formFixture();
    globalThis.fetch = async () => {throw new Error('Network failed');};
    await api.submit(form);
    assert.deepEqual(events, []);
    assert.match(status.textContent, /No se pudo enviar/);
    api.complete({}, [{type:'redirect',target:'//attacker.example'},{type:'redirect',target:'/\\attacker.example'},{type:'redirect',target:'/%2f%2fattacker.example'}]);
    assert.deepEqual(redirects, []);
});


test('private previews cannot initialize, submit or execute success actions', async () => {
    const {api, events, redirects} = runtime();
    const {form} = formFixture();
    document.documentElement = {hasAttribute: name => name === 'data-cms-preview'};
    document.querySelectorAll = () => [form];
    globalThis.fetch = () => {throw new Error('Preview must not submit');};
    api.initialize(document);
    assert.equal(form.dataset.initialized, undefined);
    await api.submit(form);
    api.complete({}, [{type:'open_popup',target:'thanks'},{type:'redirect',target:'/thanks'}]);
    assert.deepEqual(events, []);
    assert.deepEqual(redirects, []);
});

test('submission honors prevention by the embedding document', async () => {
    const {api, events} = runtime();
    const {form, nodes} = formFixture();
    nodes['[data-form-next]'].addEventListener = () => {};
    nodes['[data-form-previous]'].addEventListener = () => {};
    const listeners = {};
    form.addEventListener = (name, listener) => {listeners[name] = listener;};
    document.querySelectorAll = () => [form];
    api.initialize(document);
    globalThis.fetch = () => {throw new Error('Prevented form must not submit');};
    listeners.submit({defaultPrevented:true,preventDefault() {throw new Error('Already prevented');}});
    assert.deepEqual(events, []);
    assert.notEqual(form.dataset.submitting, 'true');
});
