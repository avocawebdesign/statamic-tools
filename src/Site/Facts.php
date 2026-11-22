<?php

namespace Avocadesign\StatamicTools\Site;

/**
 * Where the site's content still has a claim nobody has settled. recipe/facts.md has an agent write
 * `[NEEDS PROOF: ...]` where a page needs a fact it hasn't been given, and `[CLIENT TO APPROVE]` beside a customer
 * outcome the client hasn't approved, so a gap stays visible rather than being filled with an invented number.
 * Neither should go live. Nothing here changes a file: it says which files still have them.
 */
final class Facts
{
    /** Each marker as it is reported, keyed to the text that finds it, which leaves off the closing bracket. */
    public const MARKERS = [
        '[NEEDS PROOF]' => '[NEEDS PROOF',
        '[CLIENT TO APPROVE]' => '[CLIENT TO APPROVE',
    ];

    /** Folders under content/ that never reach a page: feedback holds the review's comments. */
    private const SKIP = ['feedback'];

    /**
     * @param  array<string, string>  $files  each file's contents, keyed by its path from the site's root
     * @return array<int, string> one line per file that still has a marker, or none
     */
    public static function problems(array $files): array
    {
        $problems = [];
        foreach ($files as $path => $content) {
            $found = [];
            foreach (self::MARKERS as $label => $needle) {
                $count = substr_count($content, $needle);
                if ($count > 0) {
                    $found[] = "{$count} {$label}";
                }
            }
            if ($found !== []) {
                $problems[] = "{$path} still has ".implode(' and ', $found).': settle each from '.self::relativePath()
                    .' or take the claim out before it goes live';
            }
        }

        return $problems;
    }

    /**
     * The content files a page can be built from, read from a content folder.
     *
     * @return array<string, string> each file's contents, keyed by its path from $base, in path order
     */
    public static function scan(string $content, string $base): array
    {
        if (! is_dir($content)) {
            return [];
        }

        $files = [];
        $items = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($content, \FilesystemIterator::SKIP_DOTS),
            fn (\SplFileInfo $item) => ! ($item->isDir() && $item->getPath() === rtrim($content, '/') && in_array($item->getFilename(), self::SKIP, true))
        ));
        foreach ($items as $item) {
            if ($item->isFile() && in_array(strtolower($item->getExtension()), ['md', 'yaml', 'yml'], true)) {
                $files[ltrim(substr($item->getPathname(), strlen(rtrim($base, '/'))), '/')] = (string) file_get_contents($item->getPathname());
            }
        }
        ksort($files);

        return $files;
    }

    /** The site's facts file, from the site's root. */
    public static function relativePath(): string
    {
        return trim((string) config('statamic-tools.site.docs_path', 'resources/site'), '/').'/facts.md';
    }

    /** @return array<int, string> the problems for this site, read from its content folder */
    public static function check(): array
    {
        return self::problems(self::scan(base_path('content'), base_path()));
    }
}
