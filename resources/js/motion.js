import { animate, inView, stagger } from "motion";

const reduce = () => matchMedia("(prefers-reduced-motion: reduce)").matches;
const css = (name, fallback) =>
  getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
// "400ms" -> 0.4 (motion uses seconds); Chrome reports the token as ".4s", so accept both units.
const ms = (v) => (v.endsWith("ms") ? parseFloat(v) / 1000 : parseFloat(v));
const bezier = (v) => v.match(/-?[\d.]+/g).map(Number); // "cubic-bezier(0,0,.2,1)" -> [0,0,.2,1] (skips the "-" in the name)

const T = () => ({
  slow: ms(css("--dur-slow", "400ms")),
  enter: bezier(css("--ease-enter", "cubic-bezier(0,0,.2,1)")),
});

const presets = {
  // children of [data-motion="stagger"] fade up, 60ms apart, max 5 staggered
  stagger(el) {
    const kids = [...el.children];
    return animate(kids, { opacity: [0, 1], y: [16, 0] },
      { duration: T().slow, ease: T().enter, delay: stagger(0.06, { startDelay: 0 }) });
  },
  "fade-up"(el) {
    return animate(el, { opacity: [0, 1], y: [16, 0] }, { duration: T().slow, ease: T().enter });
  },
  // [data-motion="bars"]: each [data-bar] scales in from the left, total ≤600ms
  bars(el) {
    const bars = [...el.querySelectorAll("[data-bar]")];
    const step = Math.min(0.015, 0.2 / Math.max(bars.length, 1));
    return animate(bars, { scaleX: [0, 1] },
      { duration: T().slow, ease: T().enter, delay: stagger(step) });
  },
  // hero content sequence
  hero(el) {
    const parts = [...el.querySelectorAll("[data-hero-part]")];
    return animate(parts, { opacity: [0, 1], y: [24, 0] },
      { type: "spring", bounce: 0, duration: 0.7, delay: stagger(0.08) });
  },
};

function finish(el) {
  el.dataset.motionDone = "";
  el.style.opacity = "";
  el.style.transform = "";
}

export function initMotion(root = document) {
  document.documentElement.classList.add("motion-ready");
  root.querySelectorAll("[data-motion]:not([data-motion-done])").forEach((el) => {
    const preset = presets[el.dataset.motion];
    if (!preset || reduce()) { finish(el); return; }
    inView(el, () => {
      // initMotion runs on DOMContentLoaded and on Livewire's initial livewire:navigated; inView also re-fires on re-entry.
      if (el.hasAttribute("data-motion-done")) return;
      el.dataset.motionDone = "";
      preset(el);
    }, { amount: 0.2 });
  });
}
