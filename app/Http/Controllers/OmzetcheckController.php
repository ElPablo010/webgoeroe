<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Services\GoHighLevel\OmzetcheckContact;
use App\Services\Meta\ConversionsApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gate op de omzetcheck: de bezoeker laat voornaam en e-mail achter om zijn
 * resultaat te zien. Hier rekenen we het resultaat opnieuw uit (zelfde formule
 * als de calculator in de browser), bewaren de inzending (telt als lead in de
 * Groei-meetlaag) en sturen het contact naar GoHighLevel.
 */
class OmzetcheckController extends Controller
{
    public function store(Request $request, OmzetcheckContact $ghl, ConversionsApi $meta): JsonResponse
    {
        // Honeypot: een bot vult het verborgen veld in. Doe alsof het gelukt is.
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true]);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'sector' => ['nullable', 'string', 'max:100'],
            'visitors' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'leads' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'close' => ['required', 'numeric', 'min:0', 'max:100'],
            'value' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'target' => ['nullable', 'numeric', 'min:0', 'max:100'],
            ...ConversionsApi::RULES,
        ]);

        $attribution = ConversionsApi::attribution($request);

        $result = self::calculate(
            (float) $data['visitors'],
            (float) $data['leads'],
            (float) $data['close'],
            (float) $data['value'],
            (float) ($data['target'] ?? 3),
        );

        $amount = '€ '.number_format($result['yearly'], 0, ',', '.');
        $extra = preg_replace('/,0$/', '', number_format($result['extra_leads'], 1, ',', '.'));

        FormSubmission::create([
            'type' => 'omzetcheck',
            'data' => [
                'first_name' => $data['first_name'],
                'email' => $data['email'],
                'sector' => $data['sector'] ?? null,
                'visitors' => $data['visitors'],
                'leads' => $data['leads'],
                'close' => $data['close'],
                'value' => $data['value'],
                'yearly' => round($result['yearly']),
                'extra_leads' => round($result['extra_leads'], 1),
                'attribution' => $attribution ?: null,
            ],
            'page_url' => $request->headers->get('referer'),
            'ip' => $request->ip(),
        ]);

        $ghl->send([
            'first_name' => $data['first_name'],
            'email' => $data['email'],
            'sector' => (string) ($data['sector'] ?? ''),
            'amount' => $amount,
            'extra_leads' => $extra,
            'attribution' => ConversionsApi::attributionLine($attribution),
        ]);

        app()->terminating(fn () => $meta->send('Lead', $request, ['email' => $data['email'], 'first_name' => $data['first_name']], array_filter(['content_category' => $data['sector'] ?? null])));

        return response()->json(['ok' => true]);
    }

    /**
     * Zelfde formule als de Alpine-calculator (conversion-calculator.blade.php):
     * doelconversie = target, of het dubbele van de huidige als die al hoger ligt.
     *
     * @return array{current: float, goal: float, extra_leads: float, yearly: float}
     */
    public static function calculate(float $visitors, float $leads, float $close, float $value, float $target): array
    {
        $current = $visitors > 0 ? max(0, $leads) / $visitors * 100 : 0;
        $goal = $current >= $target ? $current * 2 : $target;
        $extra = max(0, $visitors) * ($goal - $current) / 100;
        $yearly = $extra * min(100, max(0, $close)) / 100 * max(0, $value) * 12;

        return ['current' => $current, 'goal' => $goal, 'extra_leads' => $extra, 'yearly' => $yearly];
    }
}
