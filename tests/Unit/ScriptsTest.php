<?php

namespace Avocadesign\StatamicTools\Tests\Unit;

use Avocadesign\StatamicTools\Site\Scripts;
use Avocadesign\StatamicTools\Tests\TestCase;
use Statamic\Facades\YAML;

class ScriptsTest extends TestCase
{
    private const NAME = 'server-git.sh';

    private string $base;

    private string $root;

    private string $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/avoca-scripts-'.uniqid();
        $this->root = "{$this->base}/site";
        $this->package = "{$this->base}/package";
        $this->write("{$this->package}/scripts/".self::NAME, "#!/usr/bin/env bash\necho one\n");
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->base);
        parent::tearDown();
    }

    public function test_a_site_without_a_copy_is_missing_and_needs_no_force()
    {
        $this->assertSame('missing', $this->scripts()->status(self::NAME));
        $this->assertFalse($this->scripts()->needsForce(self::NAME));
    }

    public function test_publishing_writes_the_package_copy_and_records_it()
    {
        $this->scripts()->publish(self::NAME, 'v0.1.14', '2026-09-22');

        $file = "{$this->root}/scripts/".self::NAME;
        $this->assertFileExists($file);
        $this->assertSame("#!/usr/bin/env bash\necho one\n", file_get_contents($file));
        $this->assertSame('current', $this->scripts()->status(self::NAME));

        $record = $this->scripts()->record(self::NAME);
        $this->assertSame('v0.1.14', $record['version']);
        $this->assertSame('2026-09-22', $record['installed']);
        $this->assertSame('scripts', $record['category']);
        $this->assertSame('sha256:'.hash('sha256', "#!/usr/bin/env bash\necho one\n"), $record['checksum']);
    }

    public function test_the_published_copy_is_executable()
    {
        $this->scripts()->publish(self::NAME, 'v0.1.14', '2026-09-22');

        // Cron runs it, so the mode matters more than the contents.
        $this->assertSame('0755', substr(sprintf('%o', fileperms("{$this->root}/scripts/".self::NAME)), -4));
    }

    public function test_a_copy_the_site_has_not_touched_is_behind_when_the_package_moves_on()
    {
        $this->scripts()->publish(self::NAME, 'v0.1.14', '2026-09-22');
        $this->write("{$this->package}/scripts/".self::NAME, "#!/usr/bin/env bash\necho two\n");

        $this->assertSame('behind', $this->scripts()->status(self::NAME));
        // Publishing over it loses nothing, so it does not ask for --force.
        $this->assertFalse($this->scripts()->needsForce(self::NAME));
    }

    public function test_a_copy_the_site_changed_is_edited_and_needs_force()
    {
        $this->scripts()->publish(self::NAME, 'v0.1.14', '2026-09-22');
        $this->write("{$this->root}/scripts/".self::NAME, "#!/usr/bin/env bash\necho this site's own\n");

        $this->assertSame('edited', $this->scripts()->status(self::NAME));
        $this->assertTrue($this->scripts()->needsForce(self::NAME));
    }

    public function test_both_changing_is_edited_and_behind()
    {
        $this->scripts()->publish(self::NAME, 'v0.1.14', '2026-09-22');
        $this->write("{$this->root}/scripts/".self::NAME, "#!/usr/bin/env bash\necho this site's own\n");
        $this->write("{$this->package}/scripts/".self::NAME, "#!/usr/bin/env bash\necho two\n");

        $this->assertSame('edited and behind', $this->scripts()->status(self::NAME));
        $this->assertTrue($this->scripts()->needsForce(self::NAME));
    }

    public function test_a_copy_nothing_recorded_is_unrecorded_and_needs_force()
    {
        $this->write("{$this->root}/scripts/".self::NAME, "#!/usr/bin/env bash\necho from somewhere\n");

        $this->assertSame('unrecorded', $this->scripts()->status(self::NAME));
        $this->assertTrue($this->scripts()->needsForce(self::NAME));
    }

    public function test_a_copy_that_matches_the_package_is_current_even_with_nothing_recorded()
    {
        $this->write("{$this->root}/scripts/".self::NAME, "#!/usr/bin/env bash\necho one\n");

        $this->assertSame('current', $this->scripts()->status(self::NAME));
    }

    public function test_publishing_leaves_the_library_items_in_installed_yaml_alone()
    {
        $this->write("{$this->root}/resources/site/installed.yaml", YAML::dump([
            'faq' => ['version' => '1.0.0', 'installed' => '2026-09-15', 'category' => 'presets'],
        ]));

        $this->scripts()->publish(self::NAME, 'v0.1.14', '2026-09-22');

        $installed = YAML::parse((string) file_get_contents("{$this->root}/resources/site/installed.yaml"));
        $this->assertSame(['faq', self::NAME], array_keys($installed));
        $this->assertSame('1.0.0', $installed['faq']['version']);
    }

    public function test_it_only_knows_the_scripts_the_package_ships()
    {
        $this->assertTrue($this->scripts()->has(self::NAME));
        $this->assertFalse($this->scripts()->has('something-else.sh'));
        $this->assertSame([self::NAME, 'pre-push'], Scripts::names());
    }

    public function test_the_pre_push_hook_goes_in_githooks_executable()
    {
        $this->write("{$this->package}/scripts/pre-push", "#!/usr/bin/env bash\nexit 0\n");
        $this->scripts()->publish('pre-push', 'v0.2.10', '2026-11-26');

        $file = "{$this->root}/.githooks/pre-push";
        $this->assertSame('.githooks/pre-push', $this->scripts()->path('pre-push'));
        $this->assertFileExists($file);
        $this->assertFileDoesNotExist("{$this->root}/scripts/pre-push");
        $this->assertSame('0755', substr(sprintf('%o', fileperms($file)), -4));
        $this->assertSame('current', $this->scripts()->status('pre-push'));
    }

    public function test_the_package_ships_every_script_it_names()
    {
        $package = new Scripts($this->root, dirname(__DIR__, 2));

        foreach (Scripts::names() as $name) {
            $this->assertFileExists($package->template($name));
            $this->assertTrue(is_executable($package->template($name)), "{$name} is not executable");
        }
    }

    private function scripts(): Scripts
    {
        return new Scripts($this->root, $this->package);
    }

    private function write(string $file, string $contents): void
    {
        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $contents);
    }
}
