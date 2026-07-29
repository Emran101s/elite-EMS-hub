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
   you at full size, its neighbours fall back, turn away and blur.

   It lives here rather than in the page because a server round-trip cannot
   produce a 520ms transition, and re-rendering would tear down the very
   elements the transition is animating.

   Three rules keep it smooth, and they are the whole reason it is smooth:

     1. Only transform and opacity are transitioned. Those two the compositor
        can run on its own thread. filter and box-shadow cannot be, and
        animating them on a card this size repaints every frame — which is
        exactly what "not smooth" feels like. Both are set instantly instead,
        under cover of the movement.
     2. Pointer moves are coalesced to one placement per animation frame. A
        mouse reports far more often than the screen refreshes, and the work
        done between two frames never reaches a pixel.
     3. will-change is added while the deck moves and dropped when it settles.
        A permanent hint is a permanent layer, and twenty of those cost more
        than they save.
   ══════════════════════════════════════════════════════════════════════════ */

// The card is a portrait plate: taller than it is wide, at every window size.
// Width and height come from one ratio so a wide screen cannot flatten it into
// a landscape card — which is what happens the moment width is driven by the
// column alone.
const DECK_RATIO = 0.66;        // width ÷ height
const DECK_H_MIN = 520;
const DECK_H_MAX = 900;

// The neighbours' distances were quoted against a 980px hero; they are applied
// as a share of whatever the hero actually is, so the arrangement holds shape.
const DECK_REF = 980;
const DECK_MS = 520;
const DECK_RANKS = [
    { scale: 1,   rotate: 0,  x: 0,   z: 120,  opacity: 1,    blur: 0,   layer: 40 },
    { scale: 0.8, rotate: 12, x: 420, z: -80,  opacity: 0.85, blur: 0.6, layer: 30 },
    { scale: 0.6, rotate: 18, x: 700, z: -180, opacity: 0.4,  blur: 2,   layer: 20 },
];

