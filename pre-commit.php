#! /usr/bin/env php
<?php

/* @brief Git pre-commit hook to enforce a PHP coding style.
 *
 * It uses CodeSniffer to check the current patch's style, and aborts the
 * commit if it adds style errors.
 *
 * Note that unlike many CodeSniffer-based pre-commit hooks, it only checks the
 * lines being added for style errors. This makes it ideal for use with large,
 * ugly codebases, as it lets you enforce a coding style without forcing devs
 * to fix the bulk of an ugly file in order to pass style checks.
 */

// Save real working directory, since PHP_CodeSniffer stomps it on being
// instantiated.
define('CWD', getcwd());

require_once realpath(__DIR__ . '/vendor/autoload.php');

/* @brief Return an array of files that have been staged for the current commit.
 */
function get_staged_PHP_files()
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

    $staged_PHP_files = array();
    foreach ($staged_files as $filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'php') {
            $staged_PHP_files[] = $filename;
        }

    }

    return $staged_PHP_files;
}

/* @brief Return an array of messages outlining style errors in $filename.
 */
function get_style_errors($filename)
{
    $staged_file_errors = array();

    // Committing with syntax errors is *never* okay.
    $syntax_check_msgs = array();
    exec("php -l $filename", $syntax_check_msgs, $syntax_check_status);
    if ($syntax_check_status !== 0) {
        $staged_file_errors[] = $syntax_check_msgs[2];
    }

    // Run PHP CS on $filename
    // (must eventually load config options and coding style from somewhere)
    // Create an instance of PHP_CodeSniffer.
    $phpcs = new PHP_CodeSniffer();
    $phpcs->setTokenListeners('PSR2');
    // GRIPE I don't understand why I have to call these, but apparently I
    // do.
    $phpcs->populateCustomRules();
    $phpcs->populateTokenListeners();
    $phpcs_file = $phpcs->processFile(CWD . DIRECTORY_SEPARATOR . $filename);
    $style_errors = $phpcs_file->getErrors();
    $style_warnings = $phpcs_file->getWarnings();

    if (count($style_errors) < 1 && count($style_warnings) < 1) {
        return array();
    }

    // Get array of line numbers that are added by the staged patch.
    $added_line_nums = array();
    $diff_lines = array();
    // GRIPE Have to move back to CWD so shelling out to git diff works.
    // I'm hoping to add support for some of what I need to gitter, maybe?
    chdir(CWD);
    exec("git diff --cached -U0 $filename", $diff_lines, $status);
    $line_num = null;
    foreach ($diff_lines as $line) {
        $prefix = substr($line, 0, 3);
        if (substr($prefix, 0, 2) === '@@') {
            $start_pos = strpos($line, '+');
            $end_pos = strpos($line, ',', $start_pos);
            $line_num = (int) substr($line, $start_pos, $end_pos - $start_pos);
        } elseif (substr($prefix, 0, 1) === '+' && $prefix !== '+++') {
            $added_line_nums[] = $line_num;
            $line_num++;
        }
    }

    foreach ($style_errors as $line_num => $error) {
        if (in_array($line_num, $added_line_nums) !== true) {
            continue;
        }

        foreach ($error as $column => $column_errors) {
            foreach ($column_errors as $error_info) {
                $staged_file_errors[] = $error_info['message'] .
                    " (line $line_num, column $column)";
            }
        }
    }

    // GRIPE This is incredibly un-DRY. Hopefully when I abstract these
    // into somewhere else they get cleaned up.
    foreach ($style_warnings as $line_num => $error) {
        if (in_array($line_num, $added_line_nums) !== true) {
            continue;
        }

        foreach ($error as $column => $column_errors) {
            foreach ($column_errors as $error_info) {
                $staged_file_errors[] = $error_info['message'] .
                    " (line $line_num, column $column)";
            }
        }
    }

    return $staged_file_errors;
}

function main()
{
    $staged_files = get_staged_PHP_files();

    $staged_errors = array();
    foreach ($staged_files as $file) {
        $staged_errors[$file] = get_style_errors($file);
    }

    foreach ($staged_errors as $file => $errors) {
        if (count($errors) > 0) {
            echo "$file has style errors:\n";

            foreach ($errors as $error) {
                echo $error . "\n";
            }

            echo "Commit canceled. Fix style errors and try again.\n";

            exit(1);
        }
    }

    exit(0);
}

main();
