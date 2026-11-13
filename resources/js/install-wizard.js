const steps = [
    { id: 'site', label: 'Sitio' },
    { id: 'admin', label: 'Administrador' },
    { id: 'controller', label: 'Titular' },
    { id: 'privacy', label: 'Privacidad' },
];

export function installationErrorStep(errors = []) {
    const group = key => {
        key = key.replace(/^company\./, '');
        if (['name', 'email', 'password', 'password_confirmation'].includes(key)) return 1;
        if (key === 'company' || key.startsWith('processing') || ['hosting_provider', 'hosting_country', 'reviewed'].includes(key)) return 3;
        if (['company_name', 'tax_id', 'address', 'country', 'contact_email', 'privacy_email', 'activity', 'commercial_activity', 'registry_required', 'commercial_registry', 'dpo_required', 'dpo_contact'].includes(key)) return 2;
        return 0;
    };
    return errors.length ? Math.min(...errors.map(group)) : 0;
}

export function createInstallationWizard(config = {}) {
    return {
        ...config,
        steps,
        step: installationErrorStep(config.errors),
        visited: installationErrorStep(config.errors),
        submitting: false,
        init() {
            // Native validation remains available without JavaScript. With the
            // wizard active, reveal the invalid panel before focusing its field.
            this.$refs.form.noValidate = true;
        },
        panel(index) {
            return this.$refs.form.querySelector(`[data-install-step="${steps[index].id}"]`);
        },
        invalid(index) {
            if (index === 1) {
                const password = this.$refs.form.elements.namedItem('password');
                const confirmation = this.$refs.form.elements.namedItem('password_confirmation');
                confirmation.setCustomValidity(confirmation.value && confirmation.value !== password.value ? 'Las contraseñas deben coincidir.' : '');
            }
            return [...this.panel(index).querySelectorAll('input,select,textarea')].find(control => !control.checkValidity());
        },
        show(index, invalid = null) {
            this.step = index;
            this.visited = Math.max(this.visited, index);
            this.$nextTick(() => {
                if (invalid) {
                    for (let parent = invalid.parentElement; parent && parent !== this.panel(index); parent = parent.parentElement) {
                        if (parent.tagName === 'DETAILS') parent.open = true;
                    }
                    invalid.focus();
                    invalid.reportValidity();
                } else {
                    this.panel(index).querySelector('h3').focus();
                }
            });
        },
        next() {
            const invalid = this.invalid(this.step);
            if (invalid) this.show(this.step, invalid);
            else this.show(Math.min(steps.length - 1, this.step + 1));
        },
        go(index) {
            if (index >= 0 && index <= this.visited && index < steps.length) this.show(index);
        },
        submit(event) {
            if (this.submitting) { event.preventDefault(); return; }
            if (this.step < steps.length - 1) {
                event.preventDefault();
                this.next();
                return;
            }
            for (let index = 0; index < steps.length; index++) {
                const invalid = this.invalid(index);
                if (invalid) {
                    event.preventDefault();
                    this.show(index, invalid);
                    return;
                }
            }
            this.submitting = true;
        },
    };
}
