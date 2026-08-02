{{--
    Badge styling, shared by the on-screen preview and the printed sheet.

    Plain CSS rather than utilities: this is the one part of the platform that
    is measured in millimetres and has to survive being handed to a printer,
    and a print stylesheet expressed in Tailwind classes is a stylesheet you
    cannot check against a ruler.
--}}
<style>
    /*
       The browser's default 8px body margin is 16px of the page width, and two
       A6 badges leave about 1mm of slack in an A4 landscape sheet — so that
       margin alone was pushing the second badge onto its own row and halving
       the yield. @page margin: 0 does not cover it; this does.
    */
    html, body { margin: 0; padding: 0; }

    .badge {
        position: relative;
        /*
           inline-flex, not flex: the sheet tiles these like text, which is the
           one layout mode that paginates predictably across printed pages. A
           wrapping flex container put one badge on page one and two on page
           two, because flex has no idea where a page ends.
        */
        display: inline-flex;
        vertical-align: top;
        overflow: hidden;
        box-sizing: border-box;
        background: #fff;
        border-radius: 2.5mm;
        font-family: 'Instrument Sans', system-ui, sans-serif;
        color: var(--ink);
        page-break-inside: avoid;
        break-inside: avoid;
    }

    /* The one band of colour. A badge read across a room needs a hook. */
    .badge-bar {
        width: 4mm;
        flex: 0 0 4mm;
        background: var(--accent);
    }

    .badge-body {
        display: flex;
        flex: 1;
        min-width: 0;
        flex-direction: column;
        justify-content: space-between;
        padding: 4mm 4.5mm;
    }

    .badge-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 3mm;
    }

    .badge-logo { max-height: 9mm; max-width: 42mm; object-fit: contain; }

    .badge-event {
        font-size: 2.9mm;
        font-weight: 700;
        line-height: 1.25;
        letter-spacing: .02em;
        text-transform: uppercase;
        color: color-mix(in srgb, var(--ink) 55%, #fff);
        max-width: 46mm;
    }

    .badge-ticket {
        flex: 0 0 auto;
        border-radius: 6mm;
        background: var(--accent);
        padding: 1mm 2.6mm;
        font-size: 2.7mm;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
        /* Against a light accent, white text disappears. */
        color: color-mix(in srgb, var(--accent) 30%, #000);
    }

    .badge-name-wrap { padding: 2mm 0; min-width: 0; }

    .badge-name {
        margin: 0;
        font-family: 'Playfair Display', Georgia, serif;
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -.01em;
        overflow-wrap: break-word;
    }

    .badge-role, .badge-org { margin: 1mm 0 0; line-height: 1.3; }
    .badge-role { font-size: 3.2mm; font-weight: 600; color: color-mix(in srgb, var(--ink) 70%, #fff); }
    .badge-org  { font-size: 3.4mm; font-weight: 700; color: var(--accent); }

    /* Extra lines the event chose — quieter than the organisation, because
       they are what somebody reads second, after they know who this is. */
    .badge-line { margin: 0.8mm 0 0; font-size: 3mm; font-weight: 600;
                  line-height: 1.25; color: #475569; }

    .badge-foot {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 3mm;
        border-top: .3mm solid color-mix(in srgb, var(--ink) 12%, #fff);
        padding-top: 2mm;
    }

    .badge-foot-text { min-width: 0; display: flex; flex-direction: column; gap: .8mm; }

    .badge-ref {
        font-family: ui-monospace, 'SF Mono', Menlo, monospace;
        font-size: 3mm;
        font-weight: 700;
        letter-spacing: .14em;
        color: color-mix(in srgb, var(--ink) 60%, #fff);
    }

    .badge-footer-note {
        font-size: 2.6mm;
        line-height: 1.3;
        color: color-mix(in srgb, var(--ink) 45%, #fff);
    }

    .badge-qr { width: 14mm; height: 14mm; flex: 0 0 14mm; }

    /* ── The sheet ────────────────────────────────────────────────────── */
    /*
       No gaps and no page padding, on purpose. A6 is exactly a quarter of A4,
       so four tile the page perfectly — and a 4mm gutter turns that into two
       per sheet and twice the card stock. Every stock size here divides a page
       the same way, so they all tile.
    */
    .badge-sheet {
        /* font-size: 0 kills the whitespace between inline-blocks, which would
           otherwise be a few px of gutter and break the exact tiling. */
        font-size: 0;
        line-height: 0;
        padding: 0;
        margin: 0;
    }

    /* The cut line sits inside the badge (box-sizing: border-box above), so a
       guillotine has something to follow without stealing any width. */
    .badge-sheet .badge {
        border: .2mm dashed color-mix(in srgb, var(--ink) 20%, #fff);
        border-radius: 0;
    }
</style>
