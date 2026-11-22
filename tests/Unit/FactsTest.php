<?php

namespace Avocadesign\StatamicTools\Tests\Unit;

use Avocadesign\StatamicTools\Site\Facts;
use Avocadesign\StatamicTools\Tests\TestCase;

class FactsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/avoca-facts-'.uniqid();
        mkdir($this->root.'/content/collections/pages', 0755, true);
        mkdir($this->root.'/content/feedback', 0755, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
        parent::tearDown();
    }

    public function test_a_file_without_markers_is_no_problem(): void
    {
        $this->assertSame([], Facts::problems(['content/collections/pages/home.md' => "title: Home\n"]));
    }

    public function test_each_file_with_markers_is_one_line_counting_each_kind(): void
    {
        $problems = Facts::problems([
            'content/collections/pages/about.md' => "Founded in [NEEDS PROOF: the year].\nFitted [NEEDS PROOF: how many] kitchens.\n",
            'content/collections/work/smith.md' => "Their enquiries doubled. [CLIENT TO APPROVE]\nSince [NEEDS PROOF: date].\n",
        ]);

        $this->assertSame([
            'content/collections/pages/about.md still has 2 [NEEDS PROOF]: settle each from resources/site/facts.md or take the claim out before it goes live',
            'content/collections/work/smith.md still has 1 [NEEDS PROOF] and 1 [CLIENT TO APPROVE]: settle each from resources/site/facts.md or take the claim out before it goes live',
        ], $problems);
    }

    public function test_the_scan_reads_content_but_not_the_review_s_comments(): void
    {
        file_put_contents($this->root.'/content/collections/pages/about.md', "Since [NEEDS PROOF: year]\n");
        file_put_contents($this->root.'/content/collections/pages/home.yaml', "title: Home\n");
        file_put_contents($this->root.'/content/collections/pages/notes.txt', "[NEEDS PROOF]\n");
        file_put_contents($this->root.'/content/feedback/abc.yaml', "body: Can we say [NEEDS PROOF here?\n");

        $files = Facts::scan($this->root.'/content', $this->root);

        $this->assertSame(['content/collections/pages/about.md', 'content/collections/pages/home.yaml'], array_keys($files));
        $this->assertCount(1, Facts::problems($files));
    }

    public function test_a_site_without_a_content_folder_has_nothing_to_scan(): void
    {
        $this->assertSame([], Facts::scan($this->root.'/missing', $this->root));
    }
}
