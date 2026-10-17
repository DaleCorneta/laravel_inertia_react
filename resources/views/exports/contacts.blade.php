<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body { color: #1e293b; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .meta { color: #64748b; margin: 0 0 18px; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        thead { display: table-header-group; }
        th, td { border: 1px solid #cbd5e1; padding: 7px 8px; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background: #f1f5f9; color: #334155; font-size: 9px; text-transform: uppercase; }
        tr { page-break-inside: avoid; }
        .empty { color: #64748b; text-align: center; }
    </style>
</head>
<body>
    <h1>Contact directory</h1>
    <p class="meta">{{ count($rows) }} {{ count($rows) === 1 ? 'contact' : 'contacts' }} · Generated {{ $generatedAt }}</p>
    <table>
        <thead><tr>@foreach ($columns as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $value)<td>{{ $value }}</td>@endforeach</tr>
            @empty
                <tr><td class="empty" colspan="{{ count($columns) }}">No contacts match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
