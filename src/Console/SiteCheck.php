<?php

namespace Avocadesign\StatamicTools\Console;

use Avocadesign\StatamicTools\Feedback\FeedbackSettings;
use Avocadesign\StatamicTools\Library\Names;
use Avocadesign\StatamicTools\Permissions\EditorAccess;
use Avocadesign\StatamicTools\Permissions\PermissionSets;
use Avocadesign\StatamicTools\Site\AgencyLogo;
use Avocadesign\StatamicTools\Site\Blocks;
use Avocadesign\StatamicTools\Site\Catalogue;
use Avocadesign\StatamicTools\Site\Facts;
use Avocadesign\StatamicTools\Site\Launch;
use Avocadesign\StatamicTools\Site\Llms;
use Avocadesign\StatamicTools\Site\CriticalCss;
use Avocadesign\StatamicTools\Site\Scripts;
use Avocadesign\StatamicTools\Site\Docs;
use Illuminate\Console\Command;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Illuminate\Http\Request;

/**
 * Proves the /site pages and the site's AI block catalogue still reflect the fieldsets: every block and set
 * renders, and with --strict every one has guidance and the committed catalogue is current. Meant for CI. It also
 * warns, and never fails, when the editor role is missing permissions for a structure that isn't opted out, when a
 * library item the site hasn't installed uses a handle the site already has, or when the content still has a claim
 * marked for proof or for the client's approval.
 */
class SiteCheck extends Command
{
    protected $signature = 'avoca:site:check
        {--strict : Fail on missing guidance or an out-of-date AI block catalogue}
        {--stubs : Write a guidance stub for every block or set that has none}';

    protected $description = 'Check the /site pages, guidance files and AI block catalogue against the fieldsets, and the editor role against the structures.';

