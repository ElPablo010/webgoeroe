<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SeoWeeklyReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $context,
        public ?string $advice,
        public string $dashboardUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        $target = $this->context['target'] ?? 'website';

        return new Envelope(
            subject: "SEO stand van zaken — {$target}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.seo.weekly-report',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
