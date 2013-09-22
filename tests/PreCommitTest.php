<?php

// GRIPE I originally defined this using DIRECTORY_SEPARATOR while working on
// OS X so that it'd run happily on Windows.
// But, irony of ironies, since I'm running this in Git Bash on Windows, it
// died horribly due to my "portable" code. Thus, this is being flipped to just
// '/', since on Windows it'll almost always be run from within Git Bash. Not
// many other contexts you'd want to use a git hook script in.
define('DIR_SEP', '/');

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

        // Render a config file to use phpcs as our style check command.
        $command_path = $git_hooks_dir . DIR_SEP .
            implode(DIR_SEP, array('vendor', 'bin', 'phpcs'));
        $command = $command_path . ' --standard=PSR2 --report=emacs';
        $regex = '/:([0-9]+):[0-9]+/';
        file_put_contents(
            $git_hooks_dir . DIR_SEP . 'pre-commit.conf',
            '{"command": "' . $command . '", ' .
            '"error_line_num_regex": "' . $regex . '"}'
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

    public function testIgnoreStyleErrorsInEarlierCommit()
    {
        copy(
            $this->fixture_dir . DIR_SEP . 'add-bad-function'. DIR_SEP . 'functions.php',
            $this->git_dir . DIR_SEP . 'functions.php'
        );

        exec(
            'cd ' . $this->git_dir . ' && git add functions.php && ' .
            'git commit --no-verify -m a',
            $output = array(),
            $status
        );

        $this->assertEquals(0, $status);

        copy(
            $this->fixture_dir . DIR_SEP . 'edit-bad-function'. DIR_SEP . 'functions.php',
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

    public function testIgnoreNonPHPFiles()
    {
        $result = copy(
            $this->fixture_dir . DIR_SEP . 'add-non-php-file'. DIR_SEP . 'functions.php',
            $this->git_dir . DIR_SEP . 'functions.php'
        );


        exec(
            'cd ' . $this->git_dir . ' && git add . && ' .
            'git commit -m a',
            $output = array(),
            $status
        );

        $this->assertEquals(0, $status);
    }

    protected function tearDown()
    {
        exec('rm -rf ' . escapeshellarg($this->git_dir) . '');
    }
}
