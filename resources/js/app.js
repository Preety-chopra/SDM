

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
    stationManagerRole: '',
    get requiresDirectStation() {
        return this.directStationRoles.includes(this.role);
    },
    init() {
        this.role = this.$el.dataset.role || '';
        this.directStationRoles = JSON.parse(this.$el.dataset.directStationRoles || '[]');
        this.stationRole = this.$el.dataset.stationRole || '';
        this.stationManagerRole = this.$el.dataset.stationManagerRole || '';
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
