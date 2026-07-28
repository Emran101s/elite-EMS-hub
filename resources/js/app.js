// ORBIT typefaces, self-hosted — no CDN, no silent fallback.
// --font-ui Inter · --font-display Instrument Serif · --font-data JetBrains Mono
import '@fontsource/inter/300.css';
import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import '@fontsource/instrument-serif/400.css';
import '@fontsource/instrument-serif/400-italic.css';
import '@fontsource/jetbrains-mono/400.css';
import '@fontsource/jetbrains-mono/500.css';
import '@fontsource/jetbrains-mono/700.css';

import Sortable from 'sortablejs';
import * as htmlToImage from 'html-to-image';
import html2canvas from 'html2canvas';

// Expose for the agenda drag-and-drop builder (initialised from the Livewire view).
window.Sortable = Sortable;

// "Download image" helpers: html-to-image for standalone pages (sponsorship prospectus),
// html2canvas for in-app pages where the SVG-foreignObject path is unreliable (floor plan).
window.htmlToImage = htmlToImage;
window.html2canvas = html2canvas;

// ── Right-edge docks ──────────────────────────────────────────────────────
// One store coordinates every dock so only one panel is ever open at a time.
document.addEventListener('alpine:init', () => {
    window.Alpine.store('dock', {
        // Deliberately not persisted: a reload should give you a clean page, not
        // a panel you left open three navigations ago sitting over your work.
        open: null,

        is(id) {
            return this.open === id;
        },

        toggle(id) {
            this.open = this.open === id ? null : id;
        },

        close() {
            this.open = null;
        },
    });
});

// ── Disclosure menus ──────────────────────────────────────────────────────
// A native <details> opens and closes on its own summary and nothing else, so
// a dropdown built from one stays open until you click it again — including
// while you read the page behind it. These two listeners give every
// details[data-menu] the behaviour a menu is expected to have.
document.addEventListener('click', (e) => {
    document.querySelectorAll('details[data-menu][open]').forEach((menu) => {
        if (! menu.contains(e.target)) {
            menu.open = false;
        }
    });
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;

    document.querySelectorAll('details[data-menu][open]').forEach((menu) => {
        menu.open = false;
        // Put focus back where it came from, or the next Tab starts at the top.
        menu.querySelector('summary')?.focus();
    });
});
