<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Internal Property Accountability Receipt</title>
        <style>
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 12px;
                color: #111;
            }
            .row {
                display: flex;
                justify-content: space-between;
                gap: 16px;
            }
            .box {
                border: 1px solid #ddd;
                padding: 12px;
            }
            .muted {
                color: #666;
            }
            .title {
                font-size: 16px;
                font-weight: 700;
                margin-bottom: 4px;
            }
            .sig {
                border-top: 1px solid #111;
                margin-top: 12px;
                padding-top: 8px;
            }
        </style>
    </head>
    <body>
        <div class="title">Internal Property Accountability Receipt</div>
        <div class="muted">Handover #{{ $handover->id }}</div>
        <div class="muted">For internal accountability only. This document does not replace external legal contracts or notarized agreements.</div>

        <div style="height: 12px"></div>

        <div class="row">
            <div class="box" style="flex: 1">
                <div class="muted">Asset</div>
                <div><strong>{{ $handover->asset?->product?->name ?? '—' }}</strong></div>
                <div class="muted">SKU: {{ $handover->asset?->product?->sku ?? '—' }}</div>
                <div class="muted">Tag: {{ $handover->asset?->tag_code ?? '—' }}</div>
            </div>
            <div class="box" style="flex: 1">
                <div class="muted">Verified at</div>
                <div><strong>{{ optional($handover->verified_at)->toDateTimeString() }}</strong></div>
                <div class="muted">Initiated at</div>
                <div>{{ optional($handover->initiated_at)->toDateTimeString() }}</div>
            </div>
        </div>

        <div style="height: 12px"></div>

        <div class="row">
            <div class="box" style="flex: 1">
                <div class="muted">From</div>
                <div><strong>{{ $handover->fromUser?->name ?? '—' }}</strong></div>
                <div class="muted">
                    {{ $handover->fromPosition?->title ?? '—' }}
                    @if($handover->fromPosition?->department?->name)
                        · {{ $handover->fromPosition->department->name }}
                    @endif
                </div>
            </div>
            <div class="box" style="flex: 1">
                <div class="muted">To</div>
                <div><strong>{{ $handover->toUser?->name ?? '—' }}</strong></div>
                <div class="muted">
                    {{ $handover->toPosition?->title ?? '—' }}
                    @if($handover->toPosition?->department?->name)
                        · {{ $handover->toPosition->department->name }}
                    @endif
                </div>
            </div>
        </div>

        <div style="height: 12px"></div>

        <div class="box">
            <div class="muted">Notes</div>
            <div>{{ $handover->notes ?? '—' }}</div>
        </div>

        <div style="height: 12px"></div>

        <div class="box">
            <div class="muted">Recipient signature</div>
            @if($handover->signature_png)
                <div style="margin-top: 8px">
                    <img src="{{ $handover->signature_png }}" style="width: 240px; height: auto" />
                </div>
            @else
                <div style="margin-top: 8px">—</div>
            @endif
            <div class="sig muted">Signature over printed name</div>
        </div>

        <div style="height: 12px"></div>

        <div class="box">
            <div class="muted">Audit summary</div>
            <div>Initiated IP: {{ $handover->ip_address ?? '—' }}</div>
            <div>Verified IP: {{ $handover->verified_ip_address ?? '—' }}</div>
            <div>Verified by: {{ $handover->verifiedBy?->name ?? '—' }}</div>
        </div>
    </body>
</html>
