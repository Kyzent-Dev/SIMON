// Hero carousel (design/public/landing.md "Alpine skeleton"): 7s auto-advance, paused by the button (until Putar), by hover/focus within
// and while the tab is hidden; swipe 40px; ←/→ handled in Blade. Reduced motion: starts paused. Crossfade and Ken Burns are CSS.
export default ({ count, interval = 7000 }) => ({
    i: 0,
    count,
    playing: false,
    hover: false,
    focus: false,
    timer: null,
    x0: null,
    init() {
        this.playing = count > 1 && !matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.onVisibility = () => this.sync();
        document.addEventListener('visibilitychange', this.onVisibility);
        this.sync();
    },
    destroy() {
        clearInterval(this.timer);
        document.removeEventListener('visibilitychange', this.onVisibility);
    },
    get running() { return this.playing && !this.hover && !this.focus; },
    sync() {
        clearInterval(this.timer);
        this.timer = this.running && !document.hidden ? setInterval(() => this.go(this.i + 1, true), interval) : null;
    },
    go(n, auto = false) {
        this.i = (n + this.count) % this.count;
        if (!auto) this.sync(); // a manual change restarts the 7s
    },
    next() { this.go(this.i + 1); },
    prev() { this.go(this.i - 1); },
    // ←/→ only when bare: Alt+← is browser Back, Ctrl/Cmd+← word or tab navigation.
    key(e, dir) { if (!(e.altKey || e.ctrlKey || e.metaKey || e.shiftKey)) this.go(this.i + dir); },
    toggle() { this.playing = !this.playing; this.sync(); },
    enter(e) { if (e.pointerType === 'mouse') { this.hover = true; this.sync(); } },
    leave(e) { if (e.pointerType === 'mouse') { this.hover = false; this.sync(); } },
    // Only keyboard focus pauses (:focus-visible): after a mouse click the button keeps focus, which must not hold autoplay stopped.
    focusIn(e) { this.focus = !!e.target.matches?.(':focus-visible'); this.sync(); },
    focusOut(e) { if (!this.$root.contains(e.relatedTarget)) { this.focus = false; this.sync(); } },
    down(e) { this.x0 = e.clientX; },
    up(e) {
        if (this.x0 === null) return;
        const dx = e.clientX - this.x0;
        this.x0 = null;
        if (Math.abs(dx) >= 40) dx < 0 ? this.next() : this.prev();
    },
});
