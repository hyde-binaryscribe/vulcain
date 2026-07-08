<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Protocole #{{ $protocol->id }} — {{ $protocol->vehicle_name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #111; margin: 0; padding: 32px; font-size: 13px; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: #666; margin: 24px 0 8px; border-bottom: 1px solid #eee; padding-bottom: 4px; }
        .muted { color: #666; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 12px; }
        .badges span { display: inline-block; border: 1px solid #ccc; border-radius: 999px; padding: 2px 8px; font-size: 11px; margin-right: 4px; }
        .meta { margin-top: 12px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .meta div { background: #f6f6f6; border-radius: 8px; padding: 8px; }
        .meta .k { font-size: 10px; text-transform: uppercase; color: #888; }
        .meta .v { font-size: 15px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { text-align: left; padding: 5px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
        th { font-size: 10px; text-transform: uppercase; color: #888; }
        .anomaly { background: #fdeaea; }
        .state { font-weight: 600; }
        .state.ok { color: #15803d; }
        .state.ko { color: #b91c1c; }
        .sign { margin-top: 40px; display: flex; gap: 40px; }
        .sign div { flex: 1; border-top: 1px solid #999; padding-top: 6px; font-size: 11px; color: #666; }
        .print-hint { margin-top: 24px; text-align: center; }
        .print-hint button { padding: 8px 16px; border: 0; border-radius: 8px; background: #111; color: #fff; cursor: pointer; }
        @media print { .print-hint { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>Rapport de protocole</h1>
            <p class="muted" style="margin:2px 0 8px">{{ $organisation->name }} — {{ $organisation->profile()->label }}</p>
            <div class="badges">
                @foreach ($protocol->typeLabels() as $label)
                    <span>{{ $label }}</span>
                @endforeach
            </div>
        </div>
        <div style="text-align:right" class="muted">
            <div>Protocole #{{ $protocol->id }}</div>
            @if ($protocol->status === \App\Models\Protocol::STATUS_VALIDATED)
                <div style="color:#15803d; font-weight:700">✓ Validé</div>
            @else
                <div style="color:#b45309; font-weight:700">Brouillon</div>
            @endif
        </div>
    </div>

    <div class="meta">
        <div><div class="k">Cible</div><div class="v" style="font-size:13px">{{ $protocol->vehicle_name }}</div></div>
        <div><div class="k">Vérificateur</div><div class="v" style="font-size:13px">{{ $protocol->verifier?->name ?? '—' }}</div></div>
        <div><div class="k">Contrôlés</div><div class="v">{{ $checked }}/{{ $total }}</div></div>
        <div><div class="k">Anomalies</div><div class="v" style="color:{{ $anomalies > 0 ? '#b91c1c' : '#111' }}">{{ $anomalies }}</div></div>
    </div>
    <p class="muted" style="margin-top:8px">
        Démarré le {{ $protocol->started_at?->format('d/m/Y H:i') }}
        @if ($protocol->validated_at) · Validé le {{ $protocol->validated_at->format('d/m/Y H:i') }} @endif
        @if ($duration) · Durée {{ $duration }} @endif
    </p>

    @foreach ($groups as $group)
        <h2>{{ $group['location'] }}</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:36%">Matériel</th>
                    <th>N° série</th>
                    <th>Attendu</th>
                    <th>Relevé</th>
                    <th>État</th>
                    <th style="width:24%">Observation</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['items'] as $item)
                    <tr class="{{ $item['is_anomaly'] ? 'anomaly' : '' }}">
                        <td>{{ $item['material_name'] }}<br><span class="muted" style="font-size:11px">{{ $item['reference'] }}</span></td>
                        <td>{{ $item['serial_number'] ?? '—' }}</td>
                        <td>{{ $item['tracking_mode'] === 'serial' ? '1' : $item['expected_qty'] }}</td>
                        <td>
                            {{ $item['observed_qty'] ?? ($item['checked'] ? '—' : '') }}
                            @if ($item['observed_expiry'])<br><span class="muted" style="font-size:11px">péremption {{ $item['observed_expiry'] }}</span>@endif
                        </td>
                        <td class="state {{ $item['is_anomaly'] ? 'ko' : 'ok' }}">{{ $item['state_label'] ?? ($item['checked'] ? 'OK' : '—') }}</td>
                        <td>{{ $item['observation'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="sign">
        <div>Vérificateur : {{ $protocol->verifier?->name }}<br><br>Signature</div>
        <div>Responsable<br><br>Signature</div>
    </div>

    <div class="print-hint">
        <button onclick="window.print()">Imprimer / Enregistrer en PDF</button>
    </div>
</body>
</html>