const DECK_SHADOW_HERO = '0 46px 90px -40px rgba(30,27,75,.55), 0 8px 24px -12px rgba(30,27,75,.25)';
const DECK_SHADOW_SIDE = '0 24px 50px -30px rgba(30,27,75,.45)';

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
    let hovering = -1;

    const measure = () => {
        // Height first, from the window, then width from the ratio — so the
        // card is portrait by construction. Only if that width will not fit the
        // column does width lead, and the height follows it back down.
        let h = Math.min(DECK_H_MAX, Math.max(DECK_H_MIN, Math.round(window.innerHeight * 0.74)));
        let w = Math.round(h * DECK_RATIO);

        const room = Math.round(stage.clientWidth * 0.62);
        if (w > room) {
            w = Math.max(280, room);
            h = Math.round(w / DECK_RATIO);
        }

        heroW = w;
        k = heroW / DECK_REF;

        cards.forEach((card) => {
            card.style.width = `${heroW}px`;
            card.style.marginLeft = `${-heroW / 2}px`;
            card.style.setProperty('--deck-h', `${h}px`);
        });

        // Room for the hero plus the lift its tilted neighbours need.
        stage.style.height = `${h + 40}px`;
    };

    const place = (drag = 0) => {
        cards.forEach((card, i) => {
            const offset = i - index;
            const side = Math.sign(offset) || 1;
            const rank = DECK_RANKS[Math.min(2, Math.abs(offset))];
            const far = Math.abs(offset) > 2;

            // A card to the LEFT turns toward you (+rotateY); one to the right
            // turns away. The hero sits square on. Hovering a neighbour lifts
            // it toward you — added into the same transform so a CSS hover can
            // never fight the one the deck is writing.
            const lift = i === hovering && offset !== 0 ? 26 : 0;
            const x = (offset === 0 ? 0 : side * rank.x) * k + drag;
            const rotate = offset === 0 ? 0 : -side * rank.rotate;

            card.style.transform =
                `translate3d(${x}px, 0, ${rank.z * k + lift}px) rotateY(${rotate}deg) scale(${rank.scale})`;
            card.style.opacity = far ? 0 : rank.opacity;
            card.style.zIndex = far ? 0 : rank.layer;

            // Set, never transitioned: a repaint per frame is what jank is.
            card.style.filter = rank.blur ? `blur(${rank.blur}px)` : 'none';
            card.style.boxShadow = offset === 0 ? DECK_SHADOW_HERO : DECK_SHADOW_SIDE;
            card.style.visibility = far ? 'hidden' : 'visible';

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

    // Promoted for the length of the move, then the layers are released.
    let settle;
    const moving = () => {
        stage.classList.add('is-moving');
        clearTimeout(settle);
        settle = setTimeout(() => stage.classList.remove('is-moving'), DECK_MS + 80);
    };

    let arriving = null;

    const go = (to) => {
        const target = Math.max(0, Math.min(cards.length - 1, to));
        if (target === index) return place();

        index = target;
        hovering = -1;
        moving();
        measure();
        place();

        // The parts of the new hero land in reading order.
        arriving?.classList.remove('is-arriving');
        arriving = cards[index];
        void arriving.offsetWidth;              // restart the animation
        arriving.classList.add('is-arriving');

        // Tell the server which mission is active, without waiting for it.
        const host = stage.closest('[wire\\:id]');
        window.Livewire?.find(host?.getAttribute('wire:id'))
            ?.call('activate', Number(cards[index].dataset.id));
    };

    prev?.addEventListener('click', () => go(index - 1));
    next?.addEventListener('click', () => go(index + 1));
    dots.forEach((dot, i) => dot.addEventListener('click', () => go(i)));

    // ── a card at the edge IS the control that brings it in ──
    // It stays clickable; only its own links and buttons are inert, in the CSS.
    // A click that is really the tail of a drag is swallowed, or one gesture
    // would move the deck twice.
    let swallowClick = false;

    cards.forEach((card, i) => {
        card.addEventListener('click', (e) => {
            if (swallowClick) { e.preventDefault(); e.stopPropagation(); return; }
            if (i === index || e.target.closest('[data-deck-keep]')) return;
            e.preventDefault();
            go(i);
        });

        card.addEventListener('pointerenter', () => {
            if (i === index || drag) return;
            hovering = i;
            moving();
            place();
        });

        card.addEventListener('pointerleave', () => {
            if (hovering !== i) return;
            hovering = -1;
            moving();
            place();
        });
    });

    // A rotated card's clickable region is the projected quad, not its bounding
    // box, so its outer edge slants and part of what LOOKS clickable is not.
    // Rather than leave the mouse guessing, the stage itself takes any click
    // that missed a card and reads it as "go that way" — which is the mental
    // model anyway, and a target the width of half the stage.
    stage.addEventListener('click', (e) => {
        if (swallowClick || e.target.closest('[data-deck-card]')) return;

        const hero = cards[index].getBoundingClientRect();
        const mid = hero.left + hero.width / 2;
        go(index + (e.clientX < mid ? -1 : 1));
    });

    stage.addEventListener('keydown', (e) => {
        const step = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: cards.length - 1 }[e.key];
        if (step === undefined) return;
        e.preventDefault();
        go(step);
    });

    // ── drag and swipe ──
    let drag = null;
    let frame = 0;

    const draw = () => {
        frame = 0;
        if (drag) place(drag.dx * 0.55);
    };

    stage.addEventListener('pointerdown', (e) => {
        if (e.button !== 0 || e.target.closest('[data-deck-keep]')) return;
        drag = { x: e.clientX, y: e.clientY, dx: 0, moved: false, id: e.pointerId };
        swallowClick = false;
    });

    stage.addEventListener('pointermove', (e) => {
        if (! drag) return;
        const dx = e.clientX - drag.x;

        if (! drag.moved) {
            if (Math.abs(dx) < 6) return;
            // A mostly-vertical gesture is the page scrolling, not the deck.
            if (Math.abs(dx) < Math.abs(e.clientY - drag.y)) { drag = null; return; }

            drag.moved = true;
            hovering = -1;
            stage.classList.add('is-dragging', 'is-moving');
            // Captured only once the gesture has committed, so a plain click
            // still reaches the card underneath.
            try { stage.setPointerCapture(drag.id); } catch { /* already gone */ }
        }

        drag.dx = dx;
        if (! frame) frame = requestAnimationFrame(draw);
    });

    const release = (e) => {
        if (! drag) return;
        const { dx, moved, id } = drag;
        drag = null;

        if (frame) { cancelAnimationFrame(frame); frame = 0; }
        if (stage.hasPointerCapture?.(id)) stage.releasePointerCapture(id);

        if (! moved) return;

        // The click that follows a drag is the same gesture; ignore it.
        swallowClick = true;
        setTimeout(() => { swallowClick = false; }, 0);

        stage.classList.remove('is-dragging');
        void stage.offsetWidth;                 // let the easing take effect
        moving();

        // A tenth of the hero's width is enough to mean it.
        if (Math.abs(dx) > heroW * 0.1) go(index + (dx < 0 ? 1 : -1));
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
