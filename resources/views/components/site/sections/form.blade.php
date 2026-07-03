@props(['section' => null, 'content' => []])

@php
    $bg        = \App\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isDark    = \App\Filament\Schemas\Sections\SectionBackground::isDark($content['background'] ?? null);
    $isFirst   = $content['is_first'] ?? false;
    $layout    = $content['form_layout'] ?? 'right';
    $formType  = $content['form_type'] ?? 'contact';

    $formComponent = match ($formType) {
        'contact' => 'forms.contact-form',
        default   => null,
    };

    $isStacked  = $layout === 'below';
    $textOrder  = $layout === 'left' ? 'md:order-2' : 'md:order-1';
    $formOrder  = $layout === 'left' ? 'md:order-1' : 'md:order-2';
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-6xl px-6 py-20 md:py-28">
        <div class="grid gap-12 @unless ($isStacked) md:grid-cols-2 md:items-start @endunless">

            <div @unless ($isStacked) class="{{ $textOrder }}" @endunless>
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

                @php
                    $contactItems = array_filter([
                        ! empty($content['contact_email']) ? [
                            'label' => 'Email',
                            'value' => $content['contact_email'],
                            'href'  => 'mailto:' . $content['contact_email'],
                            'icon'  => 'mail',
                        ] : null,
                        ! empty($content['contact_phone']) ? [
                            'label' => 'Telefoon',
                            'value' => $content['contact_phone'],
                            'href'  => 'tel:' . preg_replace('/\s+/', '', $content['contact_phone']),
                            'icon'  => 'phone',
                        ] : null,
                        ! empty($content['contact_address']) ? [
                            'label' => 'Adres',
                            'value' => $content['contact_address'],
                            'href'  => null,
                            'icon'  => 'map-pin',
                        ] : null,
                    ]);
                @endphp

                @if (! empty($contactItems))
                    <div class="mt-8 flex flex-col gap-6">
                        @foreach ($contactItems as $item)
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $isDark ? 'bg-white/[0.07]' : 'bg-cyan-50' }}">
                                    @if ($item['icon'] === 'mail')
                                        <svg class="h-4 w-4 {{ $isDark ? 'text-cyan-400' : 'text-cyan-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                                    @elseif ($item['icon'] === 'phone')
                                        <svg class="h-4 w-4 {{ $isDark ? 'text-cyan-400' : 'text-cyan-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
                                    @else
                                        <svg class="h-4 w-4 {{ $isDark ? 'text-cyan-400' : 'text-cyan-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider {{ $isDark ? 'text-white/40' : 'text-slate-400' }}">{{ $item['label'] }}</p>
                                    @if ($item['href'])
                                        <a href="{{ $item['href'] }}" class="mt-0.5 text-sm font-medium {{ $isDark ? 'text-white hover:text-cyan-400' : 'text-slate-800 hover:text-cyan-600' }} transition-colors">{{ $item['value'] }}</a>
                                    @else
                                        <p class="mt-0.5 text-sm font-medium {{ $isDark ? 'text-white/80' : 'text-slate-800' }}">{{ $item['value'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div @unless ($isStacked) class="{{ $formOrder }}" @endunless>
                @if ($formComponent)
                    @livewire($formComponent)
                @else
                    <p class="text-sm text-red-500">Onbekend formuliertype: {{ $formType }}</p>
                @endif
            </div>
        </div>
    </div>
</x-site.sections.wrapper>
