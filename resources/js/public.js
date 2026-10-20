// Public entry (DESIGN §7.6): mirrors app.js bootstrapping, plus the motion wrapper. Public pages use full page loads.
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { initMotion } from './motion';
import carousel from './carousel';
import countUp from './countup';
import jadwal from './jadwal';
import { grafikDonat, grafikGaris, grafikRealisasi } from './grafik';

// Livewire's bundled Alpine already includes intersect, focus and sort; do not register them again.
Alpine.data('carousel', carousel);
Alpine.data('countUp', countUp);
Alpine.data('jadwal', jadwal);
Alpine.data('grafikRealisasi', grafikRealisasi);
Alpine.data('grafikGaris', grafikGaris);
Alpine.data('grafikDonat', grafikDonat);

Livewire.start();

document.addEventListener('DOMContentLoaded', () => initMotion());
document.addEventListener('livewire:navigated', () => initMotion());
