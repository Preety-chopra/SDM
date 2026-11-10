export function createAftCashCheckForm() {
    return {
        entries: [],
        stations: [],
        today: '',
        nextKey: 0,
        init() {
            this.today = this.$el.dataset.today || '';
            this.stations = JSON.parse(this.$el.dataset.stations || '[]');
            this.entries = JSON.parse(this.$el.dataset.entries || '[]').map((entry) => this.row(entry));
            if (this.entries.length === 0) this.add();
        },
        row(values = {}) {
            return {
                check_date: this.today, station_id: '', check_type: 'SURP', tom_number: '', check_time: '',
                operator_name: '', employee_number: '', employer: '', eos_number: '',
                cash_as_per_eos: '', actual_cash: '', hidden_amount: '0.00', cash_deposit_at_station: '0.00',
                ...values, _key: this.nextKey++,
            };
        },
        add() {
            if (this.entries.length < 100) this.entries.push(this.row());
        },
        remove(index) {
            if (this.entries.length > 1) this.entries.splice(index, 1);
        },
        lineFor(row) {
            return this.stations.find((station) => String(station.id) === String(row.station_id))?.line_code || '—';
        },
        cashSummary(row) {
            const cents = (amount) => {
                if (!/^\d{1,12}(?:\.\d{1,2})?$/.test(String(amount ?? ''))) return null;
                const [whole, fraction = ''] = String(amount).split('.');
                return (Number(whole) * 100) + Number(fraction.padEnd(2, '0'));
            };
            const actual = cents(row.actual_cash);
            const expected = cents(row.cash_as_per_eos);
            if (actual === null || expected === null) return { amount: '—', status: 'Awaiting cash amounts' };
            const difference = actual - expected;
            const absolute = Math.abs(difference);
            const amount = `${difference < 0 ? '-' : ''}${Math.floor(absolute / 100)}.${String(absolute % 100).padStart(2, '0')}`;
            return { amount, status: difference === 0 ? 'OK' : (difference < 0 ? 'SHORT' : 'EXCESS') };
        },
    };
}
