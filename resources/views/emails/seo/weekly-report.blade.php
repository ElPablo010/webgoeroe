@php
    $l = $context['latest'] ?? null;
    $p = $context['previous'] ?? null;
    $s = $context['stats'] ?? [];
    $fmt = fn ($v) => $v === null ? '—' : number_format($v, 0, ',', '.');
    $delta = function ($field) use ($l, $p) {
        if (!$l || !$p || $l->$field === null || $p->$field === null) return null;
        return $l->$field - $p->$field;
    };
@endphp

<x-mail::message>
# SEO stand van zaken

Hier is je wekelijkse overzicht voor **{{ $context['target'] }}**.

@if($l)
<x-mail::table>
| Cijfer | Nu | Vorige week |
|:-------|:---|:------------|
| Geschat verkeer/maand | {{ $fmt($l->organic_etv) }} | {{ $fmt(optional($p)->organic_etv) }} |
| Keywords in Google | {{ $fmt($l->organic_keywords_count) }} | {{ $fmt(optional($p)->organic_keywords_count) }} |
| Top 3 / Top 10 | {{ $s['top3'] ?? 0 }} / {{ $s['top10'] ?? 0 }} | — |
| Gem. positie (opgevolgd) | {{ $s['avg_position'] ?? '—' }} | — |
| AI Overview geciteerd | {{ $s['ai_cited'] ?? 0 }}× | — |
</x-mail::table>
@else
Er is nog geen data verzameld deze week.
@endif

@if(!empty($context['up']))
**Gestegen:** {{ collect($context['up'])->map(fn ($m) => $m['keyword'].' (+'.$m['delta'].' → #'.$m['rank'].')')->implode(', ') }}
@endif

@if(!empty($context['down']))
**Gedaald:** {{ collect($context['down'])->map(fn ($m) => $m['keyword'].' ('.$m['delta'].' → #'.$m['rank'].')')->implode(', ') }}
@endif

@if($advice)
---

{!! \Illuminate\Support\Str::markdown($advice) !!}
@endif

<x-mail::button :url="$dashboardUrl">
Open het SEO-dashboard
</x-mail::button>

Groeten,<br>
SEO-monitoring
</x-mail::message>
