import { createSdmConfirmation } from './sdm-confirmation.js';
import { createStaffLookup } from './staff-lookup.js';

export function createMemoForm() {
    return {
        ...createSdmConfirmation(),
        ...createStaffLookup(),
        get staffNameReadOnly() {
            return ['loading', 'found', 'ambiguous'].includes(this.staffLookupStatus);
        },
        selected: [],
        optionsByType: {},
        serviceType: '',
        contractorId: '',
        contractors: [],
        contractorContracts: [],
        stationId: '',
        incidentDate: '',
        restoredContractorId: '',
        deficiencyOpen: false,
        bookedShiftFrom: '',
        bookedShiftTo: '',
        actualReportingTime: '',
        init() {
            this.selected = JSON.parse(this.$el.dataset.selectedDeficiencies || '[]');
            this.optionsByType = JSON.parse(this.$el.dataset.deficiencyOptions || '{}');
            this.serviceType = this.$el.dataset.serviceType || '';
            this.contractorId = this.$el.dataset.contractorId || '';
            this.restoredContractorId = this.contractorId;
            this.contractors = JSON.parse(this.$el.dataset.contractors || '[]');
            this.contractorContracts = JSON.parse(this.$el.dataset.contractorContracts || '[]');
            this.stationId = this.$el.dataset.stationId || '';
            this.incidentDate = this.$el.dataset.incidentDate || '';
            if (!this.contractorId) {
                this.syncContractor();
            }
            this.bookedShiftFrom = this.$el.dataset.bookedShiftFrom || '';
            this.bookedShiftTo = this.$el.dataset.bookedShiftTo || '';
            this.actualReportingTime = this.$el.dataset.actualReportingTime || '';
            this.initStaffLookup();
        },
        has(type) {
            return this.selected.includes(type);
        },
        removeDeficiency(type) {
            this.selected = this.selected.filter((selectedType) => selectedType !== type);
        },
        get availableDeficiencies() {
            return this.optionsByType[this.serviceType] || [];
        },
        get matchingContractorIds() {
            if (!this.serviceType || !this.incidentDate) {
                return [];
            }

            return [...new Set(this.contractorContracts
                .filter((contract) => contract.service_type === this.serviceType
                    && (!contract.station_ids || (this.stationId && (contract.station_ids.length === 0
                        || contract.station_ids.map(String).includes(String(this.stationId)))))
                    && contract.effective_from <= this.incidentDate
                    && contract.effective_to >= this.incidentDate)
                .map((contract) => String(contract.contractor_id)))];
        },
        get availableContractors() {
            return this.contractors.filter((contractor) => this.matchingContractorIds.includes(String(contractor.id))
                || (this.restoredContractorId && String(contractor.id) === this.restoredContractorId));
        },
        syncContractor() {
            this.restoredContractorId = '';
            const ids = this.availableContractors.map((contractor) => String(contractor.id));
            if (ids.length === 1) {
                this.contractorId = ids[0];
            } else if (!ids.includes(this.contractorId)) {
                this.contractorId = '';
            }
        },
        syncSelectedDeficiencies() {
            const availableValues = this.availableDeficiencies.map((option) => option.value);
            this.selected = this.selected.filter((type) => availableValues.includes(type));
            this.deficiencyOpen = false;
        },
        labelFor(type) {
            const option = this.availableDeficiencies.find((candidate) => candidate.value === type)
                || Object.values(this.optionsByType)
                    .flat()
                    .find((candidate) => candidate.value === type);

            return option?.label || type;
        },
        get delayLabel() {
            if (!this.bookedShiftFrom || !this.actualReportingTime) {
                return '';
            }

            const minutes = (time) => {
                const [hours, mins] = time.split(':').map(Number);
                return (hours * 60) + mins;
            };
            const delay = Math.max(0, minutes(this.actualReportingTime) - minutes(this.bookedShiftFrom));

            if (delay < 60) {
                return `${delay} ${delay === 1 ? 'minute' : 'minutes'}`;
            }

            const hours = Math.floor(delay / 60);
            const remainingMinutes = delay % 60;
            return `${hours} ${hours === 1 ? 'hour' : 'hours'}${remainingMinutes ? ` ${remainingMinutes} ${remainingMinutes === 1 ? 'minute' : 'minutes'}` : ''}`;
        },
    };
}
