export function createContractForm() {
    return {
        lineIds: [],
        stationIds: [],
        stations: [],
        stationSearch: '',

        init() {
            this.lineIds = JSON.parse(this.$el.dataset.lineIds || '[]').map(String);
            this.stationIds = JSON.parse(this.$el.dataset.stationIds || '[]').map(String);
            this.stations = JSON.parse(this.$el.dataset.stations || '[]');
        },

        stationsForLine(lineId) {
            return this.stations.filter((station) => String(station.line_id) === String(lineId));
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
        },

        clearLineStations(lineId) {
            const lineStationIds = this.stationsForLine(lineId).map((station) => String(station.id));
            this.stationIds = this.stationIds.filter((stationId) => ! lineStationIds.includes(String(stationId)));
        },

        pruneStations() {
            this.stationIds = this.stationIds.filter((stationId) => this.stationMatchesSelectedLine(stationId));
        },
    };
}