    public function handle(): int
    {
        $prefix = config('statamic-tools.site.prefix', 'site');
        $failures = 0;
        $warnings = 0;

        $blocks = Blocks::pageBuilder();
        $sets = Blocks::article();

        foreach ([['blocks', $blocks], ['sets', $sets]] as [$kind, $items]) {
            foreach ($items as $handle => $set) {
                if (($set['partial']['origin'] ?? null) === null) {
                    $this->line("  <fg=red>✗</> {$kind}/{$handle}: no partial renders it");
                    $failures++;
                }
                $docs = Docs::for($kind, $handle);
                if (! $docs['exists']) {
                    if ($this->option('stubs')) {
                        $path = Docs::path($kind, $handle);
                        if (! is_dir(dirname($path))) {
                            mkdir(dirname($path), 0755, true);
                        }
                        file_put_contents($path, Docs::stub($kind, $handle, $set['display'], $set['instructions']));
                        $this->line("  <fg=yellow>+</> wrote {$docs['path']}");
                    } elseif ($this->option('strict')) {
                        $this->line("  <fg=red>✗</> {$kind}/{$handle}: no guidance at {$docs['path']}");
                        $failures++;
                    } else {
                        $this->line("  <fg=yellow>!</> {$kind}/{$handle}: no guidance at {$docs['path']}");
                        $warnings++;
                    }
                }
            }
        }

        // Site-wide design guidance: the rules every block shares, such as colour schemes.
        $design = Docs::design();
        if (! $design['exists']) {
            if ($this->option('stubs')) {
                if (! is_dir(dirname(Docs::designPath()))) {
                    mkdir(dirname(Docs::designPath()), 0755, true);
                }
                file_put_contents(Docs::designPath(), Docs::designStub());
                $this->line("  <fg=yellow>+</> wrote {$design['path']}");
            } elseif ($this->option('strict')) {
                $this->line("  <fg=red>✗</> no design guidance at {$design['path']}");
                $failures++;
            } else {
                $this->line("  <fg=yellow>!</> no design guidance at {$design['path']}");
                $warnings++;
            }
        }

        // Orphaned guidance: a file for something that no longer exists.
        foreach ([['blocks', $blocks], ['sets', $sets]] as [$kind, $items]) {
            foreach (glob(dirname(Docs::path($kind, 'x')).'/*.md') ?: [] as $file) {
                $handle = basename($file, '.md');
                if (! isset($items[$handle])) {
                    $this->line("  <fg=red>✗</> {$kind}/{$handle}.md describes a {$kind} that no longer exists");
                    $failures++;
                }
            }
        }

        // A library item the site hasn't installed that uses a handle the site already has would collide when installed.
        // Always a warning: the site works as it is.
        foreach (Names::make()->siteClashes() as $clash) {
            $this->line('  <fg=yellow>!</> '.Names::describeSiteClash($clash));
            $warnings++;
        }

        // llms.txt tells a language model what the site is. The kit lists the site's pages by itself and leaves the
        // rest to the developer, so a routed collection added later goes unmentioned. Always a warning: the site works.
        $llms = GlobalSet::findByHandle((string) config('statamic-tools.site.llms_global', 'bots'))
            ?->inDefaultSite()?->get((string) config('statamic-tools.site.llms_field', 'llms_content'));
        $routed = Collection::all()
            ->filter(fn ($collection) => $collection->routes()->filter()->isNotEmpty())
            ->map(fn ($collection) => $collection->handle())
            ->values()->all();
        foreach (Llms::problems(is_string($llms) ? $llms : null, $routed) as $problem) {
            $this->line('  <fg=yellow>!</> '.$problem);
            $warnings++;
        }

        // A claim nobody has settled yet: recipe/facts.md marks a missing fact or an unapproved outcome rather than
        // inventing one, and neither should go live. Always a warning: markers are expected while a site is written.
        foreach (Facts::check() as $problem) {
            $this->line('  <fg=yellow>!</> '.$problem);
            $warnings++;
        }

        // The kit shows the agency's logo in form emails and the site header until the client's arrives, and a site must
        // not reach client sign-off like that. Always a warning: it is expected while a site is being built.
        foreach (AgencyLogo::check() as $problem) {
            $this->line('  <fg=yellow>!</> '.$problem);
            $warnings++;
        }

        // A launched site holds the add-on to the release line it launched on, so a new line is a decision. Always a
        // warning: the site works either way.
        foreach (Launch::check() as $problem) {
            $this->line('  <fg=yellow>!</> '.$problem);
            $warnings++;
        }

        // Feedback is for a review on local or staging. Without a password it stays off anywhere but a local machine,
        // and left on in production it puts a widget on every page.
        if (FeedbackSettings::heldForPassword()) {
            $this->line('  <fg=yellow>!</> FEEDBACK_ENABLED is on but there is no PROTOTYPE_PASSWORD, so feedback stays off here: set one to review');
            $warnings++;
        }
        if (FeedbackSettings::active() && \Avocadesign\StatamicTools\Feedback\Digest::recipients() === []) {
            $this->line('  <fg=yellow>!</> feedback is on but nobody hears about new comments: mark the team in resources/site/reviewers.yaml with team: true, or set FEEDBACK_NOTIFY');
        }
        if (app()->isProduction() && FeedbackSettings::active()) {
            $this->line('  <fg=yellow>!</> feedback is switched on in production, so every page shows the widget: set FEEDBACK_ENABLED=false once the review is over');
            $warnings++;
        }

        // The AI block catalogue the site commits must match what its fieldsets and guidance produce now.
        $catalogue = Catalogue::relativePath();
        $problem = match (true) {
            ! is_file(Catalogue::path()) => "no AI block catalogue at {$catalogue}",
            (string) file_get_contents(Catalogue::path()) !== Catalogue::build() => "{$catalogue} is out of date",
            default => null,
        };
        if ($problem === null) {
            $this->line("  <fg=green>✓</> {$catalogue} is up to date");
        } elseif ($this->option('strict')) {
            $this->line("  <fg=red>✗</> {$problem}: run php please avoca:site:catalogue");
            $failures++;
        } else {
            $this->line("  <fg=yellow>!</> {$problem}: run php please avoca:site:catalogue");
            $warnings++;
        }

        // The editor role holds the permissions for every structure that isn't opted out, so the site is ready for editors
        // when it moves to Statamic Pro. Always a warning: roles do nothing on Core, so --strict never fails on it.
        $warnings += $this->checkEditorAccess();

        // The server scripts a site keeps its own copy of. Nothing is said about a site that has none: a site whose
        // content is not edited on the server never needs one. A copy the site has changed is left alone too, because
        // owning it is the point. Always a warning: the site works either way.
        $warnings += $this->checkServerScripts();

        // The critical CSS entry, on a site that has one. Always a warning: a site renders either way, and the cost of
        // a missed @source line is the top of the page moving a moment after it paints, not a broken page.
        $warnings += $this->checkCriticalCss();

        // Render the pages the way a browser would and make sure everything appears.
        foreach (['style', 'content'] as $page) {
            $response = app()->handle(Request::create("/{$prefix}/{$page}", 'GET'));
            if ($response->getStatusCode() !== 200) {
                $this->line("  <fg=red>✗</> /{$prefix}/{$page} returned HTTP {$response->getStatusCode()}");
                $failures++;
                continue;
            }
            $html = (string) $response->getContent();
            if ($page === 'content') {
                foreach ([['block', $blocks], ['set', $sets]] as [$attr, $items]) {
                    foreach (array_keys($items) as $handle) {
                        if (! str_contains($html, "data-sk-{$attr}=\"{$handle}\"")) {
                            $this->line("  <fg=red>✗</> /{$prefix}/content does not render {$attr} {$handle}");
                            $failures++;
                        }
                    }
                }
                $options = substr_count($html, 'data-sk-option=');
                $this->line("  <fg=green>✓</> /{$prefix}/content: ".count($blocks).' blocks, '.count($sets)." sets, {$options} options");
            } else {
                $this->line("  <fg=green>✓</> /{$prefix}/style: ".substr_count($html, 'class="sk-swatch"').' colours, '.substr_count($html, 'data-sk-scheme=').' schemes, '.substr_count($html, 'data-sk-stack=').' stack values');
            }
        }

        $this->newLine();
        if ($failures) {
            $this->error("{$failures} problem(s)".($warnings ? ", {$warnings} warning(s)" : ''));

            return self::FAILURE;
        }
        $this->info('Site pages are in step with the fieldsets'.($warnings ? " ({$warnings} warning(s))" : '.'));

        return self::SUCCESS;
    }

