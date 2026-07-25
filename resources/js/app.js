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
    // ── ORBIT theme (dark-first) ──────────────────────────────────────────
    // Sets data-theme on <html>. Dark is the operations default; light is for
    // planning/documents. Inert until components read the ORBIT tokens.
    window.Alpine.store('orbit', {
        theme: localStorage.orbitTheme ?? 'dark',
        init() {
            document.documentElement.dataset.theme = this.theme;
        },
        set(t) {
            this.theme = t === 'light' ? 'light' : 'dark';
            localStorage.orbitTheme = this.theme;
            document.documentElement.dataset.theme = this.theme;
        },
        toggle() {
            this.set(this.theme === 'dark' ? 'light' : 'dark');
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
