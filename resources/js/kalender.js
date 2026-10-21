// x-app.kalender-pkpt (dashboard): item detail as a popover anchored to the card ≥768, or the x-ui.drawer bottom sheet <768.
// Mirrors the public jadwal.js detail logic (no motion: dashboard bundle). tutup() returns focus to the card.
export default () => ({
    aktif: null,
    pemicu: null,
    drawer: false,
    // Bound to x-ui.drawer with x-model: every way it closes (X, Esc, scrim, tutup()) clears the open item.
    drawerBuka: false,
    pos: { top: 0, left: 0 },

    init() {
        this.$watch('drawerBuka', (v) => { if (!v && this.drawer) this.aktif = null; });
        // Livewire morphs keep Alpine state: drop a detail whose item is no longer rendered (year change, revision sent).
        this.unhook = this.$wire.$hook('morphed', () => this.$nextTick(() => {
            if (this.aktif !== null && !this.$root.querySelector(`[data-kartu="${this.aktif}"]`)) this.tutup(document.getElementById('judul-kalender'));
        }));
    },

    // $hook cleans up with the Livewire component only; the calendar is removed on Kalender⇄Tabel without that.
    destroy() {
        this.unhook?.();
    },

    buka(id, el) {
        this.pemicu = el;
        this.aktif = id;
        this.drawer = innerWidth < 768;
        if (this.drawer) this.drawerBuka = true;
        else this.$nextTick(() => this.posisi());
    },

    // Focus goes to `fokus`, else back to the card that opened the detail (x-trap.noreturn on the popover; Safari does not focus buttons on click).
    tutup(fokus = null) {
        if (this.aktif === null) return;
        const sasaran = fokus ?? this.pemicu;
        this.aktif = null;
        this.drawerBuka = false;
        setTimeout(() => (sasaran?.isConnected && sasaran.offsetParent ? sasaran : document.getElementById('judul-kalender'))?.focus(), 0);
    },

    // Outside click: another card opens instead; a click on a control keeps focus there, anywhere else focus returns to the card.
    luar(e) {
        if (this.drawer || e.target.closest('[data-kartu]')) return;
        const kontrol = e.target.closest('a[href], button, input, select, textarea, [tabindex]');
        this.tutup(kontrol && kontrol.tabIndex >= 0 ? kontrol : null);
    },

    // Fixed position under the card (above it when there is no room), inside the viewport and below the 64px sticky header.
    posisi() {
        if (this.aktif === null || this.drawer || !this.pemicu) return;
        const r = this.pemicu.getBoundingClientRect();
        const panel = this.$refs.popover;
        const w = panel.offsetWidth, h = panel.offsetHeight;
        const top = r.bottom + 8 + h > innerHeight - 8 ? Math.max(72, r.top - 8 - h) : r.bottom + 8;
        this.pos = { top, left: Math.min(Math.max(16, r.left), innerWidth - w - 16) };
    },
});
