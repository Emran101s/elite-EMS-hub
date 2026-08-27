{{--
    Microsoft Clarity — off unless CLARITY_PROJECT_ID is set in .env. Once
    enabled, this sends real visitor session data (clicks, scroll, page
    content) to Microsoft — set the project ID only where you actually want
    that, e.g. staging, not local dev by default.
--}}
@if ($id = config('services.clarity.project_id'))
    <script>
        (function (c, l, a, r, i, t, y) {
            c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments) };
            t = l.createElement(r); t.async = 1; t.src = "https://www.clarity.ms/tag/" + i;
            y = l.getElementsByTagName(r)[0]; y.parentNode.insertBefore(t, y);
        })(window, document, "clarity", "script", "{{ $id }}");
    </script>
@endif
