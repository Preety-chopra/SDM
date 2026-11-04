

import Alpine from 'alpinejs';
import { createDashboardCharts, createLineManagerDashboardCharts, createStationManagerDashboardCharts, createWorkflowRoleDashboardCharts } from './dashboard-charts';
import { createSdmConfirmation } from './sdm-confirmation';
import { createBulkMemoSelection } from './bulk-memo-selection';
import { createCmcMemoSelection } from './cmc-memo-selection';
import { createMemoCancellationDialog } from './memo-cancellation-dialog';
import { createContractForm } from './contract-form';
import { createMemoForm } from './memo-form';
import { createSdmActionFeedback } from './sdm-action-feedback';
import { createFeedbackForm } from './feedback-form';

window.Alpine = Alpine;

Alpine.data('feedbackForm', () => createFeedbackForm());

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

Alpine.data('contractForm', () => createContractForm());

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

Alpine.data('memoForm', () => createMemoForm());
Alpine.data('sdmActionFeedback', () => createSdmActionFeedback());

Alpine.data('sdmActionForm', () => createSdmConfirmation());
Alpine.data('bulkMemoSelection', () => createBulkMemoSelection());
Alpine.data('cmcMemoSelection', () => createCmcMemoSelection());
Alpine.data('memoCancellationDialog', () => createMemoCancellationDialog());
Alpine.data('dashboardCharts', (config) => createDashboardCharts(config));
Alpine.data('stationManagerDashboardCharts', (config) => createStationManagerDashboardCharts(config));
Alpine.data('lineManagerDashboardCharts', (config) => createLineManagerDashboardCharts(config));
Alpine.data('workflowRoleDashboardCharts', (config) => createWorkflowRoleDashboardCharts(config));

Alpine.start();
