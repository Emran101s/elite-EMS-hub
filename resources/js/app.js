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
    // ── ORBIT theme (light-first) ─────────────────────────────────────────
    // Light is home; dark is a mode entered for show days and live operations.
    // The server decides via App\Support\ThemePolicy and prints data-theme on
    // <html>; this store only carries a user's manual override for the session.
    window.Alpine.store('theme', {
        current: document.documentElement.dataset.theme || 'light',
        set(v) {
            this.current = v === 'dark' ? 'dark' : 'light';
            document.documentElement.dataset.theme = this.current;
            fetch('/theme-override', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
                body: JSON.stringify({ theme: this.current }),
            });
        },
        toggle() {
            this.set(this.current === 'dark' ? 'light' : 'dark');
        },
    });

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
