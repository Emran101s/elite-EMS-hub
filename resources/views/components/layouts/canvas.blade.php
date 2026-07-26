@props(['title' => 'Command Canvas'])
{{--
    Elite Command Canvas — the shell.

    Light ground (~80%) with dark navy reserved for the two command surfaces:
    the pulse strip and the primary event object. Deliberately no sidebar — the
    left is a floating dock, so the canvas keeps the full width of the screen.
--}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Elite Business Hub</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=montserrat:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-cc-mist font-canvas text-cc-ink antialiased">
    {{ $slot }}

    <script>
        /* Small, dependency-free behaviour. No Livewire, no Alpine on this page. */
        document.addEventListener('DOMContentLoaded', () => {

            /* View switcher — Canvas / List / Calendar */
            const views = document.querySelectorAll('[data-view-btn]');
            const panes = document.querySelectorAll('[data-view-pane]');
            views.forEach(btn => btn.addEventListener('click', () => {
                const target = btn.dataset.viewBtn;
                views.forEach(b => b.setAttribute('aria-pressed', String(b === btn)));
                panes.forEach(p => p.hidden = p.dataset.viewPane !== target);
            }));

            /* Canvas zoom + pan. Transform only, so nothing reflows. */
            const stage = document.querySelector('[data-canvas-stage]');
            if (stage) {
                let scale = 1, x = 0, y = 0, dragging = false, sx = 0, sy = 0;
                const apply = () => stage.style.transform = `translate(${x}px, ${y}px) scale(${scale})`;
                const clamp = v => Math.min(1.6, Math.max(0.6, v));

                document.querySelector('[data-zoom="in"]')?.addEventListener('click', () => { scale = clamp(scale + 0.12); apply(); });
                document.querySelector('[data-zoom="out"]')?.addEventListener('click', () => { scale = clamp(scale - 0.12); apply(); });
                document.querySelector('[data-zoom="reset"]')?.addEventListener('click', () => { scale = 1; x = y = 0; apply(); });

                const frame = stage.parentElement;
                frame.addEventListener('pointerdown', e => {
                    if (e.target.closest('a,button')) return;
                    dragging = true; sx = e.clientX - x; sy = e.clientY - y;
                    frame.setPointerCapture(e.pointerId); frame.style.cursor = 'grabbing';
                });
                frame.addEventListener('pointermove', e => {
                    if (!dragging) return;
                    x = e.clientX - sx; y = e.clientY - sy; apply();
                });
                const stop = () => { dragging = false; frame.style.cursor = ''; };
                frame.addEventListener('pointerup', stop);
                frame.addEventListener('pointercancel', stop);
            }

            /* ⌘K focuses the command search. */
            window.addEventListener('keydown', e => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    document.querySelector('[data-command-search]')?.focus();
                }
            });
        });
    </script>
</body>
</html>
