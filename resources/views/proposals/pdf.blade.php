<!doctype html>
{{-- The PDF wrapper around the shared paper partial.

     Rendered by headless Chrome rather than DomPDF, because DomPDF's core
     fonts are Latin-1: a "5★" printed as "5?", a typographic minus as "?",
     and an item named in Arabic would have been unreadable — which is not a
     limitation a bilingual business can carry on the document it sends.

     Same partial, same faces as the live preview, so the export IS the
     preview. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $proposal->number }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=amiri:400,700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4; margin: 0; }
        html, body { margin: 0; padding: 0; }
        /* The document sets its own family; this only has to cover anything the
           partial does not, and to carry Arabic where a name is in it. */
        body { font-family: 'Helvetica Neue', Helvetica, Arial, 'Amiri', sans-serif; }
    </style>
</head>
<body>
    @include('proposals.paper', [
        'proposal' => $proposal,
        'company' => $company,
        'theme' => $theme,
        'screen' => false,
    ])
</body>
</html>
