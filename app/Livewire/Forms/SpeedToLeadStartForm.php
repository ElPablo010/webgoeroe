<?php

namespace App\Livewire\Forms;

use App\Services\GoHighLevel\SpeedToLeadContact;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Webgoeroe\Core\Core;
use Webgoeroe\Core\Livewire\Concerns\GuardsAgainstSpam;
use Webgoeroe\Core\Livewire\Forms\ContactForm;

/**
 * Startformulier van de landingspagina "Speed to lead elektriciens": naam,
 * bedrijf, e-mail en gsm. Geen betaal- of facturatiegegevens: Pieter belt om
 * op te zetten, de betaallink volgt bij livegang. Elke inzending staat op de
 * site (telt als lead) en gaat als contact met tag naar GoHighLevel.
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

    public function submit(SpeedToLeadContact $ghl): void
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

        $submission = Core::model('form_submission')::create([
            'type' => self::TYPE,
            'data' => $data,
            'page_url' => url()->previous(),
            'ip' => request()->ip(),
        ]);

        // De melding naar Pieter komt uit GoHighLevel (workflow 1.3). Enkel als het
        // contact daar niet aankomt, mailt de site zelf, zodat geen aanvraag verloren gaat.
        if (! $ghl->send($data)) {
            ContactForm::notifyOwner($submission);
        }

        $this->sent = true;
        $this->reset(['name', 'company', 'email', 'phone']);
    }

    public function render()
    {
        return view('livewire.forms.speed-to-lead-start-form');
    }
}
