@props(['section' => null, 'content' => []])

@php
    $bg        = \App\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isDark    = \App\Filament\Schemas\Sections\SectionBackground::isDark($content['background'] ?? null);
    $isFirst   = $content['is_first'] ?? false;
    $mediaType = $content['media_type'] ?? 'image';
    $mediaSide = $content['media_side'] ?? 'right';
    $ctas      = $content['ctas'] ?? [];
    $textOrder  = $mediaSide === 'left' ? 'md:order-2' : 'md:order-1';
    $mediaOrder = $mediaSide === 'left' ? 'md:order-1' : 'md:order-2';

    // Normaliseer media-URLs: voeg /storage/ toe als de opgeslagen waarde
    // enkel een pad is (zonder leading slash of protocol) — veiligheidsnet
    // voor het geval de URL niet correct werd opgeslagen via de admin.
    $normUrl = function (?string $url): ?string {
        if (blank($url)) return null;
        if (str_starts_with($url, '/') || str_starts_with($url, 'http')) return $url;
        return '/storage/' . $url;
    };

    $mediaSrc = $normUrl($content['media']['src'] ?? null);
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto grid max-w-6xl items-center gap-12 px-6 py-20 md:grid-cols-2 md:py-28">

        {{-- Tekst --}}
        <div class="{{ $textOrder }}">
            @if (! empty($content['eyebrow']))
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-3 py-1">
                    <span class="text-xs font-semibold tracking-wider text-cyan-400">{{ $content['eyebrow'] }}</span>
                </div>
            @endif
            @if (! empty($content['heading']))
                @if ($isFirst)
                    <h1 class="text-4xl font-black tracking-tight {{ $isDark ? 'text-white' : 'text-slate-900' }} md:text-5xl mb-8">{{ $content['heading'] }}</h1>
                @else
                    <h2 class="text-3xl font-black tracking-tight {{ $isDark ? 'text-white' : 'text-slate-900' }} md:text-4xl">{{ $content['heading'] }}</h2>
                @endif
            @endif
            @if (! empty($content['intro']))
                <div class="prose mt-4 max-w-none {{ $isDark ? 'prose-invert prose-p:text-white/50' : '' }}">{!! $content['intro'] !!}</div>
            @endif
            @if (! empty($ctas))
                <div class="mt-8 flex flex-wrap gap-3">
                    @foreach ($ctas as $cta)
                        @php
                            $btnClass = match ($cta['variant'] ?? 'primary') {
                                'secondary' => $isDark
                                    ? 'bg-white/[0.08] border border-white/15 text-white hover:bg-white/[0.14]'
                                    : 'bg-slate-100 text-slate-900 hover:bg-slate-200',
                                'ghost' => $isDark
                                    ? 'border border-white/20 text-white/70 hover:bg-white/[0.06] hover:text-white'
                                    : 'border border-slate-300 text-slate-700 hover:border-primary-400 hover:text-primary-700',
                                default => $isDark
                                    ? 'bg-white text-[#050507] hover:bg-white/90'
                                    : 'bg-primary-600 text-white hover:bg-primary-700',
                            };
                        @endphp
                        <a
                            href="{{ \App\Support\Url::resolveCtaHref($cta) }}"
                            class="cursor-pointer rounded-full px-6 py-3 text-sm font-semibold transition-all {{ $btnClass }}"
                        >{{ $cta['label'] ?? '' }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Media --}}
        <div class="{{ $mediaOrder }}">
            @if ($mediaType === 'video' && ! empty($content['video_url']))
                @php
                    preg_match('/(?:youtu\.be\/|youtube\.com(?:\/embed\/|\/v\/|\/watch\?v=|\/watch\?.+&v=))([\w-]+)/', $content['video_url'], $m);
                    $ytId = $m[1] ?? null;
                @endphp
                @if ($ytId)
                    <div class="aspect-video overflow-hidden rounded-2xl {{ $isDark ? 'shadow-[0_0_0_1px_rgba(255,255,255,0.06)]' : 'shadow-lg' }}">
                        <iframe
                            src="https://www.youtube.com/embed/{{ $ytId }}"
                            class="h-full w-full"
                            frameborder="0"
                            allowfullscreen
                            loading="lazy"
                        ></iframe>
                    </div>
                @else
                    <video src="{{ $content['video_url'] }}" controls class="w-full rounded-2xl shadow-lg" loading="lazy"></video>
                @endif
            @elseif ($mediaType === 'images')
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($content['images'] ?? [] as $img)
                        @php $imgSrc = $normUrl($img['src'] ?? null); @endphp
                        @if ($imgSrc)
                            <picture>
                                <source srcset="{{ $imgSrc }}" type="image/webp">
                                <img src="{{ $imgSrc }}" alt="{{ $img['alt'] ?? '' }}" class="w-full rounded-xl object-cover" loading="lazy">
                            </picture>
                        @endif
                    @endforeach
                </div>
            @elseif ($mediaSrc)
                <picture>
                    <source srcset="{{ $mediaSrc }}" type="image/webp">
                    <img
                        src="{{ $mediaSrc }}"
                        alt="{{ $content['media']['alt'] ?? '' }}"
                        class="w-full rounded-2xl object-cover {{ $isDark ? 'shadow-[0_0_0_1px_rgba(255,255,255,0.06)]' : 'shadow-lg' }}"
                        loading="lazy"
                    >
                </picture>
            @else
                <div class="aspect-video w-full rounded-2xl {{ $isDark ? 'border border-white/[0.06] bg-white/[0.03]' : 'bg-slate-100' }}"></div>
            @endif
        </div>
    </div>
</x-site.sections.wrapper>
