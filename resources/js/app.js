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

/* ══════════════════════════════════════════════════════════════════════════
   THE EVENT DECK

   A spatial deck, not a carousel. Every mission is already in the DOM; this
   only decides where each one sits in space — the card you are on comes toward
   you at full size, its neighbours fall back and turn away, and anything past
   the second rank is parked out of sight rather than removed.

   It lives here rather than in the page because a server round-trip cannot
   produce a 520ms transition, and re-rendering would tear down the very
   elements the transition is animating. The deck owns its index; the server is
   told which mission is active only so a reload or a jump to another view
   lands on the same one.
   ══════════════════════════════════════════════════════════════════════════ */

// The geometry is quoted for a 1920px screen, against a 980px hero, and scaled
// from the stage's own width so the arrangement holds on a laptop.
const DECK_REF = 980;
const DECK_RANKS = [
    { scale: 1,   rotate: 0,  x: 0,   z: 120,  opacity: 1,    blur: 0,   layer: 40 },
    { scale: 0.8, rotate: 12, x: 420, z: -80,  opacity: 0.85, blur: 0.6, layer: 30 },
    { scale: 0.6, rotate: 18, x: 700, z: -180, opacity: 0.4,  blur: 2,   layer: 20 },
];

function mountDeck(stage) {
    if (stage.dataset.deckMounted) return;
    stage.dataset.deckMounted = '1';

    const cards = [...stage.querySelectorAll('[data-deck-card]')];
    if (! cards.length) return;

    const root = stage.closest('[data-deck-root]') || document;
    const dots = [...root.querySelectorAll('[data-deck-dot]')];
    const prev = root.querySelector('[data-deck-prev]');
    const next = root.querySelector('[data-deck-next]');

    let index = Math.max(0, cards.findIndex((c) => c.dataset.active === '1'));
    let heroW = DECK_REF;
    let k = 1;

    const measure = () => {
        // The hero takes ~58% of the stage — inside the range the design calls
        // for — and every distance scales with it.
        heroW = Math.max(300, Math.min(DECK_REF, Math.round(stage.clientWidth * 0.58)));
        k = heroW / DECK_REF;

        cards.forEach((card) => {
            card.style.width = `${heroW}px`;
            card.style.marginLeft = `${-heroW / 2}px`;
        });

        // Tall enough for the hero plus the room the tilted neighbours need.
        stage.style.height = `${Math.round((cards[index]?.offsetHeight || 540) + 40)}px`;
    };

    const place = (drag = 0) => {
        cards.forEach((card, i) => {
            const offset = i - index;
            const side = Math.sign(offset) || 1;
            const rank = DECK_RANKS[Math.min(2, Math.abs(offset))];
            const far = Math.abs(offset) > 2;

            // A card to the LEFT turns toward you (+rotateY); one to the right
            // turns the other way. The hero sits square on.
            const x = (offset === 0 ? 0 : side * rank.x) * k + drag;
            const rotate = offset === 0 ? 0 : -side * rank.rotate;

            card.style.transform =
                `translate3d(${x}px, 0, ${rank.z * k}px) rotateY(${rotate}deg) scale(${rank.scale})`;
            card.style.opacity = far ? 0 : rank.opacity;
            card.style.filter = rank.blur ? `blur(${rank.blur}px)` : 'none';
            card.style.zIndex = far ? 0 : rank.layer;
            card.style.boxShadow = offset === 0
                ? '0 46px 90px -40px rgba(30,27,75,.55), 0 8px 24px -12px rgba(30,27,75,.25)'
                : '0 24px 50px -30px rgba(30,27,75,.45)';
            card.classList.toggle('is-active', offset === 0);
            card.setAttribute('aria-hidden', far ? 'true' : 'false');
        });

        dots.forEach((dot, i) => {
            const on = i === index;
            dot.classList.toggle('w-7', on);
            dot.classList.toggle('bg-navy-950', on);
            dot.classList.toggle('w-1.5', ! on);
            dot.classList.toggle('bg-navy-200', ! on);
            dot.setAttribute('aria-current', on ? 'true' : 'false');
        });

        if (prev) prev.disabled = index === 0;
        if (next) next.disabled = index === cards.length - 1;
    };

    let arriving = null;

    const go = (to) => {
        const target = Math.max(0, Math.min(cards.length - 1, to));
        if (target === index) return place();

        index = target;
        measure();
        place();

        // The parts of the new hero land in reading order: cover, title,
        // numbers, then the line about them. The dock never moves.
        arriving?.classList.remove('is-arriving');
        arriving = cards[index];
        void arriving.offsetWidth;              // restart the animation
        arriving.classList.add('is-arriving');

        // Tell the server which mission is active, without waiting for it.
        const host = stage.closest('[wire\\:id]');
        const id = host && window.Livewire?.find(host.getAttribute('wire:id'));
        id?.call('activate', Number(cards[index].dataset.id));
    };

    prev?.addEventListener('click', () => go(index - 1));
    next?.addEventListener('click', () => go(index + 1));
    dots.forEach((dot, i) => dot.addEventListener('click', () => go(i)));

    // A card at the edge is a button: clicking it brings it in.
    cards.forEach((card, i) => card.addEventListener('click', (e) => {
        if (i === index || e.target.closest('[data-deck-keep]')) return;
        e.preventDefault();
        go(i);
    }));

    stage.addEventListener('keydown', (e) => {
        const step = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: cards.length - 1 }[e.key];
        if (step === undefined) return;
        e.preventDefault();
        go(step);
    });

    // ── drag and swipe ──
    let drag = null;

    stage.addEventListener('pointerdown', (e) => {
        if (e.button !== 0 || e.target.closest('[data-deck-keep]')) return;
        drag = { x: e.clientX, y: e.clientY, dx: 0, moved: false };
        stage.setPointerCapture(e.pointerId);
    });

    stage.addEventListener('pointermove', (e) => {
        if (! drag) return;
        const dx = e.clientX - drag.x;
        if (! drag.moved && Math.abs(dx) < 6) return;
        // A mostly-vertical gesture is the page scrolling, not the deck.
        if (! drag.moved && Math.abs(dx) < Math.abs(e.clientY - drag.y)) { drag = null; return; }

        drag.moved = true;
        drag.dx = dx;
        stage.classList.add('is-dragging');
        place(dx * 0.6);                        // follows the finger, damped
    });

    const release = () => {
        if (! drag) return;
        const { dx, moved } = drag;
        drag = null;
        stage.classList.remove('is-dragging');

        // A tenth of the hero's width is enough to mean it.
        if (moved && Math.abs(dx) > heroW * 0.1) go(index + (dx < 0 ? 1 : -1));
        else place();
    };

    stage.addEventListener('pointerup', release);
    stage.addEventListener('pointercancel', release);

    // ── trackpad ──
    let wheelAt = 0;
    stage.addEventListener('wheel', (e) => {
        if (Math.abs(e.deltaX) < Math.abs(e.deltaY) || Math.abs(e.deltaX) < 12) return;
        e.preventDefault();
        if (Date.now() - wheelAt < 380) return;  // one card per gesture
        wheelAt = Date.now();
        go(index + (e.deltaX > 0 ? 1 : -1));
    }, { passive: false });

    measure();
    place();
    cards[index]?.classList.add('is-arriving');

    let resizeAt;
    window.addEventListener('resize', () => {
        clearTimeout(resizeAt);
        resizeAt = setTimeout(() => { measure(); place(); }, 120);
    });
}

