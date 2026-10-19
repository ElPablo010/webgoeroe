<?php

namespace App\Services\Billit;

use App\Models\DepositInvoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Getekende offerte (GoHighLevel) → voorschotfactuur in Billit.
 *
 * Idempotent per getekend document: een herhaalde webhook maakt nooit een
 * tweede factuur. Is de factuur al aangemaakt maar niet verstuurd, dan
 * probeert een nieuwe webhook enkel het versturen opnieuw.
 */
class DepositInvoiceService
{
    public function __construct(private BillitClient $billit) {}

    /**
     * @param  array{source_id: string, opportunity_id?: ?string, project?: ?string, amount_excl: float, contact: array}  $data
     */
    public function handle(array $data): DepositInvoice
    {
        $percentage = (float) config('services.billit.deposit_percentage');

        $invoice = DepositInvoice::firstOrCreate(
            ['source_id' => $data['source_id']],
            [
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'project' => $data['project'] ?? null,
                'customer_name' => $this->customerName($data['contact']),
                'customer_email' => $data['contact']['email'] ?? null,
                'customer_vat' => $this->vatNumber($data['contact']['vat_number'] ?? null),
                'deal_amount_excl' => $data['amount_excl'],
                'amount_excl' => round($data['amount_excl'] * $percentage / 100, 2),
                'payload' => $data,
            ],
        );

        if ($invoice->status === DepositInvoice::STATUS_SENT) {
            return $invoice;
        }

        if (! $this->billit->isConfigured()) {
            return $this->fail($invoice, 'Billit is niet ingesteld (BILLIT_API_KEY / BILLIT_PARTY_ID ontbreken).');
        }

        if (! $invoice->billit_order_id) {
            $orderId = $this->billit->createOrder($this->order($invoice));

            if (! $orderId) {
                return $this->fail($invoice, $this->billit->lastError());
            }

            $invoice->update(['billit_order_id' => $orderId, 'status' => DepositInvoice::STATUS_CREATED, 'error' => null]);
        }

        if (! $this->billit->sendOrder($invoice->billit_order_id)) {
            return $this->fail($invoice, $this->billit->lastError());
        }

        $invoice->update(['status' => DepositInvoice::STATUS_SENT, 'sent_at' => now(), 'error' => null]);

        return $invoice;
    }

    /**
     * De Billit-order. Geen OrderNumber: Billit nummert zelf in de gewone
     * reeks. De klant wordt op btw-nummer/e-mail gezocht of aangemaakt.
     */
    public function order(DepositInvoice $invoice): array
    {
        $contact = $invoice->payload['contact'] ?? [];
        $percentage = (int) config('services.billit.deposit_percentage');
        $project = $invoice->project ?: 'project';
        [$street, $number] = $this->splitStreet($contact['street'] ?? null);

        $customer = array_filter([
            'Name' => $invoice->customer_name,
            'VATNumber' => $invoice->customer_vat,
            'PartyType' => 'Customer',
            'Email' => $invoice->customer_email,
            'Phone' => $contact['phone'] ?? null,
            'Language' => 'NL',
            'ContactFirstName' => $contact['first_name'] ?? null,
            'ContactLastName' => $contact['last_name'] ?? null,
            'Addresses' => filled($street) ? [array_filter([
                'AddressType' => 'InvoiceAddress',
                'Name' => $invoice->customer_name,
                'Street' => $street,
                'StreetNumber' => $number,
                'Zipcode' => $contact['postal_code'] ?? null,
                'City' => $contact['city'] ?? null,
                'CountryCode' => $this->countryCode($contact['country'] ?? null),
            ])] : null,
        ], fn ($value) => filled($value));

        return [
            'OrderType' => 'Invoice',
            'OrderDirection' => 'Income',
            'OrderDate' => now()->toDateString(),
            'ExpiryDate' => now()->addDays((int) config('services.billit.payment_days'))->toDateString(),
            'OrderTitle' => "Voorschot {$project}",
            'Customer' => $customer,
            'OrderLines' => [[
                'Quantity' => 1,
                'UnitPriceExcl' => (float) $invoice->amount_excl,
                'Description' => "Voorschot {$percentage}% - {$project}",
                'DescriptionExtended' => "Voorschot van {$percentage}% bij start, volgens de getekende offerte (totaal € "
                    .number_format((float) $invoice->deal_amount_excl, 2, ',', '.').' excl. btw).',
                'VATPercentage' => (float) config('services.billit.vat_percentage'),
            ]],
        ];
    }

    private function fail(DepositInvoice $invoice, ?string $error): DepositInvoice
    {
        $error ??= 'Onbekende fout bij Billit.';
        $invoice->update(['status' => DepositInvoice::STATUS_FAILED, 'error' => $error]);

        Log::error('Voorschotfactuur mislukt', ['deposit_invoice' => $invoice->id, 'error' => $error]);

        rescue(fn () => Mail::raw(
            "De voorschotfactuur voor {$invoice->customer_name} ({$invoice->project}) is niet gelukt.\n\n"
            ."Reden: {$error}\n\n"
            .($invoice->billit_order_id
                ? "De factuur staat wel in Billit (order {$invoice->billit_order_id}), maar is nog niet verstuurd.\n"
                : "Er is nog niets aangemaakt in Billit.\n")
            .'Bedrag voorschot: € '.number_format((float) $invoice->amount_excl, 2, ',', '.').' excl. btw.',
            fn ($message) => $message
                ->to(config('services.billit.notify'))
                ->subject("Voorschotfactuur mislukt: {$invoice->customer_name}"),
        ), report: false);

        return $invoice;
    }

    private function customerName(array $contact): string
    {
        $person = trim(($contact['first_name'] ?? '').' '.($contact['last_name'] ?? ''));

        return trim((string) ($contact['company'] ?? '')) ?: ($person ?: ($contact['email'] ?? 'Onbekende klant'));
    }

    /** "be 0123.456.789" → "BE0123456789"; tien cijfers zonder land → Belgisch. */
    private function vatNumber(?string $vat): ?string
    {
        $vat = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $vat));

        if ($vat === '') {
            return null;
        }

        return preg_match('/^\d{9,10}$/', $vat) ? 'BE'.str_pad($vat, 10, '0', STR_PAD_LEFT) : $vat;
    }

    /** GoHighLevel bewaart straat en nummer in één veld ("Hallaarstraat 57C"). */
    private function splitStreet(?string $address): array
    {
        $address = trim((string) $address);

        if (preg_match('/^(.*?)[\s,]+(\d+\s*[A-Za-z]?(?:\s*(?:bus|\/)\s*\S+)?)$/u', $address, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [$address, null];
    }

    private function countryCode(?string $country): string
    {
        $country = trim((string) $country);

        if (strlen($country) === 2) {
            return strtoupper($country);
        }

        return match (mb_strtolower($country)) {
            'nederland', 'netherlands', 'the netherlands' => 'NL',
            'frankrijk', 'france' => 'FR',
            'duitsland', 'germany' => 'DE',
            'luxemburg', 'luxembourg' => 'LU',
            default => 'BE',
        };
    }
}
