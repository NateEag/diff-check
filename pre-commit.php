#! /usr/bin/env php
<?php

require_once realpath(__DIR__ . '/vendor/autoload.php');

function main()
{
    // Get array of files being added to commit (git diff --cached should work)
    // GRIPE I should probably expand Gitter to support this operation, rather
    // than shelling out.
    $staged_files = array();
    $cmd = 'git diff --cached --name-only';
    exec($cmd, $staged_files, $status);
    if ($status !== 0) {
        throw new RuntimeException("$cmd exit code was $status!");
    }

    $staged_errors = array();
    foreach ($staged_files as $file) {
        // Committing with syntax errors is *never* okay.
        $syntax_check_msgs = array();
        exec("php -l $file", $syntax_check_msgs, $syntax_check_status);
        if ($syntax_check_status !== 0) {
            $staged_errors[$file] = $syntax_check_msgs[2];
        }

        // Run PHP CS on $file (must eventually load config options and
        // coding style from somewhere)

        if (count($style_errors) < 1) {
            continue;
        }

        // Get array of line numbers that are added by the staged patch.
        $new_line_nums = array(); // STUB Some invocation of git diff.

        foreach ($style_errors as $error) {
            // If the error is on a modified line, add it to our error list.
            // Else, bail.
        }
    }

    if (count($staged_errors) > 0) {
        // STUB Output list of errors that need fixed, with file:line num,
        // and bail.
        exit(1);
    }

    exit(0);
}

main();
