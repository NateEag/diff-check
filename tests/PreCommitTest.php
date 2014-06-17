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
        $regex = '';
        file_put_contents(
            $git_hooks_dir . DIR_SEP . 'pre-commit.conf',
            '{"command": "' . $command . '", ' .
            '"error_line_num_regex": "/:([0-9]+):[0-9]+/", ' .
            '"checked_file_extensions": ["php"],' .
            '"msg_regex": "/:[0-9]+:[0-9]+: (.*)/"}'
        );

        chmod($pre_commit_hook_path, 0755);

        // Make the initial commit. We do not verify it because we have no
        // previous commit to diff against.
        // TODO Figure out how to verify the first commit.
        $result = copy(
            $this->fixture_dir . DIR_SEP . 'first-commit'. DIR_SEP . 'functions.php',
            $this->git_dir . DIR_SEP . 'functions.php'
        );

        exec(
            'cd ' . $this->git_dir . ' && git add functions.php && ' .
            'git commit -m a --no-verify',
            $output = array(),
            $status
        );
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

    public function testPreventOneLineErrorCommit()
    {
        $source = $this->fixture_dir . DIR_SEP . 'add-one-line-error' .
            DIR_SEP . 'functions.php';
        $target = $this->git_dir . DIR_SEP . 'functions.php';
        copy($source, $target);

        $cmd = 'cd ' . escapeshellarg($this->git_dir) . ' && ' .
            'git add functions.php && ' .
            'git commit -m a';

        exec($cmd, $output = array(), $status);

        $this->assertEquals(1, $status);
    }

    /* @brief Helper to add a commit with style errors.
     */
    protected function addStyleErrorCommit()
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
    }

    public function testIgnoreStyleErrorsInEarlierCommit()
    {
        $this->addStyleErrorCommit();

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

    public function testErrorsInMultipleFiles()
    {
        copy(
            $this->fixture_dir . DIR_SEP . 'break-plural-files'. DIR_SEP . 'functions.php',
            $this->git_dir . DIR_SEP . 'functions.php'
        );

        copy(
            $this->fixture_dir . DIR_SEP . 'break-plural-files'. DIR_SEP . 'bad-class.php',
            $this->git_dir . DIR_SEP . 'bad-class.php'
        );

        $output = shell_exec(
            'cd ' . $this->git_dir . ' && git add functions.php && ' .
            'git add bad-class.php && git commit -m a 2>&1'
        );
        $output = explode("\n", $output);

        $this->assertContains("bad-class.php has style errors:", $output);
        $this->assertContains("functions.php has style errors:", $output);
    }

    public function testIgnoreDeletedFiles()
    {
        $this->addStyleErrorCommit();

        $status = null;
        $output = array();
        exec(
            'cd ' . $this->git_dir . ' && git rm functions.php && ' .
            'git commit -m a 2>&1',
            $output,
            $status
        );

        $this->assertNotContains(
            "fatal: ambiguous argument 'functions.php': unknown revision or " .
            "path not in the working tree.",
            $output
        );
    }

    public function testIgnoreUnstagedChanges()
    {
        // Make and stage some changes. These should be in our error output.
        copy(
            $this->fixture_dir . DIR_SEP . 'add-bad-function'. DIR_SEP . 'functions.php',
            $this->git_dir . DIR_SEP . 'functions.php'
        );

        exec('cd ' . $this->git_dir . ' && git add functions.php');

        // Make *unstaged* change. This should not be noticed.
        copy(
            $this->fixture_dir . DIR_SEP . 'add-error-to-good-function' .
            DIR_SEP . 'functions.php',
            $this->git_dir . DIR_SEP . 'functions.php'
        );

        $output = array();
        exec('cd ' . $this->git_dir . ' && git commit -m a 2>&1', $output);

        $this->assertNotContains(
            'Line 5: error - Each PHP statement must be on a line by itself',
            $output
        );
    }

    public function testIgnoreUncheckedFiles()
    {
        $result = copy(
            $this->fixture_dir . DIR_SEP . 'add-non-php-file'. DIR_SEP . 'readme.txt',
            $this->git_dir . DIR_SEP . 'readme.txt'
        );


        exec(
            'cd ' . $this->git_dir . ' && git add . && ' .
            'git commit -m a',
            $output = array(),
            $status
        );

        $this->assertEquals(0, $status);
    }

    public function testCheckNestedFile()
    {
        mkdir($this->git_dir . DIR_SEP . 'include');

        copy(
            $this->fixture_dir . DIR_SEP . 'add-nested-php-file'. DIR_SEP .
            'include' . DIR_SEP . 'test.php',
            $this->git_dir . DIR_SEP . 'include' . DIR_SEP . 'test.php'
        );

        $output = array();
        $status = null;
        exec(
            'cd ' . $this->git_dir . ' && git add . && ' .
            'git commit -m a',
            $output,
            $status
        );

        $this->assertEquals(1, $status);
    }

    protected function tearDown()
    {
        exec('rm -rf ' . escapeshellarg($this->git_dir) . '');
    }
}
