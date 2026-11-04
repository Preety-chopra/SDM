export function createFeedbackForm({
    request = (...args) => fetch(...args),
    currentUrl = () => window.location.href,
    csrfToken = () => document.querySelector('meta[name="csrf-token"]').content,
} = {}) {
    return {
        message: '',
        pageUrl: '',
        errors: [],
        success: '',
        submitting: false,
        open() {
            this.pageUrl = currentUrl();
            this.errors = [];
            this.success = '';
            if (!this.$refs.dialog.open) this.$refs.dialog.showModal();
        },
        close() {
            if (!this.submitting) this.$refs.dialog.close();
        },
        async submit() {
            if (this.submitting) return;
            this.submitting = true;
            this.errors = [];
            this.success = '';
            try {
                const response = await request(this.$el.dataset.submitUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ message: this.message, page_url: currentUrl() }),
                });
                if ([401, 419].includes(response.status) || response.redirected) {
                    this.errors = ['Your session has expired. Copy your feedback and sign in again to submit it.'];
                    return;
                }
                const data = await response.json();
                if (!response.ok) {
                    this.errors = response.status === 422
                        ? Object.values(data.errors || {}).flat()
                        : [response.status === 429 ? 'Please wait a minute before submitting more feedback.' : 'Your feedback could not be submitted. Please try again.'];
                    return;
                }
                this.success = data.message;
                this.message = '';
            } catch {
                this.errors = ['Your feedback could not be submitted. Please check your connection and try again.'];
            } finally {
                this.submitting = false;
            }
        },
    };
}
