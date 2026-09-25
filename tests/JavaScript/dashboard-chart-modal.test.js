import test from 'node:test';
import assert from 'node:assert/strict';

import { buildChartMemosUrl } from '../../resources/js/dashboard-charts.js';

test('chart modal URL combines dashboard filters with the selected bar filters', () => {
    const url = buildChartMemosUrl(
        'https://sdm.test/dashboard/chart-sdms',
        {
            date_from: '2026-09-01',
            date_to: '2026-09-30',
            service_type: '',
            status: '',
        },
        {
            service_type: 'cfs',
            status: 'line_review',
        },
        'https://sdm.test',
    );

    assert.equal(url.searchParams.get('date_from'), '2026-09-01');
    assert.equal(url.searchParams.get('date_to'), '2026-09-30');
    assert.equal(url.searchParams.get('service_type'), 'cfs');
    assert.equal(url.searchParams.get('status'), 'line_review');
});

test('selected bar filters replace dashboard filters and empty values are omitted', () => {
    const url = buildChartMemosUrl(
        '/dashboard/chart-sdms',
        { service_type: 'ticketing', status: 'closed', aging: '' },
        { status: 'am_cmc_review', aging: '3_7' },
        'https://sdm.test',
    );

    assert.equal(url.origin, 'https://sdm.test');
    assert.equal(url.searchParams.get('service_type'), 'ticketing');
    assert.equal(url.searchParams.get('status'), 'am_cmc_review');
    assert.equal(url.searchParams.get('aging'), '3_7');
    assert.equal(url.searchParams.has('serial_number'), false);
});
