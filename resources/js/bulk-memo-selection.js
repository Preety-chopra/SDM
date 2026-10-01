export function createBulkMemoSelection() {
    return {
        destination: 'am_cmc_to_scrutiny',
        selected: [],
        selectAllFiltered: false,
        destinations: {},

        init() {
            this.destinations = JSON.parse(this.$el.dataset.destinations || '{}');
            this.$watch('destination', () => {
                this.selected = this.selected.filter((id) => this.isAvailable(id));
            });
        },

        availableIds() {
            return Object.entries(this.destinations)
                .filter(([, destination]) => destination === this.destination)
                .map(([id]) => id);
        },

        isAvailable(id) {
            return this.destinations[String(id)] === this.destination;
        },

        get allPageSelected() {
            const ids = this.availableIds();

            return ids.length > 0 && ids.every((id) => this.selected.includes(id));
        },

        togglePage(checked) {
            const availableIds = this.availableIds();
            this.selected = checked
                ? [...new Set([...this.selected, ...availableIds])]
                : this.selected.filter((id) => !availableIds.includes(id));
        },
    };
}
