<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28mm 16mm 20mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #1f2937; line-height: 1.45; }
        h1 { font-size: 16pt; margin: 0 0 2mm 0; color: #14532d; }
        h2 { font-size: 11pt; margin: 7mm 0 2mm 0; color: #14532d; border-bottom: 0.6pt solid #bbf7d0; padding-bottom: 1mm; }
        .muted { color: #6b7280; }
        .small { font-size: 7.5pt; }
        .header { border-bottom: 1.2pt solid #14532d; padding-bottom: 3mm; margin-bottom: 4mm; }
        table { width: 100%; border-collapse: collapse; margin-top: 1mm; }
        th { background: #ecfdf5; color: #14532d; text-align: left; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.3pt; padding: 1.6mm; border-bottom: 0.6pt solid #a7f3d0; }
        td { padding: 1.6mm; border-bottom: 0.4pt solid #e5e7eb; vertical-align: top; }
        td.number, th.number { text-align: right; }
        .kpi { width: 100%; border-collapse: separate; border-spacing: 2mm 0; }
        .kpi td { background: #f8fafc; border: 0.4pt solid #e2e8f0; padding: 2.4mm; width: 25%; }
        .kpi .label { font-size: 7pt; text-transform: uppercase; color: #6b7280; letter-spacing: 0.3pt; }
        .kpi .value { font-size: 13pt; font-weight: bold; color: #14532d; }
        .chip { display: inline-block; padding: 0.6mm 1.6mm; border-radius: 2mm; font-size: 7pt; color: #ffffff; }
        .note { background: #fffbeb; border-left: 1.6pt solid #f59e0b; padding: 2mm 2.6mm; margin-top: 2mm; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7pt; color: #9ca3af; }
    </style>
</head>
<body>
<div class="header">
    <h1>re:green — Laporan Monitoring Restorasi Lahan</h1>
    <div class="muted small">
        {{ $snapshot['report']['scope'] === 'all' ? 'Cakupan: seluruh lahan yang terpantau' : 'Cakupan: satu lahan' }}
        &nbsp;·&nbsp; Dibuat {{ \Illuminate\Support\Carbon::parse($snapshot['report']['generated_at'])->translatedFormat('d F Y H:i') }}
        @if ($snapshot['report']['prepared_by'])
            &nbsp;·&nbsp; Disusun oleh {{ $snapshot['report']['prepared_by'] }}
        @endif
    </div>
</div>

<h2>Ringkasan</h2>
<table class="kpi">
    <tr>
        <td>
            <div class="label">Lahan terpantau</div>
            <div class="value">{{ number_format($snapshot['summary']['lands'], 0, ',', '.') }}</div>
            <div class="small muted">{{ $snapshot['summary']['lands_with_analysis'] }} lahan sudah dianalisis</div>
        </td>
        <td>
            <div class="label">Luas terpantau</div>
            <div class="value">{{ number_format((float) $snapshot['summary']['area_ha'], 2, ',', '.') }} ha</div>
            <div class="small muted">{{ $snapshot['summary']['monitoring_periods'] }} periode monitoring</div>
        </td>
        <td>
            <div class="label">Skor kesehatan rata-rata</div>
            <div class="value">{{ $snapshot['summary']['average_health_score'] ?? '—' }}</div>
            <div class="small muted">vegetasi rata-rata {{ $snapshot['summary']['average_vegetation_percentage'] ?? '—' }}%</div>
        </td>
        <td>
            <div class="label">Lahan membaik</div>
            <div class="value">{{ $snapshot['summary']['improving_lands'] }}</div>
            <div class="small muted">dari {{ $snapshot['summary']['lands'] }} lahan</div>
        </td>
    </tr>
</table>

<h2>Potensi Kredit Karbon</h2>
<table>
    <tr>
        <th>Lahan layak diajukan</th>
        <th>Estimasi serapan</th>
        <th>Proyeksi 5 tahun</th>
    </tr>
    <tr>
        <td>{{ $snapshot['summary']['carbon']['eligible_lands'] }} lahan</td>
        <td>{{ number_format((float) $snapshot['summary']['carbon']['pipeline_tco2e_per_year'], 2, ',', '.') }} tCO2e/tahun</td>
        <td>{{ number_format((float) $snapshot['summary']['carbon']['pipeline_5yr_tco2e'], 2, ',', '.') }} tCO2e</td>
    </tr>
</table>
<div class="note small">
    Angka serapan adalah estimasi penyaringan awal (luas bertutup vegetasi × laju serapan per kelas vegetasi), bukan hasil sertifikasi.
    Sertifikasi dilakukan mitra dengan metodologi spesifik lokasi.
</div>

<h2>Sebaran Keparahan Kebakaran</h2>
<table>
    <tr>
        <th>Keparahan</th>
        <th class="number">Jumlah lahan</th>
    </tr>
    @foreach ($snapshot['severity_mix'] as $severity)
        <tr>
            <td><span class="chip" style="background: {{ $severity['color'] }};">&nbsp;</span> {{ $severity['label'] }}</td>
            <td class="number">{{ $severity['count'] }}</td>
        </tr>
    @endforeach
</table>

<h2>Daftar Lahan</h2>
<table>
    <tr>
        <th>Lahan</th>
        <th>Lokasi</th>
        <th class="number">Luas (ha)</th>
        <th class="number">Periode</th>
        <th class="number">Skor</th>
        <th class="number">Vegetasi</th>
        <th>Keparahan</th>
        <th>Progress</th>
        <th class="number">tCO2e/th</th>
    </tr>
    @forelse ($snapshot['lands'] as $land)
        <tr>
            <td>{{ $land['name'] }}</td>
            <td>{{ $land['location_name'] ?? '—' }}</td>
            <td class="number">{{ $land['area_ha'] === null ? '—' : number_format((float) $land['area_ha'], 2, ',', '.') }}</td>
            <td class="number">{{ $land['periods'] }}</td>
            <td class="number">{{ $land['latest_analysis']['health_score'] ?? '—' }}</td>
            <td class="number">{{ $land['latest_analysis'] === null ? '—' : number_format((float) $land['latest_analysis']['vegetation_percentage'], 1, ',', '.').'%' }}</td>
            <td>{{ $land['latest_analysis']['burn_severity_label'] ?? '—' }}</td>
            <td>
                @if ($land['progress']['direction'] === null)
                    —
                @else
                    {{ $land['progress']['direction'] === 'improving' ? 'Membaik' : ($land['progress']['direction'] === 'declining' ? 'Menurun' : 'Stabil') }}
                    ({{ $land['progress']['vegetation_delta'] > 0 ? '+' : '' }}{{ number_format((float) $land['progress']['vegetation_delta'], 1, ',', '.') }} pp)
                @endif
            </td>
            <td class="number">{{ $land['carbon']['sequestration_tco2e_per_year'] === null ? '—' : number_format((float) $land['carbon']['sequestration_tco2e_per_year'], 2, ',', '.') }}</td>
        </tr>
    @empty
        <tr><td colspan="9" class="muted">Belum ada lahan pada cakupan laporan ini.</td></tr>
    @endforelse
</table>

<h2>Bukti dan Asal Data</h2>
<table>
    <tr>
        <th>Model analisis</th>
        <th class="number">Analisis</th>
    </tr>
    @forelse ($snapshot['evidence']['models'] as $model)
        <tr><td>{{ $model['label'] }}</td><td class="number">{{ $model['count'] }}</td></tr>
    @empty
        <tr><td colspan="2" class="muted">Belum ada analisis yang selesai.</td></tr>
    @endforelse
</table>
<table>
    <tr>
        <th>Dataset konteks (DEM / tanah / iklim)</th>
        <th class="number">Analisis</th>
    </tr>
    @forelse ($snapshot['evidence']['datasets'] as $dataset)
        <tr><td>{{ $dataset['label'] }}</td><td class="number">{{ $dataset['count'] }}</td></tr>
    @empty
        <tr><td colspan="2" class="muted">Tidak ada sampling dataset pada analisis di laporan ini.</td></tr>
    @endforelse
</table>

@if (count($snapshot['gaps']) > 0)
    <h2>Keterbatasan Data</h2>
    <ul class="small">
        @foreach ($snapshot['gaps'] as $gap)
            <li>{{ $gap['label'] }}</li>
        @endforeach
    </ul>
@endif

<div class="note small">{{ $snapshot['disclaimer'] }}</div>

<div class="footer">
    re:green · {{ $title }} · dibuat {{ \Illuminate\Support\Carbon::parse($snapshot['report']['generated_at'])->format('Y-m-d H:i') }}
</div>
</body>
</html>
