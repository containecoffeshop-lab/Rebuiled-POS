@extends('layouts.app')

@section('title', 'Cetak label barcode')

@section('content')
<div class="dashboard-head">
    <div><p class="eyebrow" style="color:#65806c;margin-bottom:5px">Pratinjau cetak</p><h1>Label barcode</h1><p>{{ $template->name }} · {{ $pages->flatten()->count() }} label</p></div>
    <div class="nav-links"><a class="button button-secondary" href="{{ route('pos.barcode-templates.index') }}">Kembali</a><button class="button" type="button" data-print-labels>Cetak</button></div>
</div>
<div data-print-error role="status" class="alert" hidden>Barcode gagal dibuat. Periksa kode menu atau pilih menu lain.</div>
@if ($template->paper_type === 'sheet')
    <style>
        @page { size: {{ $template->paper_width_mm }}mm {{ $template->paper_height_mm }}mm; margin: 0; }
        .label-page { width: {{ $template->paper_width_mm }}mm; height: {{ $template->paper_height_mm }}mm; box-sizing: border-box; padding: {{ $template->margin_top_mm }}mm {{ $template->margin_left_mm }}mm; display: grid; grid-template-columns: repeat({{ $template->columns }}, {{ $template->label_width_mm }}mm); grid-template-rows: repeat({{ $template->rows }}, {{ $template->label_height_mm }}mm); column-gap: {{ $template->gap_horizontal_mm }}mm; row-gap: {{ $template->gap_vertical_mm }}mm; page-break-after: always; overflow: hidden; }
        .barcode-label { width: {{ $template->label_width_mm }}mm; height: {{ $template->label_height_mm }}mm; box-sizing: border-box; border: 1px dashed #cbd4ca; display:flex; flex-direction:column; align-items:center; justify-content:center; overflow:hidden; padding:1mm; color:#111; font-family:var(--font-sans); text-align:center; }
        .barcode-label-name { max-width:100%; overflow:hidden; white-space:nowrap; font-size:9pt; font-weight:600; }
        .barcode-label-price { font-size:9pt; }
        .barcode-label svg { max-width:100%; max-height:16mm; }
        @media print { .label-page { border:0!important; margin:0!important; } }
        @media print { body > header, body > footer, main.shell > :not([data-barcode-print-root]) { display:none!important; } main.shell { width:100%; max-width:none; margin:0; padding:0; } }
    </style>
@else
    <style>
        @page { size: {{ $template->paper_width_mm }}mm {{ $template->label_height_mm + 2 * $template->margin_top_mm }}mm; margin: 0; }
        .label-page { width: {{ $template->paper_width_mm }}mm; height: {{ $template->label_height_mm + 2 * $template->margin_top_mm }}mm; box-sizing: border-box; padding: {{ $template->margin_top_mm }}mm {{ $template->margin_left_mm }}mm; display:flex; justify-content:center; page-break-after:always; overflow:hidden; }
        .barcode-label { width: {{ $template->label_width_mm }}mm; height: {{ $template->label_height_mm }}mm; box-sizing:border-box; display:flex; flex-direction:column; align-items:center; justify-content:center; overflow:hidden; padding:1mm; color:#111; font-family:var(--font-sans); text-align:center; }
        .barcode-label-name { max-width:100%; overflow:hidden; white-space:nowrap; font-size:9pt; font-weight:600; }
        .barcode-label-price { font-size:9pt; }
        .barcode-label svg { max-width:100%; max-height:16mm; }
        @media print { .label-page { border:0!important; margin:0!important; } }
        @media print { body > header, body > footer, main.shell > :not([data-barcode-print-root]) { display:none!important; } main.shell { width:100%; max-width:none; margin:0; padding:0; } }
    </style>
@endif
<div data-barcode-print-root>
    @foreach ($pages as $page)
        <section class="label-page">
            @foreach ($page as $menu)
                <article class="barcode-label">
                    <div class="barcode-label-name">{{ $menu->name }}</div>
                    <svg data-label-barcode="{{ $menu->barcode }}" aria-label="Barcode {{ $menu->barcode }}"></svg>
                    <div>{{ $menu->barcode }}</div>
                    <div class="barcode-label-price">Rp{{ number_format($menu->selling_price, 0, ',', '.') }}</div>
                </article>
            @endforeach
        </section>
    @endforeach
</div>
@endsection
