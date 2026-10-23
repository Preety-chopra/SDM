export function createCmcMemoSelection() {
    return {
        action: 'assign',
        selected: [],
        actions: {},

        init() {
            this.actions = JSON.parse(this.$el.dataset.actions || '{}');
            this.action = this.$el.dataset.initialAction || 'assign';
            this.selected = JSON.parse(this.$el.dataset.selected || '[]').map(String);
            this.$watch('action', () => {
                this.selected = this.selected.filter((id) => this.isAvailable(id));
            });
        },

        availableIds() {
            return Object.entries(this.actions)
                .filter(([, actions]) => actions.includes(this.action))
                .map(([id]) => id);
        },

        isAvailable(id) {
            return (this.actions[String(id)] || []).includes(this.action);
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
