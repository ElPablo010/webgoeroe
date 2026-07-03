<!DOCTYPE html>
<html lang="nl">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="margin: 0 0 0.5rem;">Nieuwe inzending — {{ $submission->typeLabel() }}</h2>
    <p style="margin: 0 0 1rem; color: #6b7280; font-size: 14px;">
        Ontvangen op {{ $submission->created_at->format('d-m-Y H:i') }}
        @if ($submission->page_url)
            via <a href="{{ $submission->page_url }}" style="color: #2563eb;">{{ $submission->page_url }}</a>
        @endif
    </p>
    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse; font-size: 14px;">
        @foreach ($submission->data as $key => $value)
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <th align="left" style="padding-right: 1rem; vertical-align: top; white-space: nowrap;">
                    {{ ucfirst(str_replace('_', ' ', $key)) }}
                </th>
                <td style="white-space: pre-wrap;">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