    /** Writes the lines for the critical CSS entry, and returns how many of them are warnings. */
    private function checkCriticalCss(): int
    {
        $critical = CriticalCss::make();
        if (! $critical->exists()) {
            return 0;   // a site without one is a site the recipe has nothing to say about
        }

        $path = $critical->path();
        $warnings = 0;

        if (! $critical->inViteInput()) {
            $this->line("  <fg=yellow>!</> {$path} is not in the input list in vite.config.js, so it is never built");
            $warnings++;
        }

        if (! $critical->inlinedByLayout()) {
            $this->line("  <fg=yellow>!</> {$path} is built but the layout does not inline it with vite:content");
            $warnings++;
        }

        foreach ($critical->uncovered() as $view) {
            $this->line("  <fg=yellow>!</> {$view} paints above the fold and no @source line in {$path} covers it: its utilities arrive when the full sheet lands");
            $warnings++;
        }

        if ($warnings === 0) {
            $this->line("  <fg=green>✓</> {$path} covers what paints above the fold");
        }

        return $warnings;
    }

    /** Writes the lines for the server scripts the site has a copy of, and returns how many of them are warnings. */
    private function checkServerScripts(): int
    {
        $scripts = Scripts::make();
        $warnings = 0;

        foreach (Scripts::names() as $name) {
            $path = $scripts->path($name);
            $status = $scripts->status($name);
            $warning = match ($status) {
                'behind' => "{$path} is behind the add-on's copy: run php please avoca:site:script {$name}",
                'edited and behind' => "{$path} has this site's changes and the add-on's copy has moved on since: run php please avoca:site:script {$name} --diff",
                'unrecorded' => "{$path} is not recorded in {$scripts->installedPath()}, so whether this site changed it cannot be told: run php please avoca:site:script {$name} --diff",
                default => null,
            };
            if ($warning !== null) {
                $this->line("  <fg=yellow>!</> {$warning}");
                $warnings++;
            } elseif ($status === 'current') {
                $this->line("  <fg=green>✓</> {$path} matches the add-on's copy");
            }
        }

        return $warnings;
    }

    /** Writes the lines for the editor role's permissions, and returns how many of them are warnings. */
    private function checkEditorAccess(): int
    {
        $access = EditorAccess::resolve();
        $role = $access->role();
        $warnings = [];
        if (($problems = $access->problems()) !== []) {
            $warnings = array_map(fn (string $problem) => "editor permissions were not checked, because {$problem}", $problems);
        } elseif (! $access->roleExists()) {
            $warnings[] = "the site has no {$role} role, so editor permissions were not checked";
        } else {
            $structures = EditorAccess::structures();
            $plan = $access->plan($structures);
            $missing = array_filter($plan, fn (array $step) => $step['status'] === 'add');
            foreach ($missing as $step) {
                $count = count($step['add']);
                $warnings[] = "the {$role} role is missing {$count} ".($count === 1 ? 'permission' : 'permissions').' for '.PermissionSets::name($step['type'], $step['handle'])
                    .": run php please avoca:site:permissions, or opt it out in {$access->optOutsPath()}";
            }
            if ($missing === []) {
                $optedOut = count(array_filter($plan, fn (array $step) => $step['status'] === 'opted_out'));
                $covered = count($plan) - $optedOut;
                $this->line("  <fg=green>✓</> the {$role} role has the permissions for {$covered} ".($covered === 1 ? 'structure' : 'structures').($optedOut ? ", with {$optedOut} opted out" : ''));
            }
            foreach ($access->strays($structures) as $stray) {
                $warnings[] = "{$access->optOutsPath()} opts out {$stray}, which the site does not have";
            }
        }
        foreach ($warnings as $warning) {
            $this->line("  <fg=yellow>!</> {$warning}");
        }

        return count($warnings);
    }
}
