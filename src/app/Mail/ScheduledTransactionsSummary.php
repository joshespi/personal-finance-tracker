<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class ScheduledTransactionsSummary extends Mailable
{
    use Queueable;

    public readonly string $intro;

    public function __construct(
        public readonly User       $user,
        public readonly Collection $fired,
    ) {
        $count       = $fired->count();
        $this->intro = $count === 1
            ? 'The following scheduled transaction was automatically recorded today:'
            : "The following {$count} scheduled transactions were automatically recorded today:";
    }

    public function envelope(): Envelope
    {
        $count = $this->fired->count();
        $noun  = $count === 1 ? 'scheduled transaction' : 'scheduled transactions';
        return new Envelope(subject: "{$count} {$noun} recorded");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.scheduled-transactions-summary');
    }
}
