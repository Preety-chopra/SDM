export function createStaffLookup() {
    return {
        staffIdentifier: '',
        staffName: '',
        staffLookupUrl: '',
        staffCsrfToken: '',
        staffLookupStatus: 'idle',
        staffLookupMessage: '',
        staffLookupSequence: 0,
        initStaffLookup() {
            this.staffIdentifier = this.$el.dataset.staffIdentifier || '';
            this.staffName = this.$el.dataset.staffName || '';
            this.staffLookupUrl = this.$el.dataset.staffLookupUrl || '';
            this.staffCsrfToken = this.$el.querySelector?.('input[name="_token"]')?.value || '';
            this.$watch?.('contractorId', async () => {
                this.resetStaffLookup();
                await this.lookupStaff();
            });
        },
        resetStaffLookup() {
            this.staffLookupSequence++;
            this.staffName = '';
            this.staffLookupStatus = 'idle';
            this.staffLookupMessage = '';
        },
        async lookupStaff() {
            const identifier = this.staffIdentifier.trim();
            const contractorId = this.contractorId;
            const sequence = ++this.staffLookupSequence;
            if (!identifier || !this.staffLookupUrl) {
                return;
            }
            if (!contractorId) {
                this.staffLookupMessage = 'Select the contractor to look up outsourced staff.';
                return;
            }
            this.staffLookupStatus = 'loading';
            this.staffLookupMessage = 'Looking up outsourced staff…';
            try {
                const response = await fetch(this.staffLookupUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.staffCsrfToken,
                    },
                    body: JSON.stringify({ staff_identifier: identifier, contractor_id: contractorId }),
                });
                if (!response.ok) {
                    throw new Error('Staff lookup failed');
                }
                const result = await response.json();
                if (sequence !== this.staffLookupSequence || contractorId !== this.contractorId) {
                    return;
                }
                if (result.status === 'found') {
                    this.staffIdentifier = result.employee_number;
                    this.staffName = result.staff_name;
                    this.staffLookupStatus = 'found';
                    this.staffLookupMessage = 'Staff found. The registered name has been filled automatically.';
                } else if (result.status === 'ambiguous') {
                    this.staffName = '';
                    this.staffLookupStatus = 'ambiguous';
                    this.staffLookupMessage = 'More than one staff member matches. Enter the EMP ID instead.';
                } else if (result.status === 'missing') {
                    this.staffLookupStatus = 'missing';
                    this.staffLookupMessage = 'No staff found for this contractor. Enter the staff name manually.';
                    if (/^[0-9]{12}$/.test(identifier.replace(/[\s-]+/g, ''))) {
                        this.staffLookupMessage += ' Aadhaar will be masked on the SDM.';
                    }
                } else {
                    throw new Error('Unexpected staff lookup response');
                }
            } catch {
                if (sequence === this.staffLookupSequence && contractorId === this.contractorId) {
                    this.staffLookupStatus = 'error';
                    this.staffLookupMessage = 'Unable to look up staff. Try again or enter the staff name manually.';
                }
            }
        },
    };
}
