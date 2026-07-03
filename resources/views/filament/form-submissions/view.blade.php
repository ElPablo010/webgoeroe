@php
    /** @var \App\Models\FormSubmission $record */
@endphp

{{-- Filament-admin view: layout-kritische styling inline (de app-Tailwind wordt
     hier niet geladen). --}}
<div style="display:flex; flex-direction:column; gap:0.75rem; font-size:0.9rem;">
    <div style="font-size:0.8rem; opacity:0.7;">
        Ontvangen {{ $record->created_at->format('d-m-Y H:i') }}
        @if ($record->page_url)
            · <a href="{{ $record->page_url }}" style="text-decoration:underline;">{{ $record->page_url }}</a>
        @endif
    </div>

    <dl style="display:grid; grid-template-columns: max-content 1fr; gap:0.5rem 1rem; margin:0;">
        @foreach ($record->data as $key => $value)
            <dt style="font-weight:600;">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
            <dd style="margin:0; white-space:pre-wrap; word-break:break-word;">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</dd>
        @endforeach
    </dl>
</div>
