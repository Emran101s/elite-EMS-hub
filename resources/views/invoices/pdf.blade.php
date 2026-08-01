<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
</head>
<body>
    {{-- The PDF is the preview: one partial, wrapped for a page. See
         resources/views/invoices/paper.blade.php. --}}
    @include('invoices.paper', [
        'invoice' => $invoice,
        'company' => $company,
        'theme' => $theme,
        'screen' => false,
    ])
</body>
</html>
