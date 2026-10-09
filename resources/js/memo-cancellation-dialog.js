export const createMemoCancellationDialog = () => ({
    cancellationOpen: false,
    confirmationOpen: false,

    openCancellation() {
        this.confirmationOpen = false;
        this.cancellationOpen = true;
    },

    reviewCancellation(form) {
        if (!form.reportValidity()) {
            return;
        }

        this.confirmationOpen = true;
    },

    editCancellationRemarks() {
        this.confirmationOpen = false;
    },

    closeCancellation() {
        this.cancellationOpen = false;
        this.confirmationOpen = false;
    },

    handleCancellationEscape() {
        if (this.confirmationOpen) {
            this.editCancellationRemarks();

            return;
        }

        this.closeCancellation();
    },
});
