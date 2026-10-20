<?php

namespace App\Http\Controllers;

use App\Models\DepositInvoice;
use App\Services\Billit\DepositInvoiceService;
use App\Services\GoHighLevel\OpportunityStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Webhooks uit GoHighLevel-workflows ("Custom Webhook"-actie). Beveiligd met
 * een gedeeld geheim in de header X-Webhook-Secret.
 */
class GoHighLevelWebhookController extends Controller
{
    /**
     * Offerte getekend → voorschotfactuur in Billit. De body stellen we zelf
     * samen in de workflow (zie wiki WebGoeroe "offerte-flow").
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

        $amount = $this->amount($data['amount_excl']);

        if ($amount <= 0) {
            return response()->json(['message' => 'Geen geldige dealwaarde (amount_excl).'], 422);
        }

        $invoice = $service->handle([
            ...$data,
            'source_id' => $data['document_id'] ?? 'opportunity:'.$data['opportunity_id'],
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
            'opportunity_id' => ['required', 'string', 'max:191'],
        ]);

        return response()->json([
            'stage' => $stage->moveTo($data['opportunity_id'], OpportunityStage::PROPOSAL_SENT),
        ]);
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
            'document_id' => ['nullable', 'string', 'max:191', 'required_without:opportunity_id'],
            'opportunity_id' => ['nullable', 'string', 'max:191'],
            'project' => ['nullable', 'string', 'max:191'],
            'amount_excl' => ['required'],
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
