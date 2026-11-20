import test from 'node:test';
import assert from 'node:assert/strict';
import { createInstallationWizard, installationErrorStep } from '../../resources/js/install-wizard.js';

const fixture = () => {
    const controls = Array.from({ length: 4 }, () => []);
    const headings = Array.from({ length: 4 }, () => ({ focus() { this.focused = true; } }));
    const confirmation = { value: '', setCustomValidity(message) { this.message = message; } };
    const password = { value: 'test-secret-password' };
    const form = {
        noValidate: false,
        querySelector(selector) {
            const index = ['site', 'admin', 'controller', 'privacy'].findIndex(id => selector.includes(`"${id}"`));
            return { querySelectorAll: () => controls[index], querySelector: () => headings[index] };
        },
        elements: { namedItem: name => name === 'password' ? password : confirmation },
    };
    const wizard = createInstallationWizard({ errors: [] });
    wizard.$refs = { form };
    wizard.$nextTick = callback => callback();
    wizard.init();
    return { wizard, form, controls, headings, password, confirmation };
};
const event = () => ({ prevented: false, preventDefault() { this.prevented = true; } });

test('server errors select the earliest affected panel including shared DTO keys', () => {
    for (const [errors, expected] of [
        [[], 0], [['front_theme'], 0], [['email'], 1], [['company.tax_id'], 2], [['commercial_registry'], 2],
        [['company.processing.0.purpose'], 3], [['hosting_country'], 3], [['reviewed'], 3], [['company'], 3],
        [['processing', 'company_name', 'password'], 1],
    ]) assert.equal(installationErrorStep(errors), expected);
});

test('navigation blocks on invalid current fields and focuses the visible field', () => {
    const { wizard, controls, headings, form } = fixture();
    const details = { tagName: 'DETAILS', open: false };
    const field = { parentElement: details, checkValidity: () => false, focus() { this.focused = true; }, reportValidity() { this.reported = true; } };
    controls[0].push(field);
    wizard.next();
    assert.equal(wizard.step, 0);
    assert.equal(field.focused, true); assert.equal(field.reported, true); assert.equal(details.open, true);
    assert.equal(form.noValidate, true);
    controls[0].length = 0;
    wizard.next();
    assert.equal(wizard.step, 1); assert.equal(headings[1].focused, true);
    wizard.go(3); assert.equal(wizard.step, 1);
    wizard.go(0); assert.equal(wizard.step, 0);
});

test('Enter advances before the final panel without posting or retaining credentials elsewhere', () => {
    const { wizard, password } = fixture();
    const first = event(); wizard.submit(first);
    assert.equal(first.prevented, true); assert.equal(wizard.step, 1);
    const second = event(); wizard.submit(second);
    assert.equal(second.prevented, true); assert.equal(wizard.step, 2);
    wizard.go(1); assert.equal(password.value, 'test-secret-password');
    assert.equal(wizard.submitting, false);
});

test('password confirmation validity is recalculated after correcting the input', () => {
    const { wizard, confirmation, password } = fixture();
    confirmation.value = 'different'; wizard.invalid(1);
    assert.equal(confirmation.message, 'Las contraseñas deben coincidir.');
    confirmation.value = password.value; wizard.invalid(1);
    assert.equal(confirmation.message, '');
});

test('final submission validates earlier panels and prevents repeated posts', () => {
    const { wizard, controls } = fixture();
    wizard.step = 3;
    controls[2].push({ checkValidity: () => false, focus() {}, reportValidity() {} });
    const invalid = event(); wizard.submit(invalid);
    assert.equal(invalid.prevented, true); assert.equal(wizard.step, 2); assert.equal(wizard.submitting, false);
    controls[2].length = 0; wizard.step = 3;
    const valid = event(); wizard.submit(valid);
    assert.equal(valid.prevented, false); assert.equal(wizard.submitting, true);
    const repeated = event(); wizard.submit(repeated);
    assert.equal(repeated.prevented, true);
});
