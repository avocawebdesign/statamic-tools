<?php

namespace Avocadesign\StatamicTools\Console;

use Avocadesign\StatamicTools\Feedback\Digest;
use Avocadesign\StatamicTools\Feedback\FeedbackSettings;
use Avocadesign\StatamicTools\Feedback\FeedbackStore;
use Avocadesign\StatamicTools\Mail\FeedbackDigest;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the team one digest of the comments and replies reviewers made since the last one. Laravel's scheduler runs it
 * every five minutes while feedback is on, and it waits until reviewers have gone quiet, or the oldest comment has
 * waited long enough, so a client who spends an hour leaving twenty comments sends one email, not twenty. If the email
 * can't be sent, the next run tries again with everything since.
 */
class FeedbackNotify extends Command
{
    protected $signature = 'avoca:feedback:notify
        {--now : Send what is waiting without waiting for reviewers to go quiet}
        {--dry-run : Say who would be told what, and when, and send nothing}';

    protected $description = 'Email the team a digest of new feedback since the last one.';

    public function handle(FeedbackStore $store): int
    {
        if (! FeedbackSettings::active()) {
            $this->line('Feedback is off on this site, so there is nothing to tell.');

            return self::SUCCESS;
        }

        $to = Digest::recipients();
        if ($to === []) {
            $this->warn('Nobody to tell: mark the team in resources/site/reviewers.yaml with team: true, or set FEEDBACK_NOTIFY.');

            return self::SUCCESS;
        }

        $digest = app(Digest::class);
        // Comments keep their time to the second, so a digest covers whole seconds: one made during this second waits
        // for the next digest rather than slipping between the two.
        $until = now()->subSecond()->startOfSecond();
        $items = $digest->pending($digest->since(), $until);
        if ($items === []) {
            $this->line('Nothing new since the last digest.');

            return self::SUCCESS;
        }

        $summary = count($items).' new '.(count($items) === 1 ? 'item' : 'items').' for '.implode(', ', $to);
        $due = Digest::dueAt($items);
        $waiting = ! $this->option('now') && $due->isFuture();
        // Relative, since the app's timezone is often UTC and nobody reading this is.
        $when = 'in '.$due->diffForHumans(['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2, 'minimumUnit' => 'minute']);
        if ($this->option('dry-run')) {
            $this->line("Would send {$summary}".($waiting ? ", {$when} if nothing more comes in" : ' now').':');
            foreach (Digest::byPerson($items) as $person) {
                $this->line("  {$person['name']}: ".Digest::tally($person['comments'], $person['replies']));
                foreach ($person['pages'] as $page) {
                    foreach ($page['items'] as $item) {
                        $this->line("    {$item['kind']}: ".Digest::link($item['comment']));
                    }
                }
            }

            return self::SUCCESS;
        }

        if ($waiting) {
            $this->line("Waiting for reviewers to go quiet: {$summary}, {$when} if nothing more comes in.");

            return self::SUCCESS;
        }

        try {
            Mail::to($to)->send(new FeedbackDigest($items));
        } catch (Throwable $e) {
            $this->error('The digest could not be sent, so the next run tries again: '.$e->getMessage());

            return self::FAILURE;
        }

        $digest->markSent($until);
        $this->info("Sent {$summary}.");

        return self::SUCCESS;
    }
}
