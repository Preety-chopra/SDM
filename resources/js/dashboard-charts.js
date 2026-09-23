import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const palette = ['#047857', '#0891b2', '#d97706', '#4f46e5', '#be123c', '#0f766e', '#7c3aed', '#0369a1'];

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

function segmentPercentageLabelsPlugin(id) {
    return {
        id: `segmentPercentageLabels-${id}`,
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
                const percentage = (value / total) * 100;
                const label = `${Number.isInteger(percentage) ? percentage : percentage.toFixed(1)}%`;

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
                        segmentPercentageLabelsPlugin('station-manager-stations'),
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
