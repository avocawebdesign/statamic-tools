<?php

namespace Avocadesign\StatamicTools\Feedback;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Statamic\Facades\YAML;

/**
 * Comments pinned to the site's pages, one YAML file each in content/feedback, holding the comment, where on the page
 * it was made, its replies and whether it is resolved. No database and no outside service. They are in git with the
 * rest of the content: made locally, they go up with a push; made on a server whose content is edited there, the
 * server's git script commits them and they come down with a pull. One file per comment, named by its ID, so comments
 * made in two places never clash. Every change fires FeedbackChanged, which Statamic's git integration commits on a
 * server with automatic commits.
 */
final class FeedbackStore
{
    public const OPEN = 'open';

    public const RESOLVED = 'resolved';

    /** A decision still to make, and one made. */
    public const TO_DECIDE = 'open';

    public const DECIDED = 'decided';

    public function __construct(private string $directory)
    {
    }

    public static function make(): self
    {
        $path = (string) config('statamic-tools.feedback.path', 'content/feedback');
        $store = new self(str_starts_with($path, '/') ? $path : base_path($path));
        // Comments made before they moved into git, under storage/app/feedback, move across the first time they're read.
        $store->adopt(storage_path('app/feedback'));

        return $store;
    }

    /** Moves the comments in an earlier folder into this one, leaving any this one already has. */
    public function adopt(string $from): int
    {
        if (rtrim($from, '/') === rtrim($this->directory, '/') || ! is_dir($from)) {
            return 0;
        }

        $moved = 0;
        foreach (glob($from.'/*.yaml') ?: [] as $file) {
            $id = basename($file, '.yaml');
            if (! $this->valid($id) || is_file($this->path($id))) {
                continue;
            }
            if (! is_dir($this->directory)) {
                mkdir($this->directory, 0755, true);
            }
            if (@rename($file, $this->path($id))) {
                $moved++;
            }
        }

        return $moved;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Every comment, newest first, optionally only those made on one page and only those in one state.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $url = null, ?string $status = null): array
    {
        $comments = [];
        foreach (glob($this->directory.'/*.yaml') ?: [] as $file) {
            $comment = $this->read($file);
            if ($comment === null) {
                continue;
            }
            if ($url !== null && ($comment['url'] ?? null) !== $url) {
                continue;
            }
            if ($status !== null && ($comment['status'] ?? null) !== $status) {
                continue;
            }
            $comments[] = $comment;
        }

        usort($comments, fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return $comments;
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->valid($id) ? $this->read($this->path($id)) : null;
    }

    /**
     * Records a new comment and returns it. Where it was made is the page and, on it, the element and the spot within
     * it, as the widget measured them. A comment on the prototype also records the version, the page's route key and
     * route, and the frame it was made in.
     *
     * @param  array<string, mixed>  $comment  url, entry, title, body, anchor, viewport; for the prototype, version, page, route, frame, options
     * @param  array{name: string, staff: bool}  $author
     * @return array<string, mixed>
     */
    public function create(array $comment, array $author): array
    {
        $prototype = ($comment['context'] ?? 'site') === 'prototype';
        $record = [
            'id' => (string) Str::ulid(),
            'context' => $prototype ? 'prototype' : 'site',
            'version' => $prototype ? (string) ($comment['version'] ?? '') : null,
            'page' => $prototype ? (string) ($comment['page'] ?? '') : null,
            'route' => $prototype ? (string) ($comment['route'] ?? '') : null,
            'frame' => $prototype ? (string) ($comment['frame'] ?? '') : null,
            'options' => $prototype && ! empty($comment['options']) ? (array) $comment['options'] : null,
            'url' => (string) $comment['url'],
            'entry' => $comment['entry'] ?? null,
            'title' => $comment['title'] ?? null,
            'body' => (string) $comment['body'],
            'anchor' => $comment['anchor'] ?? [],
            'viewport' => $comment['viewport'] ?? [],
            'author' => $author,
            'created_at' => now()->toIso8601String(),
            'status' => self::OPEN,
            'resolved_by' => null,
            'resolved_at' => null,
            'replies' => [],
            'decision' => null,
        ];

        $this->write($record);
        FeedbackChanged::dispatch($record, 'comment');

        return $record;
    }

    /** @param  array{name: string, staff: bool}  $author */
    public function reply(string $id, string $body, array $author): ?array
    {
        return $this->change($id, function (array $comment) use ($body, $author) {
            $comment['replies'][] = ['author' => $author, 'body' => $body, 'created_at' => now()->toIso8601String()];

            return $comment;
        }, 'reply');
    }

    /**
     * Changes a comment's text. It records who changed it and when, so anyone who has already replied can see the text
     * moved. The earlier text isn't kept here: git has it once the file has been committed. Who may is canEdit's to
     * say and the controller's to check.
     *
     * @param  array{name: string, staff: bool}  $by
     */
    public function edit(string $id, string $body, array $by): ?array
    {
        return $this->change($id, fn (array $comment) => [...$comment, 'body' => $body, 'edited_by' => $by, 'edited_at' => now()->toIso8601String()], 'edited');
    }

    /**
     * Changes one reply's text, found by its place in the thread, and records who changed it and when, as for a
     * comment. Null when the comment or the reply isn't there.
     *
     * @param  array{name: string, staff: bool}  $by
     */
    public function editReply(string $id, int $reply, string $body, array $by): ?array
    {
        $comment = $this->find($id);
        if ($comment === null || ! is_array($comment['replies'][$reply] ?? null)) {
            return null;
        }

        return $this->change($id, function (array $comment) use ($reply, $body, $by) {
            $comment['replies'][$reply] = [...$comment['replies'][$reply], 'body' => $body, 'edited_by' => $by, 'edited_at' => now()->toIso8601String()];

            return $comment;
        }, 'reply edited');
    }

    /**
     * Whether someone may change what an author wrote. Anyone may change their own words, matched by name because a
     * comment keeps its author's name and not their email, and the team may also change anything the team wrote. Only
     * a reviewer changes a reviewer's words. Without a list of reviewers a name is whatever someone typed to sign in,
     * so this is only as strong as the sign-in, as resolving is.
     *
     * @param  array<string, mixed>  $author
     * @param  array{name: string, staff: bool}  $by
     */
    public static function canEdit(array $author, array $by): bool
    {
        $staff = (bool) ($author['staff'] ?? false);
        if ($staff !== (bool) ($by['staff'] ?? false)) {
            return false;
        }
        $name = mb_strtolower(trim((string) ($author['name'] ?? '')));

        return $staff || ($name !== '' && $name === mb_strtolower(trim((string) ($by['name'] ?? ''))));
    }

    /**
     * What is new on a comment, as the team sees it: whether reviewers made it in the last recent_days, and which of its
     * replies, by their place in the thread, they added in that time. The team's own words are never new to the team.
     * The panel works out the same in the browser for whoever is viewing, so a reviewer sees the team's replies as new.
     *
     * @param  array<string, mixed>  $comment
     * @return array{comment: bool, replies: array<int, int>}
     */
    public static function recent(array $comment): array
    {
        $since = now()->subDays(self::recentDays());
        $fresh = fn (mixed $item) => is_array($item) && ! ($item['author']['staff'] ?? false)
            && is_string($item['created_at'] ?? null) && Carbon::parse($item['created_at'])->gt($since);

        return [
            'comment' => $fresh($comment),
            'replies' => array_keys(array_filter(array_values((array) ($comment['replies'] ?? [])), $fresh)),
        ];
    }

    public static function recentDays(): int
    {
        return max(1, (int) config('statamic-tools.feedback.recent_days', 7));
    }

    /** @param  array{name: string, staff: bool}  $by */
    public function resolve(string $id, array $by): ?array
    {
        return $this->change($id, fn (array $comment) => [
            ...$comment,
            'status' => self::RESOLVED,
            'resolved_by' => $by,
            'resolved_at' => now()->toIso8601String(),
        ], 'resolved');
    }

    /**
     * Raises a comment as a decision to make, records the decision made, or takes the decision off again. The team
     * does this, never the reviewers: who may is the controller's to check.
     *
     * @param  array{name: string, staff: bool}  $by
     */
    public function decide(string $id, string $state, array $by, ?string $outcome = null, ?string $who = null): ?array
    {
        return $this->change($id, function (array $comment) use ($state, $by, $outcome, $who) {
            if ($state === 'none') {
                return [...$comment, 'decision' => null];
            }

            $decision = (array) ($comment['decision'] ?? []);
            $decision['raised_by'] ??= $by;
            $decision['raised_at'] ??= now()->toIso8601String();
            if ($who !== null) {
                $decision['who'] = $who;
            }
            $decision['state'] = $state === self::DECIDED ? self::DECIDED : self::TO_DECIDE;
            if ($decision['state'] === self::DECIDED) {
                $decision['outcome'] = (string) $outcome;
                $decision['decided_by'] = $by;
                $decision['decided_at'] = now()->toIso8601String();
            } else {
                $decision['outcome'] = null;
                $decision['decided_by'] = null;
                $decision['decided_at'] = null;
            }

            return [...$comment, 'decision' => $decision];
        }, match ($state) {
            'none' => 'decision taken off',
            self::DECIDED => 'decision recorded',
            default => 'decision to make',
        });
    }

    /**
     * Moves a comment's pin: a new element and spot within it, measured as when the comment was made. On the prototype
     * it also records the frame it was moved in, where the spot on the page means the same thing.
     *
     * @param  array<string, mixed>  $anchor
     */
    public function move(string $id, array $anchor, ?string $frame = null): ?array
    {
        return $this->change($id, function (array $comment) use ($anchor, $frame) {
            $comment['anchor'] = $anchor;
            if ($frame !== null && ($comment['context'] ?? 'site') === 'prototype') {
                $comment['frame'] = $frame;
            }

            return $comment;
        }, 'pin moved');
    }

    /**
     * Deletes a comment and its replies, for one made by mistake, twice or as a test. A decision is never deleted: it's
     * the record of what was agreed, so it is made a comment again first. Who may delete is the controller's to check.
     * The file is in git, so a deleted comment can be found in the history once it has been committed.
     */
    public function delete(string $id): bool
    {
        $comment = $this->find($id);
        if ($comment === null || ! empty($comment['decision'])) {
            return false;
        }

        if (! @unlink($this->path($id))) {
            return false;
        }
        FeedbackChanged::dispatch($comment, 'deleted');

        return true;
    }

    public function reopen(string $id): ?array
    {
        return $this->change($id, fn (array $comment) => [...$comment, 'status' => self::OPEN, 'resolved_by' => null, 'resolved_at' => null], 'reopened');
    }

    /**
     * Changes a comment's file, then says what changed, in a few words, for the commit.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    private function change(string $id, callable $change, string $what): ?array
    {
        $comment = $this->find($id);
        if ($comment === null) {
            return null;
        }

        $comment = $change($comment);
        $this->write($comment);
        FeedbackChanged::dispatch($comment, $what);

        return $comment;
    }

    /** IDs are ULIDs: anything else never reaches the file system. */
    private function valid(string $id): bool
    {
        return (bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id);
    }

    private function path(string $id): string
    {
        return $this->directory.'/'.$id.'.yaml';
    }

    /** @return array<string, mixed>|null */
    private function read(string $file): ?array
    {
        if (! is_file($file)) {
            return null;
        }
        $data = YAML::parse((string) file_get_contents($file));

        return is_array($data) && isset($data['id']) ? $data : null;
    }

    /** @param  array<string, mixed>  $comment */
    private function write(array $comment): void
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }

        file_put_contents($this->path($comment['id']), YAML::dump($comment), LOCK_EX);
    }
}
