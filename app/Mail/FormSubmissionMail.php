<?php

namespace App\Mail;

use App\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notificatie naar de eigenaar bij een nieuwe formulier-inzending. Het
 * reply-to wordt op het e-mailadres van de inzender gezet (indien aanwezig),
 * zodat je rechtstreeks kan antwoorden.
 */
class FormSubmissionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FormSubmission $submission) {}

    public function envelope(): Envelope
    {
        $replyTo = $this->submission->data['email'] ?? null;

        return new Envelope(
            subject: 'Nieuwe inzending: '.$this->submission->typeLabel(),
            replyTo: filled($replyTo) ? [$replyTo] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.form-submission');
    }
}
