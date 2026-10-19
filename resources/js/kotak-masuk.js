// Kotak Masuk Pengajuan keyboard model (design/dashboard/bagian-umum.md "Keyboard").
// The list is a listbox with aria-activedescendant; ↑/↓/Enter/Esc always work there. J/K/T/R/X/? are single-key shortcuts (WCAG 2.1.4):
// switchable ("Pintasan keyboard", persisted per browser) and never fired while typing in a field or while a modal is open.
const XL = '(min-width: 1280px)';
const DRAWER = '(min-width: 768px) and (max-width: 1279.98px)';

export default () => ({
    pintasan: Alpine.$persist(true).as('bu-pintasan'),
    xl: matchMedia(XL).matches,
    drawer: matchMedia(DRAWER).matches,
    aktif: null,

    init() {
        matchMedia(XL).addEventListener('change', (e) => (this.xl = e.matches));
        matchMedia(DRAWER).addEventListener('change', (e) => (this.drawer = e.matches));
        this.aktif = (this.$wire.pilih ?? +this.opsi()[0]?.dataset.id) || null;
        // A tab/filter change or a decision re-renders the list: drop an active option that is gone (aria-activedescendant must not dangle).
        this.lepasHook = window.Livewire.hook('morphed', () => {
            if (this.aktif && !this.opsi().some((o) => +o.dataset.id === this.aktif)) this.aktif = (this.$wire.pilih ?? +this.opsi()[0]?.dataset.id) || null;
        });
    },

    destroy() {
        this.lepasHook?.();
    },

    opsi() {
        return [...(this.$refs.daftar?.querySelectorAll('[role=option]') ?? [])];
    },

    /** Keys typed in fields, with modifiers, or while a dialog outside this page region is open are never shortcuts. */
    abaikan(e) {
        const t = e.target;
        if (e.ctrlKey || e.metaKey || e.altKey || t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)) return true;
        return [...document.querySelectorAll('[role=dialog][aria-modal=true]')].some((d) => !this.$root.contains(d) && d.offsetParent !== null);
    },

    pilih(id) {
        return this.$wire.pilihSpt(id).then(() => {
            if (!this.xl) this.$nextTick(() => this.$refs.detail?.focus());
        });
    },

    gerak(arah) {
        const opsi = this.opsi();
        if (!opsi.length) return;
        const i = opsi.findIndex((o) => o.dataset.id * 1 === this.aktif);
        const n = opsi[Math.min(Math.max(i + arah, 0), opsi.length - 1)];
        this.aktif = n.dataset.id * 1;
        n.scrollIntoView({ block: 'nearest' });
        this.$refs.daftar.focus();
        if (this.xl) this.$wire.pilihSpt(this.aktif); // master–detail: selection follows the keyboard
    },

    tutup() {
        this.$wire.tutup().then(() => this.$nextTick(() => this.$refs.daftar?.focus()));
    },

    /** After a decision the server selected the next item: keep the keyboard flow on the list (≥1280) or the new detail. */
    maju() {
        this.$nextTick(() => {
            this.aktif = this.$wire.pilih;
            (this.xl || !this.aktif ? this.$refs.daftar : this.$refs.detail)?.focus();
        });
    },

    tombol(e) {
        if (this.abaikan(e)) return;
        const diDaftar = !!e.target.closest?.('[role=listbox]');
        const diDetail = !!this.$refs.detail?.contains(e.target);
        const bebas = diDaftar || diDetail || e.target === document.body;
        const k = e.key;

        if (diDaftar && (k === 'ArrowDown' || k === 'ArrowUp')) {
            e.preventDefault();
            return this.gerak(k === 'ArrowDown' ? 1 : -1);
        }
        if (diDaftar && k === 'Enter' && this.aktif) {
            e.preventDefault();
            return this.xl && this.$wire.pilih === this.aktif ? this.$refs.detail?.focus() : this.pilih(this.aktif);
        }
        if (diDetail && k === 'Escape') {
            e.preventDefault();
            return this.xl ? this.$refs.daftar?.focus() : this.tutup();
        }
        if (!this.pintasan || !bebas) return;

        const key = k.toLowerCase();
        if (key === 'j' || key === 'k') {
            e.preventDefault();
            this.gerak(key === 'j' ? 1 : -1);
        } else if (k === '?') {
            e.preventDefault();
            this.$dispatch('open-modal', { name: 'bantuan-pintasan' });
        } else if ((key === 't' || key === 'r' || key === 'x') && this.$refs.detail?.querySelector('[aria-keyshortcuts=t]')) {
            e.preventDefault();
            if (key === 't') this.$wire.bukaTerima();
            else this.$wire.bukaCatatan(key === 'r' ? 'revisi' : 'tolak');
        }
    },
});
