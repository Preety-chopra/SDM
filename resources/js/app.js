

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
    stationRole: '',
    stationManagerRole: '',
    init() {
        this.role = this.$el.dataset.role || '';
        this.stationRole = this.$el.dataset.stationRole || '';
        this.stationManagerRole = this.$el.dataset.stationManagerRole || '';
    },
}));

Alpine.data('memoForm', () => ({
    selected: [],
    labels: {},
    serviceType: '',
    deficiencyOpen: false,
    bookedShiftFrom: '',
    bookedShiftTo: '',
    selectedShift: 'custom',
    init() {
        this.selected = JSON.parse(this.$el.dataset.selectedDeficiencies || '[]');
        this.labels = JSON.parse(this.$el.dataset.deficiencyLabels || '{}');
        this.serviceType = this.$el.dataset.serviceType || '';
        this.bookedShiftFrom = this.$el.dataset.bookedShiftFrom || '';
        this.bookedShiftTo = this.$el.dataset.bookedShiftTo || '';
        this.syncBookedShiftPreset();
    },
    has(type) {
        return this.selected.includes(type);
    },
    labelFor(type) {
        return this.labels[type] || type;
    },
    applyBookedShiftPreset(shift) {
        const presets = {
            morning: ['05:15', '14:15'],
            evening: ['14:15', '23:15'],
        };

        this.selectedShift = shift;

        if (presets[shift]) {
            [this.bookedShiftFrom, this.bookedShiftTo] = presets[shift];
        }
    },
    syncBookedShiftPreset() {
        if (this.bookedShiftFrom === '05:15' && this.bookedShiftTo === '14:15') {
            this.selectedShift = 'morning';
        } else if (this.bookedShiftFrom === '14:15' && this.bookedShiftTo === '23:15') {
            this.selectedShift = 'evening';
        } else {
            this.selectedShift = 'custom';
        }
    },
}));

Alpine.start();
