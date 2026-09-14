

import Alpine from 'alpinejs';

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
        const employeeNumber = this.query.trim().toLowerCase();

        if (!employeeNumber) {
            return this.candidates;
        }

        return this.candidates.filter((candidate) => candidate.employee_number.toLowerCase().includes(employeeNumber));
    },
    matches(employeeNumber) {
        return employeeNumber.toLowerCase().includes(this.query.trim().toLowerCase());
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
    selected: [],
    labels: {},
    serviceType: '',
    deficiencyOpen: false,
    bookedShiftFrom: '',
    bookedShiftTo: '',
    actualReportingTime: '',
    init() {
        this.selected = JSON.parse(this.$el.dataset.selectedDeficiencies || '[]');
        this.labels = JSON.parse(this.$el.dataset.deficiencyLabels || '{}');
        this.serviceType = this.$el.dataset.serviceType || '';
        this.bookedShiftFrom = this.$el.dataset.bookedShiftFrom || '';
        this.bookedShiftTo = this.$el.dataset.bookedShiftTo || '';
        this.actualReportingTime = this.$el.dataset.actualReportingTime || '';
    },
    has(type) {
        return this.selected.includes(type);
    },
    labelFor(type) {
        return this.labels[type] || type;
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

Alpine.start();
