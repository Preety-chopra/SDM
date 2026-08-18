

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('userForm', () => ({
    role: '',
    stationRole: '',
    init() {
        this.role = this.$el.dataset.role || '';
        this.stationRole = this.$el.dataset.stationRole || '';
    },
}));

Alpine.data('memoForm', () => ({
    selected: [],
    labels: {},
    serviceType: '',
    deficiencyOpen: false,
    init() {
        this.selected = JSON.parse(this.$el.dataset.selectedDeficiencies || '[]');
        this.labels = JSON.parse(this.$el.dataset.deficiencyLabels || '{}');
        this.serviceType = this.$el.dataset.serviceType || '';
    },
    has(type) {
        return this.selected.includes(type);
    },
    labelFor(type) {
        return this.labels[type] || type;
    },
}));

Alpine.start();
