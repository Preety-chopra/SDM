import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const palette = ['#047857', '#0891b2', '#d97706', '#4f46e5', '#be123c', '#0f766e', '#7c3aed', '#0369a1'];

export function createDashboardCharts(config) {
    return {
        charts: [],
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
            const total = config.serviceDistribution.counts.reduce((sum, count) => sum + count, 0);
            const centerTotal = {
                id: 'centerTotal',
                beforeDraw(chart) {
                    const { ctx, chartArea } = chart;
                    if (!chartArea) return;
                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillStyle = '#111827';
                    ctx.font = '700 24px sans-serif';
                    ctx.fillText(String(total), (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2 - 7);
                    ctx.fillStyle = '#6b7280';
                    ctx.font = '500 11px sans-serif';
                    ctx.fillText('TOTAL', (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2 + 15);
                    ctx.restore();
                },
            };

            this.charts.push(new Chart(this.$refs.service, {
                type: 'doughnut',
                plugins: [centerTotal],
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
                        this.applyFilters({
                            service_type: config.workflowByService.service_types[element.index],
                            status: chart.data.datasets[element.datasetIndex].status,
                        });
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
                        this.applyFilters({ aging: config.aging.values[elements[0].index] });
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
