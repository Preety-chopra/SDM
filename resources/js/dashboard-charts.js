import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const palette = ['#047857', '#0891b2', '#d97706', '#4f46e5', '#be123c', '#0f766e', '#7c3aed', '#0369a1'];

export function buildChartMemosUrl(endpoint, dashboardFilters, selectedFilters, origin = window.location.origin) {
    const url = new URL(endpoint, origin);

    Object.entries({ ...dashboardFilters, ...selectedFilters }).forEach(([key, value]) => {
        if (value) url.searchParams.set(key, value);
    });

    return url;
}

function chartMemoModal(config) {
    return {
        chartModalOpen: false,
        chartModalLoading: false,
        chartModalError: '',
        chartMemos: [],
        chartMemosTruncated: false,
        chartModalTitle: '',
        chartModalTrigger: null,
        async openChartModal(title, selectedFilters) {
            this.chartModalTrigger = document.activeElement;
            this.chartModalTitle = title;
            this.chartModalOpen = true;
            this.chartModalLoading = true;
            this.chartModalError = '';
            this.chartMemos = [];
            this.chartMemosTruncated = false;

            this.$nextTick(() => this.$refs.chartModalClose?.focus());

            const url = buildChartMemosUrl(config.chartMemosUrl, config.filters, selectedFilters);

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) throw new Error('Unable to load the SDMs for this chart bar.');

                const payload = await response.json();
                this.chartMemos = payload.memos;
                this.chartMemosTruncated = payload.has_more;
            } catch (error) {
                this.chartModalError = error.message || 'Unable to load the SDMs for this chart bar.';
            } finally {
                this.chartModalLoading = false;
            }
        },
        closeChartModal() {
            this.chartModalOpen = false;
            this.chartModalTrigger?.focus?.();
        },
    };
}

function centerTotalPlugin(id) {
    return {
        id: `centerTotal-${id}`,
        beforeDraw(chart) {
            const { ctx, chartArea } = chart;
            if (!chartArea) return;

            const dataset = chart.data.datasets[0];
            const total = dataset.data.reduce((sum, value) => sum + Number(value || 0), 0);
            const centerX = (chartArea.left + chartArea.right) / 2;
            const centerY = (chartArea.top + chartArea.bottom) / 2;
            const darkMode = document.documentElement.classList.contains('dark');

            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = darkMode ? '#f9fafb' : '#111827';
            ctx.font = '700 23px sans-serif';
            ctx.fillText(String(total), centerX, centerY - 7);
            ctx.fillStyle = darkMode ? '#d1d5db' : '#6b7280';
            ctx.font = '500 11px sans-serif';
            ctx.fillText('TOTAL', centerX, centerY + 15);
            ctx.restore();
        },
    };
}

export function formatCountLabel(value) {
    return String(Number(value || 0));
}

function segmentLabelsPlugin(id, formatLabel) {
    return {
        id: `segmentLabels-${id}`,
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const dataset = chart.data.datasets[0];
            const total = dataset.data.reduce((sum, value) => sum + Number(value || 0), 0);
            if (!total) return;

            chart.getDatasetMeta(0).data.forEach((arc, index) => {
                const value = Number(dataset.data[index] || 0);
                if (!value) return;

                const angle = (arc.startAngle + arc.endAngle) / 2;
                const radius = (arc.innerRadius + arc.outerRadius) / 2;
                const x = arc.x + (Math.cos(angle) * radius);
                const y = arc.y + (Math.sin(angle) * radius);
                const label = formatLabel(value, total);

                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = '#ffffff';
                ctx.strokeStyle = 'rgba(17, 24, 39, 0.55)';
                ctx.lineWidth = 3;
                ctx.lineJoin = 'round';
                ctx.font = '700 12px sans-serif';
                ctx.strokeText(label, x, y);
                ctx.fillText(label, x, y);
                ctx.restore();
            });
        },
    };
}

function segmentPercentageLabelsPlugin(id) {
    return segmentLabelsPlugin(`percentage-${id}`, (value, total) => {
        const percentage = (value / total) * 100;

        return `${Number.isInteger(percentage) ? percentage : percentage.toFixed(1)}%`;
    });
}

function segmentCountLabelsPlugin(id) {
    return segmentLabelsPlugin(`count-${id}`, formatCountLabel);
}

