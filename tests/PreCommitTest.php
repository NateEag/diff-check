<?php

class PreCommitHookTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $tmp_dir = sys_get_temp_dir();
        $timestamp = time();
        $dir_sep = DIRECTORY_SEPARATOR;
        $this->git_dir = $tmp_dir . $dir_sep .
            "php-cs-hook-test.$timestamp";

        exec("git init " . $this->git_dir);

        // GRIPE Lame hack install technique FTL...
        $git_hooks_dir = $this->git_dir . $dir_sep . ".git" .
            $dir_sep . "hooks";
        $project_dir = __DIR__ . $dir_sep . "..";

        exec(
            'cp -R ' . $project_dir . $dir_sep . 'vendor ' .
            $git_hooks_dir
        );

        $pre_commit_hook_path = $git_hooks_dir . $dir_sep . 'pre-commit';
        copy(
            $project_dir . $dir_sep . 'pre-commit.php',
            $pre_commit_hook_path
        );

        chmod($pre_commit_hook_path, 0755);
    }

    public function testSomething()
    {
        // STUB I need to put an actual test in here eventually.
    }

    protected function tearDown()
    {
        exec('rm -rf ' . $this->git_dir);
    }
}
