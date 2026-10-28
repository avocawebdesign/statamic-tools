<?php

namespace Avocadesign\StatamicTools\Feedback;

use Illuminate\Support\Carbon;

/**
 * What the team hears about: comments and replies reviewers have made since the last digest, and who to tell. The
 * team's own comments and replies are left out, since they made them. A digest waits until reviewers go quiet, so one
 * review is one email. The time of the last digest is kept in storage, out of git, so nothing is told twice and nothing
 * is missed between runs.
 */
final class Digest
{
    /** @param  string|null  $state  Where the time of the last digest is kept: in storage, out of git, by default. */
    public function __construct(private FeedbackStore $store, private ?string $state = null)
    {
    }

    /**
     * The reviewers marked team: true, and FEEDBACK_NOTIFY's addresses.
     *
     * @return array<int, string>
     */
    public static function recipients(): array
    {
        $team = array_map(fn ($person) => $person['email'], array_filter(Reviewers::all(), fn ($person) => $person['team']));
        $extra = array_map('trim', explode(',', (string) config('statamic-tools.feedback.notify')));
        $emails = array_filter([...$team, ...$extra], fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false);

        return array_values(array_unique(array_map('mb_strtolower', $emails)));
    }

    /**
     * When the last digest went, or, before the first, a day ago, so a site's first comments are told. Anything waiting
     * longer than the hold is due at once, so nothing slides out of that day untold.
     */
    public function since(): Carbon
    {
        $state = is_file($this->statePath()) ? json_decode((string) file_get_contents($this->statePath()), true) : null;
        $sent = is_array($state) && is_string($state['sent_at'] ?? null) ? $state['sent_at'] : null;

        return $sent ? Carbon::parse($sent) : now()->subDay();
    }

    public function markSent(Carbon $until): void
    {
        if (! is_dir(dirname($this->statePath()))) {
            mkdir(dirname($this->statePath()), 0755, true);
        }
        file_put_contents($this->statePath(), json_encode(['sent_at' => $until->toIso8601String()]), LOCK_EX);
    }

    /**
     * New comments and replies from reviewers, after $since and up to $until, oldest first.
     *
     * @return array<int, array{kind: string, comment: array<string, mixed>, reply: ?array<string, mixed>, at: string}>
     */
    public function pending(Carbon $since, Carbon $until): array
    {
        $within = fn ($at) => is_string($at) && Carbon::parse($at)->gt($since) && Carbon::parse($at)->lte($until);
        $reviewer = fn ($author) => ! ($author['staff'] ?? false);

        $items = [];
        foreach ($this->store->all() as $comment) {
            if ($within($comment['created_at'] ?? null) && $reviewer($comment['author'] ?? [])) {
                $items[] = ['kind' => 'comment', 'comment' => $comment, 'reply' => null, 'at' => $comment['created_at']];
            }
            foreach ((array) ($comment['replies'] ?? []) as $reply) {
                if ($within($reply['created_at'] ?? null) && $reviewer($reply['author'] ?? [])) {
                    $items[] = ['kind' => 'reply', 'comment' => $comment, 'reply' => $reply, 'at' => $reply['created_at']];
                }
            }
        }

        usort($items, fn ($a, $b) => strcmp($a['at'], $b['at']));

        return $items;
    }

    /**
     * When what is waiting is due: once reviewers have said nothing for the quiet spell, or once the oldest has waited
     * the hold, whichever comes first. A client commenting for an hour gets one email after they stop; one commenting
     * all afternoon, one every couple of hours.
     *
     * @param  array<int, array{at: string}>  $items  Oldest first, as pending() gives them.
     */
    public static function dueAt(array $items): Carbon
    {
        $quiet = Carbon::parse($items[array_key_last($items)]['at'])->addMinutes(self::quietMinutes());
        $hold = Carbon::parse($items[0]['at'])->addMinutes(self::holdMinutes());

        return $quiet->lt($hold) ? $quiet : $hold;
    }

    /**
     * The items by who made them, in the order each person first appears, and each person's by page, so one
     * reviewer's comments read together. A person is known by name, the list's name for a listed reviewer.
     *
     * @param  array<int, array{kind: string, comment: array<string, mixed>, reply: ?array<string, mixed>, at: string}>  $items
     * @return array<int, array{name: string, comments: int, replies: int, pages: array<int, array{place: string, items: array<int, array<string, mixed>>}>}>
     */
    public static function byPerson(array $items): array
    {
        $people = [];
        foreach ($items as $item) {
            $author = $item['kind'] === 'reply' ? ($item['reply']['author'] ?? []) : ($item['comment']['author'] ?? []);
            $name = trim((string) ($author['name'] ?? '')) ?: 'Someone';
            $who = mb_strtolower($name);
            $place = self::place($item['comment']);
            // Two pages can share a title, so a page is its title and its address.
            $page = $place."\n".($item['comment']['url'] ?? '');
            $people[$who] ??= ['name' => $name, 'comments' => 0, 'replies' => 0, 'pages' => []];
            $people[$who][$item['kind'] === 'reply' ? 'replies' : 'comments']++;
            $people[$who]['pages'][$page] ??= ['place' => $place, 'items' => []];
            $people[$who]['pages'][$page]['items'][] = $item;
        }

        return array_values(array_map(fn ($person) => [...$person, 'pages' => array_values($person['pages'])], $people));
    }

    /** How many comments and replies, in words: "3 comments and 1 reply", or "3 new comments and 1 reply". */
    public static function tally(int $comments, int $replies, bool $new = false): string
    {
        return implode(' and ', array_filter([
            $comments ? $comments.($new ? ' new ' : ' ').($comments === 1 ? 'comment' : 'comments') : null,
            $replies ? $replies.' '.($replies === 1 ? 'reply' : 'replies') : null,
        ]));
    }

    /** Where a comment was made, in words: the page's title, or the prototype's version and page. */
    public static function place(array $comment): string
    {
        if (($comment['context'] ?? 'site') === 'prototype') {
            return 'Prototype, version '.($comment['version'] ?? '').': '.(($comment['title'] ?? null) ?: ($comment['route'] ?? ''));
        }

        return ($comment['title'] ?? null) ?: ($comment['url'] ?? '/');
    }

    /** The address that opens a comment: in the prototype's version, or on the site's page. */
    public static function link(array $comment): string
    {
        if (($comment['context'] ?? 'site') === 'prototype') {
            return url('/prototype/'.rawurlencode((string) ($comment['version'] ?? ''))).'?comment='.$comment['id'];
        }

        return url((string) ($comment['url'] ?? '/')).'?feedback='.$comment['id'];
    }

    public static function quietMinutes(): int
    {
        return max(1, (int) config('statamic-tools.feedback.digest_quiet_minutes', 30));
    }

    public static function holdMinutes(): int
    {
        return max(1, (int) config('statamic-tools.feedback.digest_hold_minutes', 120));
    }

    // Kept apart from the comments, which are in git, so a digest never makes a commit of its own.
    private function statePath(): string
    {
        return $this->state ?? storage_path('app/feedback-digest.json');
    }
}
