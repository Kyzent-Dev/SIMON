// Public Jadwal PKPT (design/public/jadwal.md): Linimasa/Bulanan toggle kept in ?tampilan=, legend highlight, detail panel
// (popover anchored to the trigger ≥768, bottom sheet <768). Focus return is handled by x-trap on the panel.
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

    tutup() {
        this.aktif = null;
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
