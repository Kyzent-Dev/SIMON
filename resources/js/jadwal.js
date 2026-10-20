// Public Jadwal PKPT (design/public/jadwal.md): Linimasa/Bulanan toggle kept in ?tampilan=, legend highlight, detail panel
// (popover anchored to the trigger ≥768, bottom sheet <768). tutup() returns focus to the trigger.
const TAMPILAN = ['linimasa', 'bulanan'];

export default () => ({
    tampilan: 'linimasa',
    sorot: null,
    aktif: null,
    pemicu: null,
    pos: { top: 0, left: 0 },

    init() {
        const q = new URL(location.href).searchParams.get('tampilan');
        this.tampilan = TAMPILAN.includes(q) ? q : (matchMedia('(min-width: 1024px)').matches ? 'linimasa' : 'bulanan');
        this.$watch('tampilan', (v) => {
            const url = new URL(location.href);
            url.searchParams.set('tampilan', v);
            history.replaceState(history.state, '', url);
            this.aktif = null;
        });
        // Alpine state survives Livewire morphs: after a filter change drop a highlight whose jenis is no longer rendered,
        // and let the legend re-measure its rows.
        this.$wire.$hook('morphed', () => this.$nextTick(() => {
            if (this.sorot !== null && !this.$root.querySelector(`[data-jenis="${this.sorot}"]`)) this.sorot = null;
            window.dispatchEvent(new CustomEvent('jadwal-dirender'));
        }));
    },

    sorotJenis(id) {
        this.sorot = this.sorot === id ? null : id;
    },

    redup(id) {
        return this.sorot !== null && this.sorot !== id;
    },

    buka(id, el) {
        this.pemicu = el;
        this.aktif = id;
        this.$nextTick(() => this.posisi());
    },

    // Focus goes to `fokus`, else back to the trigger of the detail open now (x-trap.noreturn: x-trap alone would return to
    // the first trigger after moving from bar A to bar B, and Safari does not focus buttons on click).
    tutup(fokus = null) {
        if (this.aktif === null) return;
        const sasaran = fokus ?? this.pemicu;
        this.aktif = null;
        this.$nextTick(() => (sasaran?.isConnected ? sasaran : document.getElementById('judul-jadwal'))?.focus());
    },

    // Outside click: a click on another trigger opens that one instead; a click on a control keeps focus there (the trap
    // bounced it back at mousedown), anywhere else focus returns to the trigger, never to <body>.
    luar(e) {
        if (e.target.closest('[data-pemicu]')) return;
        const kontrol = e.target.closest('a[href], button, input, select, textarea, [tabindex]');
        this.tutup(kontrol && kontrol.tabIndex >= 0 ? kontrol : null);
    },

    // Fixed position under the trigger (above it when there is no room), kept inside the viewport.
    posisi() {
        if (this.aktif === null || !this.pemicu || innerWidth < 768) return;
        const r = this.pemicu.getBoundingClientRect();
        const panel = this.$refs.detail;
        const w = panel.offsetWidth, h = panel.offsetHeight;
        const top = r.bottom + 8 + h > innerHeight - 8 ? Math.max(8, r.top - 8 - h) : r.bottom + 8;
        this.pos = { top, left: Math.min(Math.max(16, r.left), innerWidth - w - 16) };
    },

    get gayaDetail() {
        return innerWidth >= 768 ? `top:${this.pos.top}px;left:${this.pos.left}px` : '';
    },
});