export function createDashboardCharts(config) {
    return {
        charts: [],
        ...chartMemoModal(config),
        init() {
            this.$nextTick(() => {
                this.renderTrend();
                this.renderServiceDistribution();
                this.renderWorkflow();
                this.renderAging();
            });
        },
        destroy() {
            this.charts.forEach((chart) => chart.destroy());
        },
        applyFilters(filters) {
            const url = new URL(window.location.href);
            url.searchParams.delete('filled_page');

            Object.entries(filters).forEach(([key, value]) => {
                if (value) {
                    url.searchParams.set(key, value);
                } else {
                    url.searchParams.delete(key);
                }
            });

            window.location.assign(url.toString());
        },
        renderTrend() {
            const datasets = config.monthlyTrend.datasets.map((dataset, index) => ({
                label: dataset.label,
                data: dataset.data,
                serviceType: dataset.service_type,
                borderColor: palette[index % palette.length],
                backgroundColor: palette[index % palette.length],
                borderWidth: 2.5,
                pointRadius: 3,
                pointHoverRadius: 5,
                tension: 0.3,
            }));

            this.charts.push(new Chart(this.$refs.trend, {
                type: 'line',
                data: { labels: config.monthlyTrend.labels, datasets },
                options: {
                    ...this.baseOptions(),
                    interaction: { intersect: false, mode: 'index' },
                    onClick: (_event, elements, chart) => {
                        if (!elements.length) return;
                        this.applyFilters({ service_type: chart.data.datasets[elements[0].datasetIndex].serviceType });
                    },
                    scales: this.cartesianScales(false),
                    plugins: {
                        ...this.baseOptions().plugins,
                        tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${context.parsed.y} SDMs` } },
                    },
                },
            }));
        },
        renderServiceDistribution() {
            this.charts.push(new Chart(this.$refs.service, {
                type: 'doughnut',
                plugins: [
                    centerTotalPlugin('station-controller-service'),
                    segmentPercentageLabelsPlugin('station-controller-service'),
                ],
                data: {
                    labels: config.serviceDistribution.labels,
                    datasets: [{
                        data: config.serviceDistribution.counts,
                        backgroundColor: palette,
                        borderColor: '#ffffff',
                        borderWidth: 3,
                    }],
                },
                options: {
                    ...this.baseOptions(),
                    cutout: '68%',
                    onClick: (_event, elements) => {
                        if (!elements.length) return;
                        this.applyFilters({ service_type: config.serviceDistribution.service_types[elements[0].index] });
                    },
                    plugins: {
                        ...this.baseOptions().plugins,
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${context.label}: ${context.parsed} (${config.serviceDistribution.percentages[context.dataIndex]}%)`,
                            },
                        },
                    },
                },
            }));
        },
        renderWorkflow() {
            const datasets = config.workflowByService.datasets.map((dataset, index) => ({
                label: dataset.label,
                data: dataset.data,
                status: dataset.status,
                backgroundColor: palette[index % palette.length],
                borderRadius: 4,
                borderSkipped: false,
            }));

            this.charts.push(new Chart(this.$refs.workflow, {
                type: 'bar',
                data: { labels: config.workflowByService.labels, datasets },
                options: {
                    ...this.baseOptions(),
                    indexAxis: 'y',
                    onClick: (_event, elements, chart) => {
                        if (!elements.length) return;
                        const element = elements[0];
                        const serviceType = config.workflowByService.service_types[element.index];
                        const dataset = chart.data.datasets[element.datasetIndex];
                        this.openChartModal(`${config.workflowByService.labels[element.index]} · ${dataset.label}`, {
                            service_type: serviceType,
                            status: dataset.status,
                            aging: '',
                        });
                    },
                    onHover: (event, elements) => {
                        if (event.native?.target) {
                            event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                        }
                    },
                    scales: this.cartesianScales(true),
                    plugins: {
                        ...this.baseOptions().plugins,
                        tooltip: {
                            callbacks: {
                                label: (context) => `${context.label} · ${context.dataset.label}: ${context.parsed.x}`,
                            },
                        },
                    },
                },
            }));
        },
        renderAging() {
            this.charts.push(new Chart(this.$refs.aging, {
                type: 'bar',
                data: {
                    labels: config.aging.labels,
                    datasets: [{
                        label: 'Pending SDMs',
                        data: config.aging.counts,
                        backgroundColor: ['#10b981', '#0d9488', '#d97706', '#be123c'],
                        borderRadius: 5,
                        borderSkipped: false,
                    }],
                },
                options: {
                    ...this.baseOptions(),
                    indexAxis: 'y',
                    onClick: (_event, elements) => {
                        if (!elements.length) return;
                        const index = elements[0].index;
                        this.openChartModal(`${config.aging.labels[index]} pending SDMs`, {
                            aging: config.aging.values[index],
                        });
                    },
                    onHover: (event, elements) => {
                        if (event.native?.target) {
                            event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                        }
                    },
                    scales: this.cartesianScales(false),
                    plugins: {
                        ...this.baseOptions().plugins,
                        legend: { display: false },
                        tooltip: { callbacks: { label: (context) => `${context.parsed.x} pending SDMs` } },
                    },
                },
            }));
        },
        baseOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 250 },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 16 },
                    },
                },
            };
        },
        cartesianScales(stacked) {
            return {
                x: {
                    stacked,
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: '#e5e7eb' },
                },
                y: {
                    stacked,
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { display: false },
                },
            };
        },
    };
}

