import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import kotakMasuk from './kotak-masuk';

// Livewire's bundled Alpine already includes intersect, focus and sort; do not register them again.

// Mobile off-canvas sidebar: the hamburger lives in the header, the sidebar inside @persist('sidebar').
Alpine.store('nav', { open: false });

// Livewire keeps data-current in sync on persisted links; mirror it to aria-current for screen readers.
document.addEventListener('livewire:navigated', () => {
    Alpine.store('nav').open = false;
    document.querySelectorAll('[data-nav-link]').forEach((a) =>
        a.hasAttribute('data-current') ? a.setAttribute('aria-current', 'page') : a.removeAttribute('aria-current'));
});

Alpine.data('kotakMasuk', kotakMasuk);

Livewire.start();
