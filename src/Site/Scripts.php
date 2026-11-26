<?php

namespace Avocadesign\StatamicTools\Site;

use Avocadesign\StatamicTools\Library\Library;
use Composer\InstalledVersions;

/**
 * The scripts the add-on publishes into a site: the server's git script and the pre-push hook. The package holds the one
 * canonical copy and never edits it in place; a site gets its own, at scripts/<name> or where PLACED puts it, and owns it
 * from then on, because how a server commits a site's content
 * is the site's business rather than the package's. The site's copy is never read back: the package's file is always
 * the source. What was published, and a checksum of it, goes in resources/site/installed.yaml beside the library items,
 * so a later release can tell a copy the site has changed from one that has simply not caught up.
 */
final class Scripts
{
    public const PACKAGE = 'avocadesign/statamic-tools';

    /** What the add-on can publish: the name a site knows it by => its path inside the package. */
    private const AVAILABLE = [
        'server-git.sh' => 'scripts/server-git.sh',
        'pre-push' => 'scripts/pre-push',
    ];

    /** A script that lives somewhere other than the site's scripts folder: its name => its path from the site root. */
    private const PLACED = [
        'pre-push' => '.githooks/pre-push',
    ];

    public function __construct(
        private string $root,
        private string $package,
        private string $installedPath = 'resources/site/installed.yaml',
        private string $directory = 'scripts',
    ) {
    }

    public static function make(): self
    {
        return new self(
            base_path(),
            dirname(__DIR__, 2),
            (string) config('statamic-tools.library.installed_path', 'resources/site/installed.yaml'),
            (string) config('statamic-tools.site.scripts_path', 'scripts'),
        );
    }

    /** @return array<int, string> */
    public static function names(): array
    {
        return array_keys(self::AVAILABLE);
    }

    /** The add-on release a published copy came from. A path repository has no release, and says so. */
    public static function version(): string
    {
        if (! InstalledVersions::isInstalled(self::PACKAGE)) {
            return 'unknown';
        }

        return InstalledVersions::getPrettyVersion(self::PACKAGE) ?? 'unknown';
    }

    public function has(string $name): bool
    {
        return isset(self::AVAILABLE[$name]);
    }

    /** The package's copy, which is the only one anything here reads. */
    public function template(string $name): string
    {
        return rtrim($this->package, '/').'/'.self::AVAILABLE[$name];
    }

    /** Where the site's own copy lives, relative to the site root. */
    public function path(string $name): string
    {
        return self::PLACED[$name] ?? trim($this->directory, '/').'/'.$name;
    }

    public function file(string $name): string
    {
        return rtrim($this->root, '/').'/'.$this->path($name);
    }

    public function installedPath(): string
    {
        return $this->installedPath;
    }

    /**
     * How the site's copy stands against the package's:
     *
     *   missing            the site has none, which is normal for a site whose content is not edited on the server
     *   current            byte for byte the copy the add-on ships
     *   behind             the add-on's copy moved on and the site has not changed its own, so publishing loses nothing
     *   edited             the site changed its copy and the add-on's has not moved, so publishing would throw work away
     *   edited and behind  both changed, so the two want merging by hand
     *   unrecorded         a copy nothing recorded publishing, so whether the site changed it cannot be known
     */
    public function status(string $name): string
    {
        if (! is_file($this->file($name))) {
            return 'missing';
        }

        $copy = $this->digest((string) file_get_contents($this->file($name)));
        $template = $this->digest((string) file_get_contents($this->template($name)));

        if ($copy === $template) {
            return 'current';
        }

        $published = $this->record($name)['checksum'] ?? null;

        return match (true) {
            ! is_string($published) => 'unrecorded',
            $copy !== $published && $template !== $published => 'edited and behind',
            $copy !== $published => 'edited',
            default => 'behind',
        };
    }

    /** Whether publishing over the site's copy would throw away work it did. */
    public function needsForce(string $name): bool
    {
        return in_array($this->status($name), ['edited', 'edited and behind', 'unrecorded'], true);
    }

    /** What the site recorded when this script was last published to it. */
    public function record(string $name): ?array
    {
        $entry = $this->library()->installed()[$name] ?? null;

        return is_array($entry) ? $entry : null;
    }

    /** Writes the package's copy into the site, executable, and records what was written. */
    public function publish(string $name, string $version, string $date): void
    {
        $contents = (string) file_get_contents($this->template($name));
        $file = $this->file($name);

        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        file_put_contents($file, $contents);
        // Explicitly, because the umask would otherwise decide whether cron, or git for a hook, can run it.
        chmod($file, 0755);

        $this->library()->write($name, [
            'version' => $version,
            'installed' => $date,
            'category' => 'scripts',
            'checksum' => $this->digest($contents),
        ]);
    }

    private function digest(string $contents): string
    {
        return 'sha256:'.hash('sha256', $contents);
    }

    /** Only ever used for resources/site/installed.yaml, so it needs no library paths of its own. */
    private function library(): Library
    {
        return new Library([], $this->root, $this->installedPath);
    }
}
