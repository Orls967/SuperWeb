export default function posCashier(config = {}) {
    return {
        outletId: config.outletId || null,
        activeTab: 'tables', // 'tables' | 'menu' | 'etalase' | 'bill'
        selectedTable: null,
        selectedSession: null,
        selectedOrder: null,
        orderItems: [],
        hidangTrays: [],
        selectedTrays: [],
        consumedStatuses: {},
        discount: 0,
        paymentMethod: 'cash',
        cashTendered: 0,
        walletPin: '',
        idempotencyKey: '',
        loading: false,
        errorMessage: '',
        successMessage: '',
        modalOpenSession: false,
        modalCloseShift: false,
        modalSettleCash: false,
        modalVoidOrder: false,
        voidReason: '',
        guestCount: 2,
        guestName: '',
        customerId: null,
        selectedCategory: 'all',

        init() {
            this.generateIdempotencyKey();
        },

        generateIdempotencyKey() {
            this.idempotencyKey = 'pos_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
        },

        setTab(tab) {
            this.activeTab = tab;
            this.clearMessages();
        },

        clearMessages() {
            this.errorMessage = '';
            this.successMessage = '';
        },

        selectTable(table) {
            this.selectedTable = table;
            this.clearMessages();
            if (table.active_session) {
                this.loadSession(table.active_session.id);
            } else {
                this.selectedSession = null;
                this.selectedOrder = null;
                this.orderItems = [];
                this.modalOpenSession = true;
            }
        },

        async loadSession(sessionId) {
            this.loading = true;
            try {
                const res = await fetch(`/resto/pos/session/${sessionId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedSession = data.session;
                    this.selectedOrder = data.session.orders && data.session.orders.length > 0 ? data.session.orders[0] : null;
                    this.orderItems = this.selectedOrder ? (this.selectedOrder.items || []) : [];
                    this.initConsumedStatuses();
                }
            } catch (err) {
                this.errorMessage = 'Gagal memuat rincian sesi meja: ' + err.message;
            } finally {
                this.loading = false;
            }
        },

        initConsumedStatuses() {
            this.consumedStatuses = {};
            this.orderItems.forEach(item => {
                if (item.source === 'hidang') {
                    this.consumedStatuses[item.id] = (item.consumed_state === 'consumed');
                }
            });
        },

        async submitOpenSession() {
            if (!this.selectedTable) return;
            this.loading = true;
            this.clearMessages();
            try {
                const res = await fetch('/resto/pos/session/open', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        table_id: this.selectedTable.id,
                        guest_count: this.guestCount,
                        customer_id: this.customerId,
                        guest_name: this.guestName
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.modalOpenSession = false;
                    this.selectedSession = data.session;
                    this.selectedOrder = data.session.orders[0];
                    this.orderItems = [];
                    this.successMessage = data.message;
                    // Refresh table status locally
                    this.selectedTable.status = 'occupied';
                    this.selectedTable.active_session = data.session;
                    this.activeTab = 'hidang';
                } else {
                    this.errorMessage = data.message || 'Gagal membuka sesi meja.';
                }
            } catch (err) {
                this.errorMessage = 'Terjadi kesalahan sistem: ' + err.message;
            } finally {
                this.loading = false;
            }
        },

        toggleTraySelection(trayId) {
            const index = this.selectedTrays.indexOf(trayId);
            if (index > -1) {
                this.selectedTrays.splice(index, 1);
            } else {
                this.selectedTrays.push(trayId);
            }
        },

        async submitPresentHidang() {
            if (!this.selectedSession || this.selectedTrays.length === 0) return;
            this.loading = true;
            this.clearMessages();
            try {
                const res = await fetch(`/resto/pos/session/${this.selectedSession.id}/hidang`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        trays: this.selectedTrays
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedOrder = data.order;
                    this.orderItems = data.order.items || [];
                    this.selectedTrays = [];
                    this.initConsumedStatuses();
                    this.successMessage = data.message;
                } else {
                    this.errorMessage = data.message || 'Gagal menyajikan hidang.';
                }
            } catch (err) {
                this.errorMessage = 'Terjadi kesalahan: ' + err.message;
            } finally {
                this.loading = false;
            }
        },

        async addPesanItem(menuItemId, qty = 1) {
            if (!this.selectedSession) return;
            this.loading = true;
            this.clearMessages();
            try {
                const res = await fetch(`/resto/pos/session/${this.selectedSession.id}/item`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        menu_item_id: menuItemId,
                        qty: qty
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.orderItems.push(data.item);
                    this.successMessage = data.message;
                } else {
                    this.errorMessage = data.message || 'Gagal menambah item.';
                }
            } catch (err) {
                this.errorMessage = 'Terjadi kesalahan: ' + err.message;
            } finally {
                this.loading = false;
            }
        },

        async quickTambahNasi(nasiItemId) {
            await this.addPesanItem(nasiItemId, 1);
        },

        toggleConsumed(itemId) {
            this.consumedStatuses[itemId] = !this.consumedStatuses[itemId];
        },

        async submitCalculateBill() {
            if (!this.selectedSession) return;
            this.loading = true;
            this.clearMessages();
            try {
                const res = await fetch(`/resto/pos/session/${this.selectedSession.id}/bill`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        consumed_statuses: this.consumedStatuses,
                        discount: this.discount
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedOrder = data.order;
                    this.orderItems = data.order.items || [];
                    this.cashTendered = data.order.grand_total;
                    this.successMessage = data.message;
                    this.activeTab = 'bill';
                } else {
                    this.errorMessage = data.message || 'Gagal menghitung tagihan.';
                }
            } catch (err) {
                this.errorMessage = 'Terjadi kesalahan: ' + err.message;
            } finally {
                this.loading = false;
            }
        },

        async submitPayment() {
            if (!this.selectedOrder) return;
            this.loading = true;
            this.clearMessages();
            try {
                const res = await fetch(`/resto/pos/order/${this.selectedOrder.id}/pay`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        payment_method: this.paymentMethod,
                        cash_tendered: this.cashTendered,
                        pin: this.walletPin,
                        idempotency_key: this.idempotencyKey
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedOrder = data.order;
                    this.successMessage = data.message;
                    // Reset table and generate new idempotency key
                    if (this.selectedTable) {
                        this.selectedTable.status = 'available';
                        this.selectedTable.active_session = null;
                    }
                    this.generateIdempotencyKey();
                    // Open digital receipt in new tab or popup
                    if (data.receipt_url) {
                        window.open(data.receipt_url, '_blank', 'width=450,height=700');
                    }
                } else {
                    this.errorMessage = data.message || 'Gagal memproses pembayaran.';
                }
            } catch (err) {
                this.errorMessage = 'Terjadi kesalahan: ' + err.message;
            } finally {
                this.loading = false;
            }
        },

        async submitVoidOrder() {
            if (!this.selectedOrder || !this.voidReason) return;
            this.loading = true;
            this.clearMessages();
            try {
                const res = await fetch(`/resto/pos/order/${this.selectedOrder.id}/void`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        reason: this.voidReason
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedOrder = data.order;
                    this.modalVoidOrder = false;
                    this.voidReason = '';
                    if (this.selectedTable) {
                        this.selectedTable.status = 'available';
                        this.selectedTable.active_session = null;
                    }
                    this.successMessage = data.message;
                } else {
                    this.errorMessage = data.message || 'Gagal membatalkan pesanan.';
                }
            } catch (err) {
                this.errorMessage = 'Terjadi kesalahan: ' + err.message;
            } finally {
                this.loading = false;
            }
        }
    };
}
