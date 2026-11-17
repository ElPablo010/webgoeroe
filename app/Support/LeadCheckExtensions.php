<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Haakjes rond een ingevulde lekkencheck (singleton, via LeadCheck::hooks()).
 * Zelfde vorm als Core::bookings(). De request bevat `tracking` zoals de browser
 * het meestuurt (window.coreLeadTracking.payload()). Bv.:
 *
 *     LeadCheck::hooks()
 *         ->rules(ConversionsApi::RULES)
 *         ->data(fn (Request $request): array => ['herkomst' => …])
 *         ->submitted(fn (Request $request, Model $submission, array $contact) => …);
 */
class LeadCheckExtensions
{
    /** @var array<string, mixed> */
    protected array $rules = [];

    /** @var array<int, callable(Request): array<string, mixed>> */
    protected array $data = [];

    /** @var array<int, callable(Request, Model, array<string, string>): void> */
    protected array $submitted = [];

    /** Extra validatieregels (bv. trackingvelden, onder `tracking.*`). */
    public function rules(array $rules): static
    {
        $this->rules = [...$this->rules, ...$rules];

        return $this;
    }

    /** Extra velden in de aanvraag; ze staan er vóór de koppeling met GHL loopt. */
    public function data(callable $callback): static
    {
        $this->data[] = $callback;

        return $this;
    }

    /** Na een ingevulde check (de aanvraag staat er). */
    public function submitted(callable $callback): static
    {
        $this->submitted[] = $callback;

        return $this;
    }

    /** @return array<string, mixed> */
    public function validationRules(): array
    {
        return $this->rules;
    }

    /** @return array<string, mixed> */
    public function extraData(Request $request): array
    {
        $data = [];

        foreach ($this->data as $callback) {
            $data = [...$data, ...(array) $callback($request)];
        }

        return $data;
    }

    /** @param  array<string, string>  $contact  first_name, email, phone */
    public function afterSubmit(Request $request, Model $submission, array $contact): void
    {
        foreach ($this->submitted as $callback) {
            try {
                $callback($request, $submission, $contact);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
