const parseJsonArray = (value) => {
    try {
        const parsed = JSON.parse(value || '[]');

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
};

const controlsFor = (control) => {
    if (!control) {
        return [];
    }

    if (typeof control.length === 'number' && !control.tagName) {
        return Array.from(control);
    }

    return [control];
};

const controlValue = (control) => {
    if (control.type === 'checkbox' || control.type === 'radio') {
        if (!control.checked) {
            return '';
        }

        return control.dataset.previewValue || control.labels?.[0]?.textContent?.trim() || control.value;
    }

    if (control.dataset?.previewValue) {
        return control.dataset.previewValue.trim();
    }

    if (control.type === 'select-one') {
        return control.selectedOptions?.[0]?.textContent?.trim() || control.value;
    }

    return String(control.value ?? '').trim();
};

export const createSdmConfirmation = (modalName = 'sdm-action-confirmation') => ({
    sdmPreview: [],
    pendingForm: null,
    pendingSubmitter: null,
    confirmationApproved: false,
    isSubmitting: false,

    requestSdmConfirmation(event) {
        const submitter = event.submitter;

        if (!submitter || submitter.dataset.sdmConfirm === undefined || this.confirmationApproved) {
            return;
        }

        event.preventDefault();

        if (!event.currentTarget.reportValidity()) {
            return;
        }

        this.pendingForm = event.currentTarget;
        this.pendingSubmitter = submitter;
        this.sdmPreview = this.buildSdmPreview(event.currentTarget, submitter);
        this.$dispatch('open-modal', modalName);
    },

    cancelSdmConfirmation() {
        if (this.isSubmitting) {
            return;
        }

        this.pendingForm = null;
        this.pendingSubmitter = null;
        this.sdmPreview = [];
        this.$dispatch('close-modal', modalName);
    },

    confirmSdmAction() {
        if (this.isSubmitting || !this.pendingForm || !this.pendingSubmitter) {
            return;
        }

        this.isSubmitting = true;
        this.confirmationApproved = true;
        this.pendingForm.requestSubmit(this.pendingSubmitter);
    },

    buildSdmPreview(form, submitter) {
        const fixedItems = parseJsonArray(form.dataset.confirmationPreview);
        const fieldItems = parseJsonArray(form.dataset.confirmationFields).flatMap((field) => {
            if (field.value) {
                return [{ label: field.label, value: field.value }];
            }

            const values = controlsFor(form.elements?.namedItem(field.name))
                .filter((control) => !control.disabled && control.dataset?.previewIgnore === undefined)
                .map(controlValue)
                .filter(Boolean);

            const value = values.length ? values.join(', ') : field.emptyValue;

            return value ? [{ label: field.label, value }] : [];
        });

        return [
            ...(form.dataset.confirmationShowAction === 'false'
                ? []
                : [{ label: 'Action', value: submitter.dataset.confirmationAction || 'Proceed' }]),
            ...fixedItems,
            ...fieldItems,
        ].filter((item) => item.label && item.value);
    },
});
