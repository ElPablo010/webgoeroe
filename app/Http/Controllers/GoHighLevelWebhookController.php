<?php

namespace App\Http\Controllers;

use App\Models\DepositInvoice;
use App\Services\Billit\DepositInvoiceService;
use App\Services\GoHighLevel\OpportunityStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Webhooks uit GoHighLevel-workflows ("Custom Webhook"-actie). Beveiligd met
 * een gedeeld geheim in de header X-Webhook-Secret.
 */
class GoHighLevelWebhookController extends Controller
{
    /**
     * Offerte getekend → voorschotfactuur in Billit + deal naar de voorschotstap.
     *
     * De workflow stuurt het contact (contact_id) en de documentnaam; de site
     * zoekt zelf de juiste deal (OpportunityStage::findForQuote) en neemt
     * naam en waarde uit die deal. Vindt hij er geen eenduidige, dan komt er
     * geen factuur en krijgt Pieter een mail. De oude body (opportunity_id +
     * amount_excl) blijft werken.
     */
    public function quoteSigned(Request $request, DepositInvoiceService $service, OpportunityStage $stage): JsonResponse
    {
        $this->authorizeWebhook($request);

        try {
            $data = $this->validated($request);
        } catch (ValidationException $e) {
            // Als error: op Combell staat LOG_LEVEL=error.
            Log::error('GoHighLevel webhook offerte-getekend: ongeldige body', [
                'errors' => $e->errors(),
                'content_type' => $request->header('Content-Type'),
                'payload' => $request->except('contact.vat_number'),
            ]);

            throw $e;
        }

        if (filled($data['contact_id'] ?? null)) {
            $found = $stage->findForQuote($data['contact_id'], $data['document_name'] ?? null);
            $opportunity = $found['opportunity'];

            if (! $opportunity) {
                return $this->unmatched('getekend', $data, $found['reason']);
            }

            $data['opportunity_id'] = $opportunity['id'];
            $data['project'] = $opportunity['name'] ?? ($data['document_name'] ?? null);
            $data['amount_excl'] = $opportunity['monetaryValue'] ?? 0;
            $sourceId = filled($data['document_url'] ?? null)
                ? 'doc:'.sha1($data['document_url'])
                : 'opportunity:'.$opportunity['id'];
        } else {
            $sourceId = $data['document_id'] ?? 'opportunity:'.$data['opportunity_id'];
        }

        $amount = $this->amount($data['amount_excl'] ?? 0);

        if ($amount <= 0) {
            return $this->unmatched('getekend', $data, 'De deal "'.($data['project'] ?? '?').'" heeft geen waarde (excl. btw).', 422);
        }

        $invoice = $service->handle([
            ...$data,
            'source_id' => $sourceId,
            'amount_excl' => $amount,
        ]);

        // Getekend is getekend: de deal schuift door, ook als de factuur faalt.
        $movedTo = filled($data['opportunity_id'] ?? null)
            ? $stage->moveTo($data['opportunity_id'], OpportunityStage::DEPOSIT)
            : null;

        return response()->json([
            'status' => $invoice->status,
            'billit_order_id' => $invoice->billit_order_id,
            'error' => $invoice->error,
            'stage' => $movedTo,
        ], $invoice->status === DepositInvoice::STATUS_FAILED ? 502 : 200);
    }

    /**
     * Offerte verstuurd → deal naar de stap "Proposal Sent" van zijn pijplijn.
     */
    public function quoteSent(Request $request, OpportunityStage $stage): JsonResponse
    {
        $this->authorizeWebhook($request);

        $data = $request->validate([
            'contact_id' => ['nullable', 'string', 'max:191', 'required_without:opportunity_id'],
            'document_name' => ['nullable', 'string', 'max:191'],
            'opportunity_id' => ['nullable', 'string', 'max:191'],
        ]);

        $opportunityId = $data['opportunity_id'] ?? null;

        if (filled($data['contact_id'] ?? null)) {
            $found = $stage->findForQuote($data['contact_id'], $data['document_name'] ?? null);

            if (! $found['opportunity']) {
                return $this->unmatched('verstuurd', $data, $found['reason']);
            }

            $opportunityId = $found['opportunity']['id'];
        }

        return response()->json([
            'stage' => $stage->moveTo($opportunityId, OpportunityStage::PROPOSAL_SENT),
        ]);
    }

    /** Geen eenduidige deal: niets doen, Pieter verwittigen. */
    private function unmatched(string $event, array $data, ?string $reason, int $status = 200): JsonResponse
    {
        $document = $data['document_name'] ?? '(zonder naam)';

        Log::error("GoHighLevel webhook offerte-{$event}: geen deal", ['reason' => $reason, 'document' => $document, 'contact_id' => $data['contact_id'] ?? null]);

        rescue(fn () => Mail::raw(
            "Offerte \"{$document}\" is {$event}, maar ik kon niet bepalen bij welke deal ze hoort.\n\n"
            ."Reden: {$reason}\n\n"
            .($event === 'getekend'
                ? "Er is GEEN voorschotfactuur gemaakt en de deal is niet verplaatst. Maak de factuur zelf in Billit, of geef de deal dezelfde naam als het document en laat 6.3 opnieuw lopen.\n"
                : "De deal is niet naar Proposal Sent gezet. Doe dat zelf, en geef de deal best dezelfde naam als het document, anders lukt de voorschotfactuur straks ook niet.\n"),
            fn ($message) => $message
                ->to(config('services.billit.notify'))
                ->subject("Offerte {$event}, deal niet gevonden: {$document}"),
        ), report: false);

        return response()->json(['status' => 'unmatched', 'reason' => $reason], $status);
    }

    private function authorizeWebhook(Request $request): void
    {
        $secret = (string) config('services.gohighlevel.webhook_secret');

        abort_if($secret === '', 503, 'Webhook niet ingesteld.');
        abort_unless(hash_equals($secret, (string) $request->header('X-Webhook-Secret')), 401);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'contact_id' => ['nullable', 'string', 'max:191', 'required_without_all:document_id,opportunity_id'],
            'document_name' => ['nullable', 'string', 'max:191'],
            'document_url' => ['nullable', 'string', 'max:2000'],
            'document_id' => ['nullable', 'string', 'max:191'],
            'opportunity_id' => ['nullable', 'string', 'max:191'],
            'project' => ['nullable', 'string', 'max:191'],
            'amount_excl' => ['required_without:contact_id'],
            'contact' => ['required', 'array'],
            'contact.email' => ['nullable', 'email'],
            'contact.company' => ['nullable', 'string', 'max:191'],
            'contact.first_name' => ['nullable', 'string', 'max:191'],
            'contact.last_name' => ['nullable', 'string', 'max:191'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
            'contact.vat_number' => ['nullable', 'string', 'max:50'],
            'contact.street' => ['nullable', 'string', 'max:191'],
            'contact.postal_code' => ['nullable', 'string', 'max:20'],
            'contact.city' => ['nullable', 'string', 'max:191'],
            'contact.country' => ['nullable', 'string', 'max:100'],
        ]);
    }

    /** "5000", "5000.00", "5.000,00" of "€ 5 000" → 5000.0 */
    private function amount(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = preg_replace('/[^\d,.]/', '', (string) $value);

        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return (float) $value;
    }
}