export function createStationManagerDashboardCharts(config) {
    const doughnutOptions = () => ({
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 14 },
            },
        },
    });

    return {
        charts: [],
        init() {
            this.$nextTick(() => {
                this.charts.push(new Chart(this.$refs.managerTrend, {
                    type: 'line',
                    data: {
                        labels: config.monthlyTrend.labels,
                        datasets: config.monthlyTrend.datasets.map((dataset, index) => ({
                            label: dataset.label,
                            data: dataset.data,
                            borderColor: palette[index % palette.length],
                            backgroundColor: palette[index % palette.length],
                            fill: false,
                            borderWidth: 2.5,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            tension: 0.3,
                        })),
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 16 },
                            },
                            tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${context.parsed.y} SDMs` } },
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e5e7eb' } },
                        },
                    },
                }));

                this.charts.push(new Chart(this.$refs.managerStations, {
                    type: 'doughnut',
                    plugins: [
                        centerTotalPlugin('station-manager-stations'),
                        segmentCountLabelsPlugin('station-manager-stations'),
                    ],
                    data: {
                        labels: config.stationDistribution.labels,
                        datasets: [{ data: config.stationDistribution.counts, backgroundColor: palette, borderColor: '#ffffff', borderWidth: 3 }],
                    },
                    options: doughnutOptions(),
                }));

                this.charts.push(new Chart(this.$refs.managerServices, {
                    type: 'doughnut',
                    plugins: [
                        centerTotalPlugin('station-manager-services'),
                        segmentPercentageLabelsPlugin('station-manager-services'),
                    ],
                    data: {
                        labels: config.serviceDistribution.labels,
                        datasets: [{ data: config.serviceDistribution.counts, backgroundColor: palette, borderColor: '#ffffff', borderWidth: 3 }],
                    },
                    options: doughnutOptions(),
                }));
            });
        },
        destroy() {
            this.charts.forEach((chart) => chart.destroy());
        },
    };
}

export function createLineManagerDashboardCharts(config) {
    return {
        charts: [],
        lineModalOpen: false,
        lineModalLoading: false,
        lineModalError: '',
        lineMemos: [],
        lineMemosTruncated: false,
        selectedLineCode: '',
        lineModalTrigger: null,
        init() {
            this.$nextTick(() => {
                this.charts.push(new Chart(this.$refs.lineManagerTrend, {
                    type: 'line',
                    data: {
                        labels: config.monthlyTrend.labels,
                        datasets: config.monthlyTrend.datasets.map((dataset, index) => ({
                            label: dataset.label,
                            data: dataset.data,
                            borderColor: palette[index % palette.length],
                            backgroundColor: palette[index % palette.length],
                            borderWidth: 2.5,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            tension: 0.3,
                        })),
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 16 },
                            },
                            tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${context.parsed.y} SDMs` } },
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e5e7eb' } },
                        },
                    },
                }));

                this.charts.push(new Chart(this.$refs.lineManagerServices, {
                    type: 'doughnut',
                    plugins: [
                        centerTotalPlugin('line-manager-services'),
                        segmentPercentageLabelsPlugin('line-manager-services'),
                    ],
                    data: {
                        labels: config.serviceDistribution.labels,
                        datasets: [{
                            data: config.serviceDistribution.counts,
                            backgroundColor: palette,
                            borderColor: '#ffffff',
                            borderWidth: 3,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '62%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 14 },
                            },
                        },
                    },
                }));

                this.charts.push(new Chart(this.$refs.lineManagerLines, {
                    type: 'bar',
                    data: {
                        labels: config.lineDistribution.labels,
                        datasets: [{
                            label: 'Received SDMs',
                            data: config.lineDistribution.counts,
                            backgroundColor: config.lineDistribution.colors,
                            borderRadius: 6,
                            borderSkipped: false,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        onClick: (_event, elements) => {
                            if (!elements.length) return;

                            this.openLineModal(config.lineDistribution.labels[elements[0].index]);
                        },
                        onHover: (event, elements) => {
                            if (event.native?.target) {
                                event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                            }
                        },
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e5e7eb' } },
                        },
                    },
                }));
            });
        },
        destroy() {
            this.charts.forEach((chart) => chart.destroy());
        },
        async openLineModal(lineCode) {
            this.lineModalTrigger = document.activeElement;
            this.selectedLineCode = lineCode;
            this.lineModalOpen = true;
            this.lineModalLoading = true;
            this.lineModalError = '';
            this.lineMemos = [];
            this.lineMemosTruncated = false;

            this.$nextTick(() => this.$refs.lineModalClose?.focus());

            const url = new URL(config.lineMemosUrl, window.location.origin);
            url.searchParams.set('line_code', lineCode);

            Object.entries(config.filters).forEach(([key, value]) => {
                if (value) url.searchParams.set(key, value);
            });

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) throw new Error('Unable to load the SDMs for this line.');

                const payload = await response.json();
                this.lineMemos = payload.memos;
                this.lineMemosTruncated = payload.has_more;
            } catch (error) {
                this.lineModalError = error.message || 'Unable to load the SDMs for this line.';
            } finally {
                this.lineModalLoading = false;
            }
        },
        closeLineModal() {
            this.lineModalOpen = false;
            this.lineModalTrigger?.focus?.();
        },
    };
}

