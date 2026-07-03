<?php

namespace App\Livewire\Forms;

use App\Mail\FormSubmissionMail;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Standaard contactformulier (de eerste interactieve sectie van de builder).
 * Slaat de inzending op in `form_submissions`, mailt de eigenaar (faalt netjes
 * als SMTP nog niet geconfigureerd is) en toont een bedankboodschap.
 *
 * Een nieuw formuliertype toevoegen (bv. een offerteaanvraag) = kopieer deze
 * class naar bv. QuoteForm met $type = 'quote' en de extra velden, registreer
 * het label in FormSubmission::TYPE_LABELS, voeg het toe aan de form_type-
 * dropdown (FormFields) én aan de match() in de form-partial.
 */
class ContactForm extends Component
{
    /** Het formuliertype dat in form_submissions.type belandt. */
    protected string $type = 'contact';

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|email|max:190')]
    public string $email = '';

    #[Validate('nullable|string|max:40')]
    public string $phone = '';

    #[Validate('required|string|max:5000')]
    public string $message = '';

    /** Honeypot: bots vullen dit in, mensen zien het niet. */
    public string $website = '';

    public bool $sent = false;

    /** @return array<string, string> Nederlandse validatieberichten. */
    protected function messages(): array
    {
        return [
            'name.required' => 'Vul je naam in.',
            'email.required' => 'Vul je e-mailadres in.',
            'email.email' => 'Vul een geldig e-mailadres in.',
            'message.required' => 'Schrijf even je bericht.',
        ];
    }

    public function submit(): void
    {
        // Stille spam-afhandeling: doe alsof het lukte, sla niets op.
        if ($this->website !== '') {
            $this->sent = true;

            return;
        }

        $data = $this->validate();
        unset($data['website']);

        $submission = FormSubmission::create([
            'type' => $this->type,
            'data' => $data,
            'page_url' => url()->previous(),
            'ip' => request()->ip(),
        ]);

        $this->notifyOwner($submission);

        $this->sent = true;
        $this->reset(['name', 'email', 'phone', 'message']);
    }

    protected function notifyOwner(FormSubmission $submission): void
    {
        // Stuur naar het afzender-adres uit config/mail.php. Wil je een apart
        // notificatie-adres? Zet MAIL_FROM_ADDRESS op de inbox van de klant,
        // of voeg een eigen config-key/Setting toe en lees die hier.
        $to = config('mail.from.address');

        if (blank($to)) {
            return;
        }

        try {
            Mail::to($to)->send(new FormSubmissionMail($submission));
        } catch (\Throwable $e) {
            // SMTP nog niet (juist) ingesteld: de inzending staat al in de DB,
            // dus de eigenaar verliest niets — log enkel en ga verder.
            Log::warning('Form-notificatie e-mail faalde: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.forms.contact-form');
    }
}
