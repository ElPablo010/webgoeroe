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

@if(!empty($backlog))
---

## Verbeteracties

@if($backlog['blocked'])
**Deze week geen nieuwe voorstellen** — er {{ $backlog['open'] === 1 ? 'staat er nog 1 open' : 'staan er nog '.$backlog['open'].' open' }}. Werk je lijst af, dan staan er bij de volgende analyse {{ $backlog['limit'] }} nieuwe klaar.

@foreach($backlog['items'] as $item)
- {{ $item['title'] }} — {{ $item['days'] === 0 ? 'vandaag' : $item['days'].' '.($item['days'] === 1 ? 'dag' : 'dagen').' oud' }}
@endforeach

@if($backlog['next_expiry'])
Kom je er niet aan toe? Dan vervalt het oudste voorstel vanzelf op {{ $backlog['next_expiry']->format('d/m/Y') }} — je hoeft niets op te ruimen.
@endif
@elseif($backlog['created'] > 0)
Er {{ $backlog['created'] === 1 ? 'staat 1 nieuw voorstel' : 'staan '.$backlog['created'].' nieuwe voorstellen' }} klaar ter beoordeling.
@else
Geen nieuwe voorstellen deze week — de cijfers gaven geen aanleiding tot een concrete actie.
@endif

@if($backlog['expired'] > 0)
{{ $backlog['expired'] }} {{ $backlog['expired'] === 1 ? 'ouder voorstel is' : 'oudere voorstellen zijn' }} vervallen: te lang blijven liggen om nog op de huidige cijfers te kloppen.
@endif
@endif

<x-mail::button :url="$dashboardUrl">
Open het SEO-dashboard
</x-mail::button>

Groeten,<br>
SEO-monitoring
</x-mail::message>
