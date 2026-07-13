<?php

namespace App\Mail;

use App\Enums\EmailSummaryFrequency;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountChangesSummary extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly EmailSummaryFrequency $frequency,
        public readonly array $data,
        public readonly CarbonInterface $since,
        public readonly CarbonInterface $until,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your {$this->frequency->label()} account summary");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.account-changes-summary');
    }
}
