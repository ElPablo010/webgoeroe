@props(['section' => null, 'content' => []])

@php
    $bg      = \App\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isFirst = $content['is_first'] ?? false;

    $query = \App\Models\CaseStudy::query()
        ->where('published', true)
        ->orderByDesc('featured')
        ->orderByDesc('updated_at');

    if (! empty($content['filter_industry'])) {
        $query->where('industry', $content['filter_industry']);
    }

    if (! empty($content['filter_tags'])) {
        foreach ((array) $content['filter_tags'] as $tag) {
            $query->whereJsonContains('tags', $tag);
        }
    }

    if (! empty($content['limit'])) {
        $query->limit((int) $content['limit']);
    }

    $cases = $query->get();
    $ctas  = $content['cta'] ?? [];
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-6xl px-6 py-20 md:py-28">

        @if (! empty($content['heading']))
            <div class="mx-auto mb-14 max-w-2xl text-center">
                @if (! empty($content['eyebrow']))
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-3 py-1">
                        <span class="text-xs font-semibold tracking-wider text-cyan-400">{{ $content['eyebrow'] }}</span>
                    </div>
                @endif
                @if ($isFirst)
                    <h1 class="text-4xl font-black tracking-tight text-white md:text-5xl mb-8">{{ $content['heading'] }}</h1>
                @else
                    <h2 class="text-3xl font-black tracking-tight text-white md:text-4xl">{{ $content['heading'] }}</h2>
                @endif
                @if (! empty($content['intro']))
                    <div class="prose prose-invert mx-auto mt-4 prose-p:text-white/50">{!! $content['intro'] !!}</div>
                @endif
            </div>
        @endif

        @if ($cases->isNotEmpty())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cases as $case)
                    <a
                        href="{{ $case->publicUrl() }}"
                        class="group relative flex flex-col overflow-hidden rounded-2xl border border-white/[0.08] bg-white/[0.04] backdrop-blur-sm transition-all duration-300 hover:border-white/[0.14] hover:bg-white/[0.06] hover:shadow-[0_0_0_1px_rgba(34,211,238,0.1),0_8px_40px_rgba(34,211,238,0.04)]"
                    >
                        @if ($case->featured)
                            <div class="absolute right-3 top-3 z-10 rounded-full bg-primary-600/90 px-2.5 py-0.5 text-xs font-semibold text-white backdrop-blur-sm">
                                Uitgelicht
                            </div>
                        @endif

                        @if ($case->cover_url)
                            <div class="aspect-video w-full overflow-hidden">
                                <picture>
                                    <source srcset="{{ $case->cover_url }}" type="image/webp">
                                    <img
                                        src="{{ $case->cover_url }}"
                                        alt="{{ $case->cover_alt ?: $case->title }}"
                                        class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.02]"
                                    >
                                </picture>
                            </div>
                        @else
                            <div class="aspect-video w-full bg-gradient-to-br from-primary-600/20 to-cyan-400/10"></div>
                        @endif

                        <div class="flex flex-1 flex-col p-6">
                            @if ($case->industry)
                                <div class="mb-3 inline-flex items-center gap-1.5 self-start rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-2.5 py-0.5">
                                    <span class="text-xs font-semibold tracking-wide text-cyan-400">{{ $case->industry }}</span>
                                </div>
                            @endif

                            <h3 class="text-lg font-bold text-white">{{ $case->title }}</h3>

                            @if ($case->client)
                                <p class="mt-0.5 text-sm text-white/40">{{ $case->client }}</p>
                            @endif

                            @if ($case->excerpt)
                                <p class="mt-3 flex-1 text-sm leading-relaxed text-white/50 line-clamp-3">{{ $case->excerpt }}</p>
                            @endif

                            @if (! empty($case->tags))
                                <div class="mt-4 flex flex-wrap gap-1.5">
                                    @foreach ($case->tags as $tag)
                                        <span class="rounded-full border border-white/[0.08] bg-white/[0.04] px-2.5 py-0.5 text-xs text-white/50">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-5 flex items-center gap-1.5 text-sm font-semibold text-white/50 transition-colors group-hover:text-white">
                                Lees case study
                                <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        @if (! empty($ctas))
            <div class="mt-12 flex flex-wrap justify-center gap-3">
                @foreach ($ctas as $cta)
                    @php $variant = $cta['variant'] ?? 'primary'; @endphp
                    @if ($variant === 'primary')
                        <a
                            href="{{ \App\Support\Url::resolveCtaHref($cta) }}"
                            class="cursor-pointer px-7 py-3.5 text-sm font-semibold transition-all"
                            style="background:#fff; color:#000; border-radius:100px;"
                            onmouseenter="this.style.background='rgba(255,255,255,0.92)'; this.style.boxShadow='0 0 40px rgba(255,255,255,0.2),0 8px 30px rgba(0,0,0,0.4)'; this.style.transform='translateY(-1px)'"
                            onmouseleave="this.style.background='#fff'; this.style.boxShadow=''; this.style.transform=''"
                        >{{ $cta['label'] ?? '' }}</a>
                    @else
                        <a
                            href="{{ \App\Support\Url::resolveCtaHref($cta) }}"
                            class="cursor-pointer px-7 py-3.5 text-sm font-semibold transition-all"
                            style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); color:rgba(255,255,255,0.7); border-radius:100px;"
                            onmouseenter="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)'; this.style.color='#fff'; this.style.transform='translateY(-1px)'"
                            onmouseleave="this.style.background='rgba(255,255,255,0.06)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.color='rgba(255,255,255,0.7)'; this.style.transform=''"
                        >{{ $cta['label'] ?? '' }}</a>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</x-site.sections.wrapper>
