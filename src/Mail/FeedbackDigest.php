<?php

namespace Avocadesign\StatamicTools\Mail;

use Avocadesign\StatamicTools\Feedback\Digest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The team's digest of new comments and replies, by who made them and then by page, each with a link straight to it. */
class FeedbackDigest extends Mailable
{
    use Queueable;

    /** @param  array<int, array{kind: string, comment: array<string, mixed>, reply: ?array<string, mixed>, at: string}>  $items */
    public function __construct(public array $items)
    {
    }

    /** The items by who made them, and each person's by page. */
    public function people(): array
    {
        return Digest::byPerson($this->items);
    }

    public function envelope(): Envelope
    {
        $comments = count(array_filter($this->items, fn ($item) => $item['kind'] === 'comment'));
        $names = array_column($this->people(), 'name');
        $from = count($names) <= 2 ? implode(' and ', $names) : $names[0].' and '.(count($names) - 1).' others';

        return new Envelope(subject: ucfirst(Digest::tally($comments, count($this->items) - $comments, true)).' from '.$from.' on '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'statamic-tools::feedback.digest',
            text: 'statamic-tools::feedback.digest-text',
            with: ['people' => $this->people()],
        );
    }
}
