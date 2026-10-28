<?php

namespace Avocadesign\StatamicTools\Http\Controllers;

use Avocadesign\StatamicTools\Feedback\FeedbackSettings;
use Avocadesign\StatamicTools\Feedback\FeedbackStore;
use Avocadesign\StatamicTools\Feedback\Reviewers;
use Avocadesign\StatamicTools\Feedback\Viewer;
use Avocadesign\StatamicTools\Site\Blocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the feedback widget and the avoca:feedback command talk to. The widget signs people in and reads and writes
 * comments as them. The command sends the developer's FEEDBACK_KEY instead of a cookie, from any machine, so a
 * developer's Claude can read and resolve a staging site's comments. Everything here is a 404 unless feedback is on.
 */
class FeedbackController extends Controller
{
    /** Where on a page a comment is: the element, the spot within it and on the page, and its name in words. */
    private const ANCHOR = [
        'anchor.selector' => ['nullable', 'string', 'max:1000'],
        'anchor.x' => ['nullable', 'numeric', 'between:0,1'],
        'anchor.y' => ['nullable', 'numeric', 'between:0,1'],
        'anchor.page_x' => ['nullable', 'numeric', 'between:0,1'],
        'anchor.page_y' => ['nullable', 'numeric', 'min:0'],
        'anchor.block' => ['nullable', 'string', 'max:100'],
        'anchor.label' => ['nullable', 'string', 'max:300'],
        'anchor.text' => ['nullable', 'string', 'max:300'],
    ];

    public function __construct(private FeedbackStore $store)
    {
    }

