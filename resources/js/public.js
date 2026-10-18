import "./bootstrap";
import { initMotion } from "./motion";

document.addEventListener("DOMContentLoaded", () => initMotion());
document.addEventListener("livewire:navigated", () => initMotion());
