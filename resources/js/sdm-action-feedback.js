export const createSdmActionFeedback = () => ({
    messages: [],
    type: 'success',
    invalidField: null,

    get title() {
        return this.type === 'error' ? 'SDM action could not be completed' : 'SDM action successful';
    },

    init() {
        this.messages = JSON.parse(this.$el.dataset.messages || '[]');
        this.type = this.$el.dataset.feedbackType || 'success';

        if (this.messages.length) {
            this.$nextTick(() => this.open());
        }
    },

    open() {
        if (!this.$refs.dialog.open) {
            this.$refs.dialog.showModal();
        }
    },

    showValidationError(event) {
        const field = event.target;

        if (!field.form || field.form.method.toLowerCase() === 'get') {
            return;
        }

        event.preventDefault();

        if (!this.invalidField) {
            this.messages = [];
            this.invalidField = field;
        }

        const label = field.labels?.[0]?.textContent?.replace(/\s+/g, ' ').trim() || field.name;
        const message = label ? `${label}: ${field.validationMessage}` : field.validationMessage;

        if (!this.messages.includes(message)) {
            this.messages.push(message);
        }

        this.type = 'error';
        this.open();
    },

    dismiss() {
        this.$refs.dialog.close();
        this.invalidField?.focus();
        this.invalidField = null;
    },
});
