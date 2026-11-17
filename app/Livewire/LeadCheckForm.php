<?php

namespace App\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Webgoeroe\Core\Core;
use Webgoeroe\Core\Integrations\GoHighLevel\SubmissionSync;
use Webgoeroe\Core\Livewire\Concerns\GuardsAgainstSpam;
use Webgoeroe\Core\Livewire\Concerns\PersistsLocale;
use Webgoeroe\Core\Livewire\Concerns\RemembersSection;
use Webgoeroe\Core\Livewire\Forms\ContactForm;
use App\Support\LeadCheck;

/**
 * Het blok "Lekkencheck" (Livewire-component `lead-check`, tot core 0.25 `core.lead-check` in de core). De vragen
 * lopen in de browser (Alpine); bij "Toon mijn resultaat" komen de antwoorden
 * en de gegevens hier binnen. De server rekent zelf (Support\LeadCheck, met de
 * inhoud van het blok uit de database), bewaart de aanvraag (type `lead_check`,
 * met het blok voor de GHL-instellingen) en toont het resultaat.
 */
class LeadCheckForm extends Component
{
    use GuardsAgainstSpam;
    use PersistsLocale;
    use RemembersSection;

    public const TYPE = 'lead_check';

    public string $first_name = '';

    public string $email = '';

    public string $phone = '';

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    /** @return array<string, mixed> */
    #[Computed]
    public function content(): array
    {
        $section = $this->sectionId ? Core::model('page_section')::query()->whereKey($this->sectionId)->where('section_type', 'lead_check')->first() : null;

        return (array) ($section?->content ?? []);
    }

    /** De vragen zoals de browser ze nodig heeft (zonder de rekenwaarden). */
    #[Computed]
    public function publicQuestions(): array
    {
        return array_map(fn (array $q): array => [
            'key' => $q['key'],
            'question' => $q['question'],
            'info' => $q['info'],
            'input' => $q['input'],
            'prefix' => $q['role'] === 'value' ? '€' : null,
            'suffix' => $q['role'] === 'close_rate' ? '%' : null,
            'min' => $q['min'],
            'max' => $q['max'],
            'options' => array_column($q['options'], 'label'),
        ], LeadCheck::questions($this->content));
    }

    /**
     * @param  array<string, mixed>  $answers  vraagsleutel → optie-index of getal
     * @param  array<string, mixed>|null  $tracking  window.coreLeadTracking.payload()
     * @param  array<string, mixed>  $contact  first_name, email, phone, website (honeypot) uit de browser
     */
    public function submit(array $answers = [], ?array $tracking = null, array $contact = []): bool
    {
        // De velden komen mee als argument (Alpine) en niet via wire:model: dat
        // werkt niet op een site die Alpine zelf ook laadt.
        foreach (['first_name', 'email', 'phone', 'website'] as $field) {
            if (array_key_exists($field, $contact)) {
                $this->{$field} = mb_substr(trim((string) $contact[$field]), 0, 200);
            }
        }

        if ($this->isSpam($this->first_name, $this->email)) {
            $this->result = null;

            return false;
        }

        $content = $this->content;
        $questions = LeadCheck::questions($content);

        $this->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => [! empty($content['phone_required']) ? 'required' : 'nullable', 'string', 'max:40'],
        ], [
            'first_name.required' => __('Vul je voornaam in.'),
            'email.required' => __('Vul je e-mailadres in.'),
            'email.email' => __('Vul een geldig e-mailadres in.'),
            'phone.required' => __('Vul je gsm-nummer in.'),
        ]);

        $missing = collect($questions)->first(fn (array $q): bool => ! isset($answers[$q['key']]) || $answers[$q['key']] === '' || $answers[$q['key']] === null);

        if ($questions === [] || $missing !== null) {
            $this->addError('answers', __('Beantwoord eerst alle vragen.'));

            return false;
        }

        $request = request()->duplicate(request: ['tracking' => $tracking ?? []]);
        $extensions = LeadCheck::hooks();

        if ($extensions->validationRules() !== [] && Validator::make(['tracking' => $tracking ?? []], $extensions->validationRules())->fails()) {
            $request = request()->duplicate(request: ['tracking' => []]);
        }

        if ($this->tooManySubmissions()) {
            $this->addError('email', __('Je hebt de check al meerdere keren ingevuld. Probeer het later opnieuw.'));

            return false;
        }

        $result = LeadCheck::calculate($content, $answers);
        $leaks = collect($result['leaks'])->filter(fn (array $leak): bool => $leak['status'] !== 'green');

        $contact = array_filter([
            'first_name' => trim($this->first_name),
            'email' => trim($this->email),
            'phone' => trim($this->phone),
        ], 'filled');

        $data = [
            ...$contact,
            'name' => $contact['first_name'],
            ...$result['answers'],
            'result_year' => LeadCheck::money($result['yearly']['low']).' – '.LeadCheck::money($result['yearly']['high']),
            'result_month' => LeadCheck::money($result['monthly']['low']).' – '.LeadCheck::money($result['monthly']['high']),
            'lost_clients' => LeadCheck::range($result['clients']['low'], $result['clients']['high']),
            'top_leak' => $result['top_leak'],
            'top_leak_advice' => collect($result['leaks'])->firstWhere('label', $result['top_leak'])['advice'] ?? null,
            'leaks' => $leaks->pluck('label')->implode(', ') ?: null,
            'score' => $result['score']['ok'].'/'.$result['score']['total'],
            'leak_tags' => $leaks->pluck('tag')->filter()->values()->all() ?: null,
            ...$extensions->extraData($request),
        ];

        if ($result['mode'] === 'score') {
            unset($data['result_year'], $data['result_month'], $data['lost_clients']);
        }

        /** @var Model $submission */
        $submission = Core::model('form_submission')::create([
            'type' => self::TYPE,
            'data' => array_filter($data, fn ($value): bool => $value !== null && $value !== '' && $value !== []),
            'page_url' => url()->previous(),
            'ip' => request()->ip(),
            ...$this->sectionAttributes(),
        ]);

        // Gaat het type naar GHL, dan meldt de koppeling een fout zelf aan de eigenaar.
        if (! SubmissionSync::shouldSync($submission)) {
            ContactForm::notifyOwner($submission);
        }

        $extensions->afterSubmit($request, $submission, $contact);

        $this->result = $result;
        $this->dispatch('booking-prefill', first_name: $contact['first_name'], email: $contact['email'], phone: $contact['phone'] ?? null);
        $this->dispatch('lead-check-completed', section: $this->sectionId);

        return true;
    }

    /** Een resultaattekst met de placeholders ingevuld. */
    public function text(?string $text): string
    {
        if (blank($text) || $this->result === null) {
            return '';
        }

        return strtr($text, LeadCheck::placeholders($this->result, trim($this->first_name)));
    }

    public function render()
    {
        return view('livewire.lead-check', ['content' => $this->content, 'questions' => $this->publicQuestions]);
    }
}
