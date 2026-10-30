export function createContractForm() {
    return {
        lineIds: [],
        stationIds: [],
        stations: [],
        stationSearch: '',
        stationRanges: {},

        init() {
            this.lineIds = JSON.parse(this.$el.dataset.lineIds || '[]').map(String);
            this.stationIds = JSON.parse(this.$el.dataset.stationIds || '[]').map(String);
            this.stations = JSON.parse(this.$el.dataset.stations || '[]');
            const lineIds = new Set([...this.lineIds, ...this.stations.map((station) => String(station.line_id))]);
            lineIds.forEach((lineId) => this.syncStationRange(lineId));
        },

        stationsForLine(lineId) {
            return this.stations.filter((station) => String(station.line_id) === String(lineId));
        },

        rangeStationsForLine(lineId) {
            return this.stationsForLine(lineId)
                .filter((station) => Number.isFinite(station.sequence_no))
                .sort((first, second) => first.sequence_no - second.sequence_no || first.id - second.id);
        },

        syncStationRange(lineId) {
            const stations = this.rangeStationsForLine(lineId);
            const selected = stations.filter((station) => this.stationIds.includes(String(station.id)));
            this.stationRanges[lineId] = { from: '', till: '' };

            if (selected.length === 0 || selected.length !== this.stationCountForLine(lineId)) {
                return;
            }

            const first = selected[0];
            const last = selected[selected.length - 1];
            const range = stations.filter((station) => station.sequence_no >= first.sequence_no && station.sequence_no <= last.sequence_no);

            if (range.length === selected.length) {
                this.stationRanges[lineId] = { from: String(first.id), till: String(last.id) };
            }
        },

        selectStationRange(lineId) {
            if (! this.lineIds.includes(String(lineId))) {
                return;
            }

            const stations = this.rangeStationsForLine(lineId);
            const range = this.stationRanges[lineId];
            const from = stations.find((station) => String(station.id) === range?.from);
            const till = stations.find((station) => String(station.id) === range?.till);

            if (! from || ! till) {
                return;
            }

            const minimum = Math.min(from.sequence_no, till.sequence_no);
            const maximum = Math.max(from.sequence_no, till.sequence_no);
            const lineStationIds = this.stationsForLine(lineId).map((station) => String(station.id));
            const rangeIds = stations
                .filter((station) => station.sequence_no >= minimum && station.sequence_no <= maximum)
                .map((station) => String(station.id));

            this.stationIds = [
                ...this.stationIds.filter((stationId) => ! lineStationIds.includes(String(stationId))),
                ...rangeIds,
            ];
        },

        stationMatchesSelectedLine(stationId) {
            const station = this.stations.find((item) => String(item.id) === String(stationId));

            return station && this.lineIds.includes(String(station.line_id));
        },

        stationMatchesSearch(stationId) {
            const query = this.stationSearch.trim().toLowerCase();

            if (! query) {
                return true;
            }

            const station = this.stations.find((item) => String(item.id) === String(stationId));
            const searchableText = `${station?.code || ''} ${station?.name || ''}`.toLowerCase();

            return searchableText.includes(query);
        },

        stationCountForLine(lineId) {
            const lineStationIds = this.stationsForLine(lineId).map((station) => String(station.id));

            return this.stationIds.filter((stationId) => lineStationIds.includes(String(stationId))).length;
        },

        visibleStationCountForLine(lineId) {
            return this.stationsForLine(lineId)
                .filter((station) => this.stationMatchesSearch(station.id))
                .length;
        },

        allLineStationsSelected(lineId) {
            const lineStationIds = this.stationsForLine(lineId).map((station) => String(station.id));

            return lineStationIds.length > 0
                && lineStationIds.every((stationId) => this.stationIds.includes(stationId));
        },

        selectLineStations(lineId) {
            const lineStationIds = this.stationsForLine(lineId).map((station) => String(station.id));
            this.stationIds = [...new Set([...this.stationIds.map(String), ...lineStationIds])];
            this.syncStationRange(lineId);
        },

        clearLineStations(lineId) {
            const lineStationIds = this.stationsForLine(lineId).map((station) => String(station.id));
            this.stationIds = this.stationIds.filter((stationId) => ! lineStationIds.includes(String(stationId)));
            this.stationRanges[lineId] = { from: '', till: '' };
        },

        pruneStations() {
            this.stationIds = this.stationIds.filter((stationId) => this.stationMatchesSelectedLine(stationId));
            Object.keys(this.stationRanges).forEach((lineId) => {
                if (! this.lineIds.includes(lineId)) {
                    this.stationRanges[lineId] = { from: '', till: '' };
                }
            });
        },
    };
}
