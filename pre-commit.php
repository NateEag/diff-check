#! /usr/bin/env php
<?php

/* @brief Git pre-commit hook to enforce coding style.
 *
 * It uses the style checker of your choice, git diff, and a regex or two to
 * make sure the new commit does not add any style violations.
 *
 * Unlike many style checker hooks, it only checks the lines being added for
 * style errors. This makes it ideal for use with large, ugly codebases, as it
 * lets you enforce a coding style without forcing devs to fix the bulk of an
 * ugly file in order to pass style checks.
 */

/* @brief Return an array of files that have been staged for the current commit.
 *
 * @param array $file_extensions
 * Array of filename extensions that indicate a file should be checked.
 */
function get_staged_files($file_extensions)
{
    // Get array of new/modified files in commit. We do *not* want deleted
    // files, since there's no sense in checking style of deleted files.
    // The --diff-filter option gets Added, Copied, and Modified files.
    $staged_files = array();
    $cmd = 'git diff --cached --name-only --diff-filter=ACM';
    exec($cmd, $staged_files, $status);
    if ($status !== 0) {
        throw new RuntimeException("$cmd exit code was $status!");
    }

    $checked_files = array();
    foreach ($staged_files as $filename) {
        foreach ($file_extensions as $ext) {
            if (substr($filename, -strlen($ext)) === $ext) {
                $checked_files[] = $filename;

                continue;
            }
        }
    }

    return $checked_files;
}

/* @brief Return an array of line numbers added to $filename in the current
 * commit.
 */
function get_staged_line_numbers($filename)
{
    $added_line_nums = array();
    $diff_lines = array();
    exec("git diff --cached -U0 $filename", $diff_lines, $status);

    $line_num = null;
    foreach ($diff_lines as $line) {
        $prefix = substr($line, 0, 3);
        if (substr($prefix, 0, 2) === '@@') {
            $start_pos = strpos($line, '+');
            $end_pos = strpos($line, ' ', $start_pos);
            $comma_pos = strpos($line, ',', $start_pos);
            if ($comma_pos !== false) {
                $end_pos = $comma_pos;
            }

            $line_num = (int) substr($line, $start_pos, $end_pos - $start_pos);
        } elseif (substr($prefix, 0, 1) === '+' && $prefix !== '+++') {
            $added_line_nums[] = $line_num;
            $line_num++;
        }
    }

    return $added_line_nums;
}

function filter_errors_by_cur_diff($errors_in_staged_files)
{
    $staged_errors = array();
    foreach ($errors_in_staged_files as $filename => $file_errors) {
        $staged_line_nums = get_staged_line_numbers($filename);

        $staged_file_errors = array();

        foreach ($file_errors as $line_num => $errors) {
            if (in_array($line_num, $staged_line_nums)) {
                $staged_file_errors[$line_num] = $errors;
            }
        }

        $staged_errors[$filename] = $staged_file_errors;
    }

    return $staged_errors;
}

/* @brief Return an array of messages outlining style errors in $filename.
 *
 * @param string $filename
 * cwd-relative path to the file to be scanned for errors.
 *
 * @param string $cmd
 * The command to be run on $filename.
 *
 * @param string $line_match_regex
 * A poorly-named regular expression that is run on each line of output from
 * $cmd. If it matches, then the first captured group is used as the line
 * number the message applies to. If it does not match, then the line is
 * discarded.
 */
function get_style_errors($filename, $cmd, $line_match_regex)
{
    $staged_file_errors = array();

    // Get a snapshot of the file as it will appear after commiting.
    // The ':filename' arg to git show shows the file as it currently stands in
    // the index.
    $tmp_dir = sys_get_temp_dir();
    $cur_time = time();
    // GRIPE You'd think this should be DIRECTORY_SEPARATOR, but since Git Bash
    // uses '/', this is actually more portable.
    $tmp_filename = str_replace('/', '-', $filename);
    $tmp_file_path = $tmp_dir . DIRECTORY_SEPARATOR . $cur_time . '.' . $tmp_filename;

    $filename_arg = ":$filename";
    $make_tmp_file_cmd = 'git show ' . escapeshellarg($filename_arg) . ' > ' .
        escapeshellarg($tmp_file_path);
    exec($make_tmp_file_cmd);

    $output = array();
    exec($cmd . ' ' . $tmp_file_path, $output);

    foreach ($output as $line) {
        $matches = array();
        $result = preg_match($line_match_regex, $line, $matches);
        if ($result === 1) {
            $line_num = (int) $matches[1];

            if (! isset($staged_file_errors[$line_num])) {
                $staged_file_errors[$line_num] = array();
            }

            $staged_file_errors[$line_num][] = $line;
        }
    }

    unlink($tmp_file_path);

    return $staged_file_errors;
}

/* @brief Return array of end-user config data for this script.
 *
 * Currently assumes config file lives in the same dir as this script, but that
 * might be dumb. Consider adding a command-line flag for specifying
 * the path.
 */
function load_config()
{
    $config_file_path = __FILE__ . '.conf';
    $json = file_get_contents($config_file_path);

    return json_decode($json, true);
}

function main()
{
    $config = load_config();

    $cmd = $config['command'];
    $regex = $config['error_line_num_regex'];
    $checked_file_extensions = $config['checked_file_extensions'];

    $errs_in_staged_files = array();
    $staged_files = get_staged_files($checked_file_extensions);
    foreach ($staged_files as $file) {
        $errs_in_staged_files[$file] = get_style_errors($file, $cmd, $regex);
    }

    // TODO Long-term I hope to make this a more generic diff engine, so there
    // can be pre-commit and pre-receive variations on this script, rather than
    // a function that operates by side effect.
    $staged_errors = filter_errors_by_cur_diff($errs_in_staged_files);

    $cancel_commit = false;
    $msg_regex = isset($config['msg_regex']) ?
        $config['msg_regex']:
        '/(.*)/'; // By default, capture the whole message.
    if (isset($config['msg_regex'])) {
        $msg_regex = $config['msg_regex'];
    }

    $err_msgs = array();
    foreach ($staged_errors as $file => $file_errors) {
        if (count($file_errors) > 0) {
            $file_err_msgs = array("$file has style errors:");
            foreach ($file_errors as $line_num => $line_errors) {
                foreach ($line_errors as $error) {
                    $matches = array();
                    preg_match($msg_regex, $error, $matches);
                    $file_err_msgs[] = "Line $line_num: " . $matches[1];
                }
            }
            $err_msgs[] = implode("\n", $file_err_msgs);
        }

        $file_err_msg = '';
        $file_err_msgs = array();
    }

    if (count($err_msgs) > 0) {
        echo "Commit canceled. Fix style errors and try again.\n";

        echo implode("\n\n", $err_msgs);

        exit(1);
    }


    exit(0);
}

main();
