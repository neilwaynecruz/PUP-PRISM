<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'PUP-PRISM Product Labels' }}</title>
    <style>
        @page {
            margin: {{ $margin_mm ?? 8 }}mm;
            size: {{ $paper_size ?? 'a4' }} {{ $orientation ?? 'portrait' }};
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #111827;
            background: #ffffff;
            font-size: 8pt;
            line-height: 1.15;
        }
        .page {
            width: 100%;
        }
        .page + .page {
            page-break-before: always;
        }
        table.grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: {{ $gap_mm ?? 2 }}mm;
            table-layout: fixed;
        }
        td.label-cell {
            vertical-align: middle;
            text-align: center;
            border: 0.5pt dashed #cbd5e1;
            padding: 0.25mm 1mm;
            border-radius: 2mm;
            overflow: hidden;
            background: #ffffff;
        }
        .product-name {
            font-size: 7.5pt;
            font-weight: bold;
            color: #0f172a;
            max-height: 16pt;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 1.5mm;
        }
        .qr-wrapper {
            margin: 0 auto 1.5mm auto;
            text-align: center;
        }
        .qr-wrapper img {
            display: inline-block;
            vertical-align: middle;
        }
        .sku-code {
            font-family: "Courier New", Courier, monospace;
            font-size: 7pt;
            font-weight: 600;
            color: #475569;
            letter-spacing: 0.5pt;
        }
        .category-name {
            font-size: 6pt;
            color: #64748b;
            margin-top: 1mm;
            text-transform: uppercase;
        }
        .brand-footer {
            font-size: 5pt;
            color: #880000;
            font-weight: bold;
            letter-spacing: 0.8pt;
            margin-top: 1mm;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    @foreach ($pages as $page)
        <div class="page">
            <table class="grid">
                @foreach ($page as $row)
                    <tr>
                        @foreach ($row as $cell)
                            @if ($cell)
                                <td class="label-cell" style="width: {{ $col_width_pct }}%; height: {{ $cell_height_mm }}mm;">
                                    @if ($show_name)
                                        <div class="product-name" title="{{ $cell['name'] }}">
                                            {{ \Illuminate\Support\Str::limit($cell['name'], 32) }}
                                        </div>
                                    @endif

                                    <div class="qr-wrapper">
                                        <img src="data:image/svg+xml;base64,{{ $cell['qr_base64'] }}"
                                             width="{{ $qr_size_px }}"
                                             height="{{ $qr_size_px }}"
                                             alt="QR Code for {{ $cell['sku'] }}" />
                                    </div>

                                    @if ($show_sku)
                                        <div class="sku-code">{{ $cell['sku'] }}</div>
                                    @endif

                                    @if ($show_category && !empty($cell['category']))
                                        <div class="category-name">{{ $cell['category'] }}</div>
                                    @endif

                                    <div class="brand-footer">PUP PRISM</div>
                                </td>
                            @else
                                <td class="label-cell" style="border: none; background: transparent; width: {{ $col_width_pct }}%;"></td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</body>
</html>