/* The Flight Path's canvas is drawn in percentages inside a min-width box, so
   zoom is that width: no re-layout, and every card keeps its own date. */
function mountFlightPath(root) {
    if (root.dataset.fpMounted) return;
    root.dataset.fpMounted = '1';

    let level = 100;
    const apply = () => {
        root.style.minWidth = `${Math.round((Number(root.dataset.fpBase) || 880) * level / 100)}px`;
        const out = document.querySelector('[data-fp-level]');
        if (out) out.textContent = `${level}%`;
    };

    document.querySelectorAll('[data-fp-zoom]').forEach((b) => b.addEventListener('click', () => {
        level = Math.min(240, Math.max(60, level + Number(b.dataset.fpZoom) * 20));
        apply();
    }));

    document.querySelector('[data-fp-today]')?.addEventListener('click', () => {
        document.querySelector('[data-fp-line]')?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    });
}

const mountEventViews = () => {
    document.querySelectorAll('[data-deck-stage]').forEach(mountDeck);
    document.querySelectorAll('[data-fp-canvas]').forEach(mountFlightPath);
};

document.addEventListener('DOMContentLoaded', mountEventViews);
document.addEventListener('livewire:navigated', mountEventViews);
document.addEventListener('livewire:init', () => {
    // A Livewire re-render replaces the cards, so the deck is mounted again.
    window.Livewire.hook('morph.updated', mountEventViews);
});
mountEventViews();
