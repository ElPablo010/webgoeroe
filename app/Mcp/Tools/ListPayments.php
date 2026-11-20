<?php

namespace App\Mcp\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Webgoeroe\Core\Models\AgendaBooking;
use Webgoeroe\Core\Models\Payment;
use Webgoeroe\Core\Payments\PaymentStatus;

#[Description('Lijst de betaalde betalingen van de site op (sinds een datum, standaard 14 dagen terug), met alles wat nodig is voor de factuur: klant, bedrijfsnaam en btw-nummer (als de klant ze invulde), omschrijving, bedrag excl. btw, btw en incl. btw, provider en betaal-id. Enkel betalingen met status betaald; open, mislukte en vervallen betalingen komen er niet in.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class ListPayments extends Tool
{
    protected string $name = 'list_payments';

    /** Labels van de optionele boekingsvelden waaruit bedrijfsnaam en btw-nummer komen. */
    private const COMPANY_LABELS = ['bedrijfsnaam', 'bedrijf'];

    private const VAT_LABELS = ['btw-nummer', 'btw nummer', 'btw'];

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $since = filled($validated['since'] ?? null)
            ? CarbonImmutable::parse($validated['since'])->startOfDay()
            : CarbonImmutable::now()->subDays(14)->startOfDay();

        $payments = Payment::query()
            ->where('status', PaymentStatus::Paid->value)
            ->where('paid_at', '>=', $since)
            ->with('payable')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->limit($validated['limit'] ?? 100)
            ->get();

        return Response::json([
            'since' => $since->toDateString(),
            'count' => $payments->count(),
            'payments' => $payments->map(fn (Payment $payment) => $this->summarize($payment))->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function summarize(Payment $payment): array
    {
        $answers = $payment->payable instanceof AgendaBooking
            ? $this->normalizedAnswers((array) $payment->payable->answers)
            : [];

        $paidAt = $payment->paid_at?->timezone(config('app.timezone'));

        return [
            'id' => $payment->id,
            'paid_at' => $paidAt?->toIso8601String(),
            'customer_name' => $payment->customer_name,
            'email' => $payment->customer_email,
            'company' => $this->firstAnswer($answers, self::COMPANY_LABELS),
            'vat_number' => $this->firstAnswer($answers, self::VAT_LABELS),
            'kind' => $payment->kind,
            'description' => $payment->description,
            'amount_excl' => $this->euro($payment->amount_excl_cents),
            'vat' => $this->euro($payment->vat_cents),
            'amount_incl' => $this->euro($payment->amount_cents),
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'provider_payment_id' => $payment->provider_payment_id,
            'is_test' => (bool) $payment->is_test,
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, string>
     */
    private function normalizedAnswers(array $answers): array
    {
        $result = [];

        foreach ($answers as $label => $value) {
            if (is_string($label) && is_scalar($value)) {
                $result[mb_strtolower(trim($label))] = trim((string) $value);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $answers
     * @param  list<string>  $labels
     */
    private function firstAnswer(array $answers, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (($answers[$label] ?? '') !== '') {
                return $answers[$label];
            }
        }

        return null;
    }

    private function euro(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()
                ->description('Optioneel. Datum (jjjj-mm-dd): enkel betalingen betaald vanaf die dag. Standaard 14 dagen terug.'),
            'limit' => $schema->integer()
                ->description('Maximum aantal resultaten (1-200, standaard 100).'),
        ];
    }
}