    /** The two scripts, from the package: the small loader on every page, and the widget it loads on the first click. */
    public function script(string $file): Response
    {
        abort_unless(in_array($file, ['loader', 'widget'], true), 404);

        return response()->file(self::scriptPath($file), [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public static function scriptPath(string $file): string
    {
        return __DIR__."/../../../resources/feedback/{$file}.js";
    }

    /**
     * Who is viewing, whether a name or a password is still needed, the token for writing, and how many days a comment
     * or reply counts as new. Never cached.
     */
    public function session(Request $request): JsonResponse
    {
        return $this->json([
            'viewer' => Viewer::current($request),
            'needs_password' => Viewer::needsPassword(),
            'needs_email' => Reviewers::listed(),
            'token' => csrf_token(),
            'recent_days' => FeedbackStore::recentDays(),
        ]);
    }

    /**
     * With a list of reviewers, an email on it; without one, any name. The password too, when there is one. A listed
     * reviewer is shown by the list's name for them, never one they type.
     */
    public function signIn(Request $request): JsonResponse
    {
        $listed = Reviewers::listed();
        $input = $request->validate([
            'name' => [$listed ? 'nullable' : 'required', 'string', 'max:80'],
            'email' => [$listed ? 'required' : 'nullable', 'string', 'max:254'],
            'password' => [Viewer::needsPassword() ? 'required' : 'nullable', 'string'],
        ], [
            'name.required' => 'Enter your name.',
            'email.required' => 'Enter your email address.',
            'password.required' => 'Enter the password.',
        ]);

        if (Viewer::needsPassword() && ! Viewer::passwordMatches((string) $input['password'])) {
            return $this->json(['errors' => ['password' => ['That password isn’t right.']]], 422);
        }

        if ($listed) {
            $person = Reviewers::find((string) $input['email']);
            if (! $person) {
                return $this->json(['errors' => ['email' => ['That email isn’t on the list of reviewers. Check it, or ask whoever sent you the link.']]], 422);
            }

            return $this->json(['viewer' => ['name' => $person['name'], 'staff' => false]])
                ->withCookie(Viewer::signIn($person['name'], $person['email']));
        }

        return $this->json(['viewer' => ['name' => trim($input['name']), 'staff' => false]])
            ->withCookie(Viewer::signIn($input['name']));
    }

    public function signOut(): JsonResponse
    {
        return $this->json(['viewer' => null])->withCookie(Viewer::signOut());
    }

    /** The open count for one page, for the tab's badge: only for someone signed in, so it tells nobody else anything. */
    public function count(Request $request): JsonResponse
    {
        $this->authorise($request);

        $open = array_filter($this->store->all($this->path($request->query('url')), FeedbackStore::OPEN), fn ($c) => ($c['context'] ?? 'site') === 'site');

        return $this->json(['open' => count($open)]);
    }

    /**
     * Comments on the site's pages, or on the prototype, each with where it was made in words. The site's are listed
     * for one page or every page; the prototype's for one version or every version, one page or all. Either can be
     * narrowed to one state, and to decisions only.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorise($request);

        $context = in_array($request->query('context'), ['prototype', 'all'], true) ? $request->query('context') : 'site';
        $url = $context === 'site' && $request->query('scope') !== 'all' ? $this->path($request->query('url')) : null;
        $status = in_array($request->query('status'), [FeedbackStore::OPEN, FeedbackStore::RESOLVED], true) ? $request->query('status') : null;
        $version = is_string($request->query('version')) ? $request->query('version') : null;
        $page = is_string($request->query('page')) ? $request->query('page') : null;
        $decisions = $request->boolean('decisions');
        $blocks = Blocks::pageBuilder();

        $comments = array_values(array_filter($this->store->all($url, $status), fn (array $c) => ($context === 'all' || ($c['context'] ?? 'site') === $context)
            && ($version === null || ($c['version'] ?? null) === $version)
            && ($page === null || ($c['page'] ?? null) === $page)
            && (! $decisions || ! empty($c['decision']))));

        $comments = array_map(function (array $comment) use ($blocks) {
            $handle = $comment['anchor']['block'] ?? null;
            $comment['anchor']['block_name'] = is_string($handle) && isset($blocks[$handle]) ? $blocks[$handle]['display'] : null;

            return $comment;
        }, $comments);

        return $this->json(['comments' => $comments]);
    }

    public function store(Request $request): JsonResponse
    {
        $author = $this->authorise($request);
        $input = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
            'entry' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:5000'],
            'anchor' => ['nullable', 'array'],
            ...self::ANCHOR,
            'viewport' => ['nullable', 'array'],
            'viewport.width' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'viewport.height' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'viewport.breakpoint' => ['nullable', 'string', 'max:10'],
            'context' => ['nullable', 'in:site,prototype'],
            'version' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
            'page' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:300'],
            'frame' => ['nullable', 'string', 'max:20', 'regex:/^[a-z][a-z0-9-]*$/'],
            'decision' => ['nullable', 'boolean'],
            'who' => ['nullable', 'string', 'max:120'],
            'options' => ['nullable', 'array', 'max:12'],
            'options.*' => ['string', 'max:100'],
        ], ['body.required' => 'Write a comment first.']);
        // The prototype's page options showing when the comment was made, by label: words only, and short.
        $input['options'] = collect($input['options'] ?? [])
            ->filter(fn ($value, $label) => is_string($label) && mb_strlen($label) <= 100)
            ->all() ?: null;

        // The team can raise a comment as a decision as it posts it; anyone else is turned away before anything is kept.
        abort_if(! empty($input['decision']) && ! $author['staff'], 403, 'Only the team can make a comment a question.');

        $input['url'] = $this->path($input['url']);
        $comment = $this->store->create($input, $author);
        if (! empty($input['decision'])) {
            $comment = $this->store->decide($comment['id'], FeedbackStore::TO_DECIDE, $author, null, $input['who'] ?? null);
        }

        return $this->json(['comment' => $comment], 201);
    }

    /**
     * Raises a comment as a decision to make, records the outcome, or takes the decision off. Only the team can: a
     * control panel login, a reviewer marked team, or the command.
     */
    public function decide(Request $request, string $id): JsonResponse
    {
        $by = $this->authorise($request);
        abort_unless($by['staff'], 403, 'Only the team can raise or answer a question.');
        $input = $request->validate([
            'state' => ['required', 'in:open,decided,none'],
            'outcome' => ['required_if:state,decided', 'nullable', 'string', 'max:5000'],
            'who' => ['nullable', 'string', 'max:120'],
        ], ['outcome.required_if' => 'Write the answer.']);

        return $this->found($this->store->decide($id, $input['state'], $by, $input['outcome'] ?? null, $input['who'] ?? null));
    }

    /**
     * Moves a comment's pin, when it covers something: anyone signed in can, as anyone can resolve. Only the parts of
     * the spot the store keeps are taken.
     */
    public function move(Request $request, string $id): JsonResponse
    {
        $this->authorise($request);
        $input = $request->validate([
            'anchor' => ['required', 'array'],
            ...self::ANCHOR,
            'frame' => ['nullable', 'string', 'max:20', 'regex:/^[a-z][a-z0-9-]*$/'],
        ]);
        $anchor = Arr::only($input['anchor'], array_map(fn ($rule) => substr($rule, 7), array_keys(self::ANCHOR)));

        return $this->found($this->store->move($id, $anchor, $input['frame'] ?? null));
    }

    public function reply(Request $request, string $id): JsonResponse
    {
        $author = $this->authorise($request);
        $input = $request->validate(['body' => ['required', 'string', 'max:5000']], ['body.required' => 'Write a reply first.']);

        return $this->found($this->store->reply($id, $input['body'], $author));
    }

    /**
     * Changes a comment's text: whoever wrote it can, and the team can change the team's. Never a reviewer's words for
     * them, which is why this checks who wrote it rather than only who is asking.
     */
    public function edit(Request $request, string $id): JsonResponse
    {
        $by = $this->authorise($request);
        $input = $request->validate(['body' => ['required', 'string', 'max:5000']], ['body.required' => 'Write a comment first.']);
        $comment = $this->store->find($id);
        abort_unless($comment !== null, 404);
        abort_unless(FeedbackStore::canEdit((array) ($comment['author'] ?? []), $by), 403, 'Only whoever wrote this can edit it.');

        return $this->found($this->store->edit($id, $input['body'], $by));
    }

    /** Changes a reply's text, by its place in the thread, on the same terms as a comment's. */
    public function editReply(Request $request, string $id, string $reply): JsonResponse
    {
        $by = $this->authorise($request);
        $input = $request->validate(['body' => ['required', 'string', 'max:5000']], ['body.required' => 'Write a reply first.']);
        $existing = $this->store->find($id)['replies'][(int) $reply] ?? null;
        abort_unless(is_array($existing), 404);
        abort_unless(FeedbackStore::canEdit((array) ($existing['author'] ?? []), $by), 403, 'Only whoever wrote this can edit it.');

        return $this->found($this->store->editReply($id, (int) $reply, $input['body'], $by));
    }

    public function resolve(Request $request, string $id): JsonResponse
    {
        return $this->found($this->store->resolve($id, $this->authorise($request)));
    }

    public function reopen(Request $request, string $id): JsonResponse
    {
        $this->authorise($request);

        return $this->found($this->store->reopen($id));
    }

    /**
     * Deletes a comment made by mistake, twice or as a test. Only the team can, and never a decision, which is made a
     * comment again first.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $by = $this->authorise($request);
        abort_unless($by['staff'], 403, 'Only the team can delete a comment.');
        $comment = $this->store->find($id);
        abort_unless($comment !== null, 404);
        abort_if(! empty($comment['decision']), 409, 'A question can’t be deleted. Make it a comment first.');
        abort_unless($this->store->delete($id), 500, 'The comment couldn’t be deleted.');

        return $this->json(['deleted' => $id]);
    }

    /**
     * The person a request acts for. On the command's routes, which skip the session token, that is the team, named by
     * the command, when it sends the server's FEEDBACK_KEY; a server with no key turns them all away. Everywhere else
     * it is the signed-in viewer. Anyone else is turned away.
     *
     * @return array{name: string, staff: bool}
     */
    private function authorise(Request $request): array
    {
        if ($request->routeIs('*feedback.api.*')) {
            $key = FeedbackSettings::key();
            abort_if($key === '', 403, 'This server has no FEEDBACK_KEY, so feedback can’t be read from another machine.');
            abort_unless(hash_equals($key, (string) $request->header('X-Feedback-Key')), 403, 'That key isn’t right.');
            $name = trim((string) $request->header('X-Feedback-Name', 'Developer'));

            return ['name' => $name !== '' ? mb_substr($name, 0, 80) : 'Developer', 'staff' => true];
        }

        $viewer = Viewer::current($request);
        abort_unless($viewer !== null, 401, 'Sign in to comment.');

        return $viewer;
    }

    /** A page's path without its host or query, so the same page is the same page on any domain. */
    private function path(mixed $url): string
    {
        $path = parse_url(is_string($url) ? $url : '/', PHP_URL_PATH) ?: '/';

        return '/'.trim($path, '/');
    }

    private function found(?array $comment): JsonResponse
    {
        abort_unless($comment !== null, 404);

        return $this->json(['comment' => $comment]);
    }

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, ['Cache-Control' => 'no-store, private']);
    }
}
