export function createLineSectionsForm() {
    return {
        stations: [],
        sections: [],
        ready: false,
        init() {
            this.stations = JSON.parse(this.$el.dataset.stations || '[]');
            this.sections = JSON.parse(this.$el.dataset.sections || '[]').map((section) => ({
                ...section,
                station_ids: (section.station_ids || []).map(String),
                from_station_id: '',
                to_station_id: '',
            }));
            this.ready = true;
        },
        addSection() {
            this.sections.push({ code: '', name: '', station_ids: [], from_station_id: '', to_station_id: '' });
        },
        removeSection(index) {
            this.sections.splice(index, 1);
        },
        rangeStations() {
            return this.stations.filter((station) => Number.isFinite(station.sequence_no))
                .sort((first, second) => first.sequence_no - second.sequence_no || first.id - second.id);
        },
        selectRange(section) {
            const from = this.stations.find((station) => String(station.id) === section.from_station_id);
            const to = this.stations.find((station) => String(station.id) === section.to_station_id);
            if (!Number.isFinite(from?.sequence_no) || !Number.isFinite(to?.sequence_no)) return;
            section.station_ids = this.rangeStations()
                .filter((station) => station.sequence_no >= Math.min(from.sequence_no, to.sequence_no)
                    && station.sequence_no <= Math.max(from.sequence_no, to.sequence_no))
                .map((station) => String(station.id));
        },
        assignedCount() {
            return new Set(this.sections.flatMap((section) => section.station_ids)).size;
        },
    };
}
