<?php

define('DIR_SEP', DIRECTORY_SEPARATOR);

class PreCommitHookTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $tmp_dir = sys_get_temp_dir();
        $timestamp = time();
        $this->git_dir = $tmp_dir . DIR_SEP .
            "php-cs-hook-test.$timestamp";
        $this->fixture_dir = __DIR__ . DIR_SEP . 'fixtures';

        exec("git init " . $this->git_dir);

        // GRIPE Lame hack install technique FTL...
        // DEBUG When I get a chance, I should make running a build part of my
        // setup for the tests, so I can then use whatever install technique
        // we'll normally use. I'm thinking a .phar?
        $git_hooks_dir = $this->git_dir . DIR_SEP . ".git" .
            DIR_SEP . "hooks";
        $project_dir = __DIR__ . DIR_SEP . "..";

        exec(
            'cp -R ' . $project_dir . DIR_SEP . 'vendor ' .
            $git_hooks_dir
        );

        $pre_commit_hook_path = $git_hooks_dir . DIR_SEP . 'pre-commit';
        copy(
            $project_dir . DIR_SEP . 'pre-commit.php',
            $pre_commit_hook_path
        );

        chmod($pre_commit_hook_path, 0755);
    }

    public function testFirstCommit()
    {
        $result = copy(
            $this->fixture_dir . DIR_SEP . 'first-commit'. DIR_SEP . 'functions.php',
            $this->git_dir . DIR_SEP . 'functions.php'
        );

        exec(
            'cd ' . $this->git_dir . ' && git add functions.php && ' .
            'git commit -m a',
            $output = array(),
            $status
        );

        $this->assertEquals(0, $status);
    }

    public function testPreventStyleErrorCommit()
    {
        copy(
            $this->fixture_dir . DIR_SEP . 'add-bad-function'. DIR_SEP . 'functions.php',
            $this->git_dir . DIR_SEP . 'functions.php'
        );

        exec(
            'cd ' . $this->git_dir . ' && git add functions.php && ' .
            'git commit -m a',
            $output = array(),
            $status
        );

        $this->assertEquals(1, $status);
    }

    public function testPreventStyleWarningCommit()
    {
        copy(
            $this->fixture_dir . DIR_SEP . 'add-side-effect'. DIR_SEP . 'functions.php',
            $this->git_dir . DIR_SEP . 'functions.php'
        );

        exec(
            'cd ' . $this->git_dir . ' && git add functions.php && ' .
            'git commit -m a',
            $output = array(),
            $status
        );

        $this->assertEquals(1, $status);
    }

    protected function tearDown()
    {
        exec('rm -rf ' . $this->git_dir);
    }
}
