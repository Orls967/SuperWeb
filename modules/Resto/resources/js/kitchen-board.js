export default function kitchenBoard(config = {}) {
    return {
        outletId: config.outletId || 1,
        simulateUrl: config.simulateUrl || '/resto/kitchen/simulate',
        isCookingModalOpen: false,
        isDiscardModalOpen: false,
        activeTab: 'display', // 'display', 'production', 'waste'

        // Cooking modal state
        selectedRecipeId: '',
        plannedPortions: 10,
        actualPortions: 10,
        putOnDisplay: true,
        cookingNote: '',
        isSimulating: false,
        simulationResult: null,

        // Discard modal state
        discardTrayId: null,
        discardTrayName: '',
        discardPortions: 0,
        discardReason: 'Batas waktu pajang etalase habis (6 jam)',

        init() {
            // Live countdown tick every minute
            setInterval(() => {
                this.$dispatch('kitchen-tick');
            }, 30000);
        },

        openCookModal(defaultRecipeId = '') {
            this.selectedRecipeId = defaultRecipeId;
            this.plannedPortions = 10;
            this.actualPortions = 10;
            this.putOnDisplay = true;
            this.cookingNote = '';
            this.simulationResult = null;
            this.isCookingModalOpen = true;

            if (defaultRecipeId) {
                this.simulate();
            }
        },

        closeCookModal() {
            this.isCookingModalOpen = false;
        },

        openDiscardModal(trayId, dishName, portions) {
            this.discardTrayId = trayId;
            this.discardTrayName = dishName;
            this.discardPortions = portions;
            this.discardReason = 'Batas waktu pajang etalase habis (6 jam)';
            this.isDiscardModalOpen = true;
        },

        closeDiscardModal() {
            this.isDiscardModalOpen = false;
        },

        onPortionsChange() {
            this.actualPortions = this.plannedPortions;
            this.simulate();
        },

        applySuggestedPortions() {
            if (this.simulationResult && this.simulationResult.suggested_portions > 0) {
                this.plannedPortions = this.simulationResult.suggested_portions;
                this.actualPortions = this.simulationResult.suggested_portions;
                this.simulate();
            }
        },

        async simulate() {
            if (!this.selectedRecipeId || !this.plannedPortions || this.plannedPortions <= 0) {
                this.simulationResult = null;
                return;
            }

            this.isSimulating = true;

            try {
                const response = await fetch(this.simulateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        outlet_id: this.outletId,
                        recipe_id: this.selectedRecipeId,
                        portions: this.plannedPortions
                    })
                });

                if (response.ok) {
                    this.simulationResult = await response.json();
                } else {
                    this.simulationResult = null;
                }
            } catch (err) {
                console.error('Simulation error:', err);
                this.simulationResult = null;
            } finally {
                this.isSimulating = false;
            }
        }
    };
}
