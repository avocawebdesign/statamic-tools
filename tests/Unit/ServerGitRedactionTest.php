<?php

namespace Avocadesign\StatamicTools\Tests\Unit;

use Avocadesign\StatamicTools\Tests\TestCase;
use Symfony\Component\Process\Process;

/**
 * Ploi's remote is https://<token>@github.com/..., and one token serves every site on the account. Nothing
 * scripts/server-git.sh prints, on screen, in a cron log or in its saved state, may show it.
 */
class ServerGitRedactionTest extends TestCase
{
    private const TOKEN = 'gho_FAKEtoken1234567890abcdEFGH';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/avoca-server-git-'.uniqid();
        mkdir($this->dir.'/content', 0777, true);
        file_put_contents($this->dir.'/content/page.md', "one\n");
        copy(__DIR__.'/../../scripts/server-git.sh', $this->dir.'/server-git.sh');
        chmod($this->dir.'/server-git.sh', 0755);

        $this->git('init', '-q', '-b', 'main');
        $this->git('add', '.');
        $this->git('-c', 'user.name=t', '-c', 'user.email=t@t', 'commit', '-q', '-m', 'init');
        // Port 9 refuses the connection, so fetch and push fail at once, with git's own message.
        $this->git('remote', 'add', 'origin', 'https://'.self::TOKEN.'@127.0.0.1:9/avocadesign/site.git');
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->dir]))->run();
        parent::tearDown();
    }

    public function test_status_shows_the_remote_without_its_token()
    {
        $output = $this->script('status');

        $this->assertStringContainsString('https://***@127.0.0.1:9/avocadesign/site.git', $output);
        $this->assertStringNotContainsString(self::TOKEN, $output);
    }

    public function test_a_failed_sync_and_commit_never_show_the_token()
    {
        file_put_contents($this->dir.'/content/page.md', "two\n");
        $output = $this->script('sync').$this->script('commit', '--now');

        file_put_contents($this->dir.'/content/page.md', "three\n");
        $output .= $this->script('commit', '--now').$this->script('status');

        $this->assertStringContainsString('FAILED', $output);
        $this->assertStringNotContainsString(self::TOKEN, $output);
        $this->assertStringNotContainsString(self::TOKEN, (string) @file_get_contents($this->dir.'/.git/avoca/push-failed-output'));
    }

    public function test_an_old_push_error_holding_the_token_is_shown_without_it()
    {
        $this->script('status');
        @mkdir($this->dir.'/.git/avoca', 0777, true);
        touch($this->dir.'/.git/avoca/push-failed-since');
        file_put_contents($this->dir.'/.git/avoca/push-failed-output', 'remote: https://'.self::TOKEN."@github.com/a/b.git rejected\n");

        $output = $this->script('status');

        $this->assertStringContainsString('https://***@github.com/a/b.git rejected', $output);
        $this->assertStringNotContainsString(self::TOKEN, $output);
    }

    private function script(string ...$args): string
    {
        $process = new Process(['./server-git.sh', ...$args], $this->dir, [
            'AVOCA_GIT_PATHS' => 'content',
            'AVOCA_GIT_USER_NAME' => 'bot',
            'AVOCA_GIT_USER_EMAIL' => 'bot@example.com',
            'GIT_TERMINAL_PROMPT' => '0',
        ]);
        $process->run();

        return $process->getOutput().$process->getErrorOutput();
    }

    private function git(string ...$args): void
    {
        $process = new Process(['git', ...$args], $this->dir);
        $process->mustRun();
    }
}
