

import Alpine from 'alpinejs';
import { createDashboardCharts, createLineManagerDashboardCharts, createStationManagerDashboardCharts, createWorkflowRoleDashboardCharts } from './dashboard-charts';
import { createSdmConfirmation } from './sdm-confirmation';
import { createBulkMemoSelection } from './bulk-memo-selection';

window.Alpine = Alpine;

Alpine.data('appShell', () => ({
    collapsed: false,
    mobileOpen: false,
    init() {
        this.collapsed = window.localStorage.getItem('sdm-sidebar-collapsed') === 'true';
    },
    toggleSidebar() {
        this.collapsed = !this.collapsed;
        window.localStorage.setItem('sdm-sidebar-collapsed', String(this.collapsed));
    },
}));

Alpine.data('userForm', () => ({
    role: '',
    directStationRoles: [],
    stationRole: '',
    get requiresDirectStation() {
        return this.directStationRoles.includes(this.role);
    },
    init() {
        this.role = this.$el.dataset.role || '';
        this.directStationRoles = JSON.parse(this.$el.dataset.directStationRoles || '[]');
        this.stationRole = this.$el.dataset.stationRole || '';
    },
}));

Alpine.data('contractForm', () => ({
    lineId: '',
    stationId: '',
    init() {
        this.lineId = this.$el.dataset.lineId || '';
        this.stationId = this.$el.dataset.stationId || '';
    },
}));

Alpine.data('employeeAutocomplete', () => ({
    candidates: [],
    query: '',
    open: false,
    init() {
        this.candidates = JSON.parse(this.$el.dataset.candidates || '[]');
        this.query = this.$el.dataset.initialValue || '';
    },
    get filteredCandidates() {
        const query = this.query.trim().toLowerCase();

        if (!query) {
            return this.candidates;
        }

        return this.candidates.filter((candidate) => (
            candidate.employee_number.toLowerCase().includes(query)
            || candidate.name.toLowerCase().includes(query)
        ));
    },
    get previewValue() {
        const employeeNumber = this.query.trim();
        const candidate = this.candidates.find((item) => item.employee_number === employeeNumber);

        return candidate ? `${candidate.name}(${candidate.employee_number})` : employeeNumber;
    },
    matches(employeeNumber, name) {
        const query = this.query.trim().toLowerCase();

        return employeeNumber.toLowerCase().includes(query) || name.toLowerCase().includes(query);
    },
    select(employeeNumber) {
        this.query = employeeNumber;
        this.open = false;
    },
    close() {
        this.open = false;
    },
}));

Alpine.data('memoForm', () => ({
    ...createSdmConfirmation(),
    selected: [],
    optionsByType: {},
    serviceType: '',
    deficiencyOpen: false,
    bookedShiftFrom: '',
    bookedShiftTo: '',
    actualReportingTime: '',
    init() {
        this.selected = JSON.parse(this.$el.dataset.selectedDeficiencies || '[]');
        this.optionsByType = JSON.parse(this.$el.dataset.deficiencyOptions || '{}');
        this.serviceType = this.$el.dataset.serviceType || '';
        this.bookedShiftFrom = this.$el.dataset.bookedShiftFrom || '';
        this.bookedShiftTo = this.$el.dataset.bookedShiftTo || '';
        this.actualReportingTime = this.$el.dataset.actualReportingTime || '';
    },
    has(type) {
        return this.selected.includes(type);
    },
    get availableDeficiencies() {
        return this.optionsByType[this.serviceType] || [];
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
}));

Alpine.data('sdmActionForm', () => createSdmConfirmation());
Alpine.data('bulkMemoSelection', () => createBulkMemoSelection());
Alpine.data('dashboardCharts', (config) => createDashboardCharts(config));
Alpine.data('stationManagerDashboardCharts', (config) => createStationManagerDashboardCharts(config));
Alpine.data('lineManagerDashboardCharts', (config) => createLineManagerDashboardCharts(config));
Alpine.data('workflowRoleDashboardCharts', (config) => createWorkflowRoleDashboardCharts(config));

Alpine.start();
