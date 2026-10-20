// count-up (DESIGN §7.3): rAF from 0 to the value over 1200ms, ease-out-cubic, id-ID. Reduced motion shows the final value at once.
export default (target) => ({
    value: target,
    start() {
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) { this.value = target; return; }
        this.value = 0;
        const t0 = performance.now(), d = 1200;
        const step = (t) => {
            const p = Math.min((t - t0) / d, 1);
            this.value = Math.round(target * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    },
    get formatted() { return this.value.toLocaleString('id-ID'); },
});
