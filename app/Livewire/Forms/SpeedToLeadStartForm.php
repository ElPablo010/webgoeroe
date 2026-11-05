<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Component;
use Webgoeroe\Core\Core;
use Webgoeroe\Core\Livewire\Concerns\GuardsAgainstSpam;

/**
 * Startformulier van de landingspagina "Speed to lead elektriciens": naam,
 * bedrijf, e-mail en gsm. Geen betaal- of facturatiegegevens: Pieter belt om
 * op te zetten, de betaallink volgt bij livegang. Elke inzending staat op de
 * site (telt als lead) en gaat via de koppeling van de core (Instellingen →
 * Integraties) als contact met tag naar GoHighLevel. Lukt dat niet, dan mailt
 * de core zelf de eigenaar.
 */
class SpeedToLeadStartForm extends Component
{
    use GuardsAgainstSpam;

    public const TYPE = 'speed_to_lead_start';

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|string|max:120')]
    public string $company = '';

    #[Validate('required|email|max:190')]
    public string $email = '';

    #[Validate('required|string|max:40')]
    public string $phone = '';

    public bool $sent = false;

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'name.required' => 'Vul je naam in.',
            'company.required' => 'Vul je bedrijfsnaam in.',
            'email.required' => 'Vul je e-mailadres in.',
            'email.email' => 'Vul een geldig e-mailadres in.',
            'phone.required' => 'Vul je gsm-nummer in, zodat we je kunnen bellen.',
        ];
    }

    public function submit(): void
    {
        // Stille spam-afhandeling: doe alsof het lukte, sla niets op.
        if ($this->isSpam($this->name, $this->email)) {
            $this->sent = true;

            return;
        }

        $data = $this->validate();

        if ($this->tooManySubmissions()) {
            $this->addError('email', 'Je hebt je al meerdere keren aangemeld. Probeer het later opnieuw.');

            return;
        }

        Core::model('form_submission')::create([
            'type' => self::TYPE,
            'data' => $data,
            'page_url' => url()->previous(),
            'ip' => request()->ip(),
        ]);

        $this->sent = true;
        $this->reset(['name', 'company', 'email', 'phone']);
    }

    public function render()
    {
        return view('livewire.forms.speed-to-lead-start-form');
    }
}
