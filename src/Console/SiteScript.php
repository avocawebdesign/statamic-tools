<?php

namespace Avocadesign\StatamicTools\Console;

use Avocadesign\StatamicTools\Site\Scripts;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Puts one of the add-on's scripts in the site, where the site owns it: scripts/server-git.sh, or the pre-push hook at
 * .githooks/pre-push. Run by hand, and by
 * the starter kit when a site is first installed. It refuses to write over a copy the site has changed, so a site that
 * needed something different keeps it: --diff says what differs and --force overwrites. Not for a deploy script, which
 * would then fail on every deploy of a site that customised its copy.
 */
class SiteScript extends Command
{
    protected $signature = 'avoca:site:script
        {name=server-git.sh : The script to publish}
        {--force : Write over a copy this site has changed}
        {--diff : Say how the site\'s copy differs from the add-on\'s, and change nothing}';

    protected $description = "Publish the add-on's server git script or pre-push hook into the site, where the site can change it.";

    public function handle(): int
    {
        $scripts = Scripts::make();
        $name = (string) $this->argument('name');

        if (! $scripts->has($name)) {
            $this->error("There is no script called {$name}. The add-on has: ".implode(', ', Scripts::names()).'.');

            return self::FAILURE;
        }

        $status = $scripts->status($name);
        $path = $scripts->path($name);

        if ($this->option('diff')) {
            return $this->showDifference($scripts, $name, $status);
        }

        if ($status === 'current' && ! $this->option('force')) {
            $this->info("{$path} is already the copy the add-on ships.");
            $this->nextStep($name, $path);

            return self::SUCCESS;
        }

        if ($scripts->needsForce($name) && ! $this->option('force')) {
            $this->warn("{$path} was left alone. ".$this->reason($status, $scripts));
            $this->line("  See what differs:  <fg=cyan>php please avoca:site:script {$name} --diff</>");
            $this->line("  Take the add-on's: <fg=cyan>php please avoca:site:script {$name} --force</>");
            $this->line('  The site\'s copy is in git, so --force is undoable.');

            return self::FAILURE;
        }

        $was = $scripts->record($name)['version'] ?? null;
        $scripts->publish($name, $version = Scripts::version(), now()->toDateString());

        $this->info(match ($status) {
            'missing' => "Wrote {$path} from {$version}.",
            // A path repository reports the same version either side, so only name both when they differ.
            'behind' => 'Updated '.$path.($was === $version ? " to {$version}." : " from {$was} to {$version}.").' The site had not changed its copy.',
            default => "Wrote {$path} from {$version}, over a copy this site had changed.",
        });
        $this->line("It is the site's file now: change it if this site needs something different, and commit it.");
        $this->line("Recorded in {$scripts->installedPath()}.");
        $this->nextStep($name, $path);

        return self::SUCCESS;
    }

    private function nextStep(string $name, string $path): void
    {
        $this->line(match ($name) {
            // A hook reads the push it is checking from git, so it is switched on rather than run.
            'pre-push' => 'Switch it on in each clone you work in, never on a server: <fg=cyan>git config core.hooksPath .githooks</>',
            default => "See where the site stands: <fg=cyan>./{$path} status</>",
        });
    }

    private function reason(string $status, Scripts $scripts): string
    {
        return match ($status) {
            'edited' => 'It has changes this site made.',
            'edited and behind' => "It has changes this site made, and the add-on's copy has moved on since, so the two want merging by hand.",
            default => "Nothing recorded publishing it in {$scripts->installedPath()}, so whether this site changed it cannot be told.",
        };
    }

    private function showDifference(Scripts $scripts, string $name, string $status): int
    {
        $path = $scripts->path($name);

        if ($status === 'missing') {
            $this->info("This site has no {$path} yet. Run php please avoca:site:script {$name} to write one.");

            return self::SUCCESS;
        }

        if ($status === 'current') {
            $this->info("{$path} is the copy the add-on ships, byte for byte.");

            return self::SUCCESS;
        }

        $this->line("{$path} against the add-on's copy. A line marked - is the site's, + is the add-on's.");
        $this->newLine();

        $diff = new Process(['diff', '-u', '--label', $path, $scripts->file($name), '--label', 'the add-on\'s copy', $scripts->template($name)]);
        $diff->run();

        // diff exits 1 when the files differ, which is the whole point of running it; only 2 and up is trouble.
        if ($diff->getExitCode() !== null && $diff->getExitCode() < 2) {
            $this->line(rtrim($diff->getOutput()));
        } else {
            $this->warn('Could not run diff here. Compare them yourself:');
            $this->line("  git diff --no-index {$path} ".$scripts->template($name));
        }

        return self::SUCCESS;
    }
}