export function createWorkflowRoleDashboardCharts(config) {
    const baseOptions = () => ({
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 14 },
            },
        },
    });

    return {
        charts: [],
        ...chartMemoModal(config),
        init() {
            this.$nextTick(() => {
                this.charts.push(new Chart(this.$refs.workflowRoleTrend, {
                    type: 'line',
                    data: {
                        labels: config.monthlyTrend.labels,
                        datasets: config.monthlyTrend.datasets.map((dataset, index) => ({
                            label: dataset.label,
                            data: dataset.data,
                            borderColor: palette[index % palette.length],
                            backgroundColor: palette[index % palette.length],
                            borderWidth: 2.5,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            tension: 0.3,
                        })),
                    },
                    options: {
                        ...baseOptions(),
                        interaction: { intersect: false, mode: 'index' },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e5e7eb' } },
                        },
                    },
                }));

                this.charts.push(new Chart(this.$refs.workflowRoleServices, {
                    type: 'doughnut',
                    plugins: [
                        centerTotalPlugin('workflow-role-services'),
                        segmentPercentageLabelsPlugin('workflow-role-services'),
                    ],
                    data: {
                        labels: config.serviceDistribution.labels,
                        datasets: [{
                            data: config.serviceDistribution.counts,
                            backgroundColor: palette,
                            borderColor: '#ffffff',
                            borderWidth: 3,
                        }],
                    },
                    options: { ...baseOptions(), cutout: '62%' },
                }));

                this.charts.push(new Chart(this.$refs.workflowRoleStatuses, {
                    type: 'bar',
                    data: {
                        labels: config.statusDistribution.labels,
                        datasets: [{
                            label: 'Assigned SDMs',
                            data: config.statusDistribution.counts,
                            backgroundColor: config.statusDistribution.labels.map((_, index) => palette[index % palette.length]),
                            borderRadius: 6,
                            borderSkipped: false,
                        }],
                    },
                    options: {
                        ...baseOptions(),
                        indexAxis: 'y',
                        onClick: (_event, elements) => {
                            if (!elements.length) return;
                            const index = elements[0].index;
                            this.openChartModal(`${config.statusDistribution.labels[index]} SDMs`, {
                                status: config.statusDistribution.statuses[index],
                            });
                        },
                        onHover: (event, elements) => {
                            if (event.native?.target) {
                                event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                            }
                        },
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e5e7eb' } },
                            y: { grid: { display: false } },
                        },
                    },
                }));
            });
        },
        destroy() {
            this.charts.forEach((chart) => chart.destroy());
        },
    };
}
