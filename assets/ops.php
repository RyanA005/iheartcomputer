<?php

/**
 * Admin operations: git status + fast-forward pull. Fixed commands only.
 */

function ops_root(): string
{
    return dirname(__DIR__);
}

function ops_git_bin(): string
{
    foreach (['/usr/bin/git', '/usr/local/bin/git'] as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }
    return 'git';
}

function ops_pull_wrapper(): string
{
    return '/usr/local/sbin/iheartcomputer-pull';
}

function ops_github_repo(): string
{
    return 'RyanA005/iheartcomputer';
}

/**
 * Run a fixed argv list with no shell. $argv[0] must be an executable path or
 * a known binary name (git/sudo).
 *
 * @param list<string> $argv
 * @return array{ok: bool, code: int, output: string}
 */
function ops_exec(array $argv, string $cwd, int $timeoutSec = 25): array
{
    $fail = static fn(string $msg, int $code = 1): array => [
        'ok' => false,
        'code' => $code,
        'output' => $msg,
    ];

    if ($argv === []) {
        return $fail('empty command');
    }

    $bin = $argv[0];
    $allowedBins = [ops_git_bin(), 'git', 'sudo', '/usr/bin/sudo', '/usr/bin/git', '/usr/local/bin/git'];
    if (!in_array($bin, $allowedBins, true)) {
        return $fail('refused: unexpected binary');
    }

    foreach ($argv as $i => $part) {
        if (!is_string($part) || $part === '' || str_contains($part, "\0")) {
            return $fail('refused: bad argument');
        }
        if ($i > 0 && ($part[0] === '-' || $part[0] === '/') && $bin === 'sudo') {
            if ($part !== '-n' && $part !== ops_pull_wrapper()) {
                return $fail('refused: unexpected sudo argument');
            }
        }
    }

    if (!function_exists('proc_open')) {
        return $fail('proc_open is disabled on this PHP');
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $env = [];
    foreach (['PATH', 'HOME', 'USER', 'LOGNAME', 'SHELL', 'SSH_AUTH_SOCK', 'SSH_AGENT_PID', 'XDG_RUNTIME_DIR', 'LANG', 'TERM'] as $key) {
        $val = getenv($key);
        if (is_string($val) && $val !== '') {
            $env[$key] = $val;
        }
    }
    if (!isset($env['PATH'])) {
        $env['PATH'] = '/usr/local/bin:/usr/bin:/bin';
    }
    $env['GIT_TERMINAL_PROMPT'] = '0';
    $env['GIT_OPTIONAL_LOCKS'] = '0';
    $env['LC_ALL'] = 'C';

    $pipes = [];
    $proc = @proc_open($argv, $descriptors, $pipes, $cwd, $env);
    if (!is_resource($proc)) {
        return $fail('could not start process');
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $out = '';
    $start = microtime(true);
    $timedOut = false;

    while (true) {
        $out .= (string) stream_get_contents($pipes[1]);
        $out .= (string) stream_get_contents($pipes[2]);
        $status = proc_get_status($proc);
        if (empty($status['running'])) {
            break;
        }
        if ((microtime(true) - $start) > $timeoutSec) {
            $timedOut = true;
            @proc_terminate($proc, 15);
            usleep(150000);
            $st = proc_get_status($proc);
            if (!empty($st['running'])) {
                @proc_terminate($proc, 9);
            }
            break;
        }
        usleep(40000);
    }

    $out .= (string) stream_get_contents($pipes[1]);
    $out .= (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    $out = trim($out);
    if ($timedOut) {
        return [
            'ok' => false,
            'code' => 124,
            'output' => trim($out . "\n") . 'timed out after ' . $timeoutSec . 's',
        ];
    }

    return [
        'ok' => $code === 0,
        'code' => (int) $code,
        'output' => $out,
    ];
}

/**
 * @param list<string> $gitArgv arguments after the git binary
 * @return array{ok: bool, code: int, output: string}
 */
function ops_git(array $gitArgv, string $cwd, int $timeoutSec = 25): array
{
    return ops_exec(array_merge([ops_git_bin()], $gitArgv), $cwd, $timeoutSec);
}

function ops_explain_git_error(string $output): string
{
    $low = strtolower($output);
    if (str_contains($low, 'dubious ownership') || str_contains($low, 'safe.directory')) {
        return 'git refuses this checkout (dubious ownership). Add a safe.directory entry for the site path, or make the php-fpm user own the checkout.';
    }
    if (str_contains($low, 'host key verification failed') || str_contains($low, 'no ed25519 host key')) {
        return 'cannot reach GitHub over SSH: github.com is not in this user\'s known_hosts (php-fpm user has no home/ssh).';
    }
    if (str_contains($low, 'permission denied (publickey)') || str_contains($low, 'could not read from remote')) {
        return 'cannot authenticate to GitHub as this user (no deploy key / ssh agent).';
    }
    if (str_contains($low, 'permission denied') || str_contains($low, 'cannot lock') || str_contains($low, 'unable to write')) {
        return 'this user cannot write .git or the working tree.';
    }
    if (str_contains($low, 'not a git repository')) {
        return 'this directory is not a git checkout.';
    }
    if (str_contains($low, 'not possible to fast-forward') || str_contains($low, 'diverging')) {
        return 'fast-forward only: local main and origin/main have diverged.';
    }
    return '';
}

function ops_commit_url(string $hash): string
{
    if (!preg_match('/^[0-9a-f]{7,40}$/i', $hash)) {
        return '';
    }
    return 'https://github.com/' . ops_github_repo() . '/commit/' . $hash;
}

function ops_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    if ($bytes < 1024 * 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }
    return number_format($bytes / (1024 * 1024 * 1024), 1) . ' GB';
}

function ops_format_uptime(float $seconds): string
{
    $seconds = (int) $seconds;
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $mins = intdiv($seconds % 3600, 60);
    $parts = [];
    if ($days > 0) {
        $parts[] = $days . 'd';
    }
    if ($hours > 0 || $days > 0) {
        $parts[] = $hours . 'h';
    }
    $parts[] = $mins . 'm';
    return implode(' ', $parts);
}

/**
 * @return array<string, mixed>
 */
function ops_status(string $root = '', bool $fetch = true): array
{
    $root = $root !== '' ? $root : ops_root();
    $git = [
        'ok' => false,
        'error' => '',
        'hash' => '',
        'hash_full' => '',
        'subject' => '',
        'author' => '',
        'date' => '',
        'date_raw' => '',
        'branch' => '',
        'dirty' => false,
        'dirty_summary' => '',
        'ahead' => null,
        'behind' => null,
        'fetched' => false,
        'fetch_error' => '',
        'on_main' => false,
        'url' => '',
    ];

    $rev = ops_git(['rev-parse', '--is-inside-work-tree'], $root, 8);
    if (!$rev['ok'] || trim($rev['output']) !== 'true') {
        $git['error'] = ops_explain_git_error($rev['output']) ?: ('not a git checkout: ' . $rev['output']);
        return ops_status_wrap($root, $git);
    }

    $branch = ops_git(['rev-parse', '--abbrev-ref', 'HEAD'], $root, 8);
    $git['branch'] = $branch['ok'] ? trim($branch['output']) : '';
    $git['on_main'] = $git['branch'] === 'main';

    $full = ops_git(['rev-parse', 'HEAD'], $root, 8);
    if ($full['ok']) {
        $git['hash_full'] = trim($full['output']);
        $git['hash'] = substr($git['hash_full'], 0, 7);
        $git['url'] = ops_commit_url($git['hash_full']);
    }

    $meta = ops_git(['log', '-1', '--format=%s%n%an%n%cI'], $root, 8);
    if ($meta['ok']) {
        $parts = explode("\n", $meta['output'], 3);
        $git['subject'] = $parts[0] ?? '';
        $git['author'] = $parts[1] ?? '';
        $git['date_raw'] = trim($parts[2] ?? '');
        if ($git['date_raw'] !== '') {
            try {
                $git['date'] = (new DateTimeImmutable($git['date_raw']))
                    ->setTimezone(new DateTimeZone('America/New_York'))
                    ->format('Y-m-d H:i T');
            } catch (Throwable $e) {
                $git['date'] = $git['date_raw'];
            }
        }
    }

    $dirty = ops_git(['status', '--porcelain=v1', '-uno'], $root, 8);
    if ($dirty['ok']) {
        $git['dirty'] = trim($dirty['output']) !== '';
        $git['dirty_summary'] = trim($dirty['output']);
    } else {
        $git['error'] = ops_explain_git_error($dirty['output']) ?: $dirty['output'];
    }

    if ($fetch) {
        $fetched = ops_git(['fetch', '--prune', 'origin'], $root, 20);
        $git['fetched'] = $fetched['ok'];
        if (!$fetched['ok']) {
            $git['fetch_error'] = ops_explain_git_error($fetched['output']) ?: $fetched['output'];
        }
    }

    $counts = ops_git(['rev-list', '--left-right', '--count', 'HEAD...origin/main'], $root, 8);
    if ($counts['ok'] && preg_match('/^(\d+)\s+(\d+)/', trim($counts['output']), $m) === 1) {
        $git['ahead'] = (int) $m[1];
        $git['behind'] = (int) $m[2];
    }

    $git['ok'] = $git['hash'] !== '';
    return ops_status_wrap($root, $git);
}

/**
 * @param array<string, mixed> $git
 * @return array<string, mixed>
 */
function ops_status_wrap(string $root, array $git): array
{
    $uptime = '';
    $uptimeRaw = @file_get_contents('/proc/uptime');
    if (is_string($uptimeRaw) && preg_match('/^([0-9.]+)/', $uptimeRaw, $m) === 1) {
        $uptime = ops_format_uptime((float) $m[1]);
    }

    $diskFree = @disk_free_space($root);
    $diskTotal = @disk_total_space($root);
    $hitsPath = function_exists('hits_db_path') ? hits_db_path() : ($root . '/data/hits.sqlite');
    $hitsSize = is_file($hitsPath) ? filesize($hitsPath) : false;

    $user = get_current_user();
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $pw = posix_getpwuid(posix_geteuid());
        if (is_array($pw) && !empty($pw['name'])) {
            $user = (string) $pw['name'];
        }
    }

    $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
    $loadStr = '';
    if (is_array($load) && isset($load[0], $load[1], $load[2])) {
        $loadStr = number_format((float) $load[0], 2) . ' / '
            . number_format((float) $load[1], 2) . ' / '
            . number_format((float) $load[2], 2);
    }

    $wrapper = ops_pull_wrapper();

    return [
        'git' => $git,
        'host' => gethostname() ?: '',
        'user' => $user,
        'php' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'uptime' => $uptime,
        'load' => $loadStr,
        'disk_free' => is_int($diskFree) || is_float($diskFree) ? ops_format_bytes((int) $diskFree) : '',
        'disk_total' => is_int($diskTotal) || is_float($diskTotal) ? ops_format_bytes((int) $diskTotal) : '',
        'hits_size' => $hitsSize === false ? '' : ops_format_bytes((int) $hitsSize),
        'repo' => $root,
        'wrapper' => is_file($wrapper) && is_executable($wrapper),
        'wrapper_path' => $wrapper,
    ];
}

/**
 * @return array{ok: bool, from: string, to: string, output: string, reason: string}
 */
function ops_pull(string $root = ''): array
{
    $root = $root !== '' ? $root : ops_root();
    $empty = [
        'ok' => false,
        'from' => '',
        'to' => '',
        'output' => '',
        'reason' => '',
    ];

    $head = ops_git(['rev-parse', 'HEAD'], $root, 8);
    $from = $head['ok'] ? trim($head['output']) : '';
    $empty['from'] = $from;

    $branch = ops_git(['rev-parse', '--abbrev-ref', 'HEAD'], $root, 8);
    if (!$branch['ok'] || trim($branch['output']) !== 'main') {
        $empty['reason'] = 'refusing to pull: HEAD is not main (' . trim($branch['output']) . ').';
        $empty['output'] = $empty['reason'];
        return $empty;
    }

    $dirty = ops_git(['status', '--porcelain=v1', '-uno'], $root, 8);
    if (!$dirty['ok']) {
        $empty['reason'] = ops_explain_git_error($dirty['output']) ?: $dirty['output'];
        $empty['output'] = $dirty['output'];
        return $empty;
    }
    if (trim($dirty['output']) !== '') {
        $empty['reason'] = 'refusing to pull: working tree has local changes (fast-forward would be unsafe).';
        $empty['output'] = $empty['reason'] . "\n" . trim($dirty['output']);
        return $empty;
    }

    $lines = [];
    $wrapper = ops_pull_wrapper();
    $useWrapper = is_file($wrapper) && is_executable($wrapper);

    if ($useWrapper) {
        $lines[] = '$ sudo -n ' . $wrapper;
        $run = ops_exec(['sudo', '-n', $wrapper], $root, 60);
        $lines[] = $run['output'] !== '' ? $run['output'] : '(no output)';
        if (!$run['ok']) {
            $reason = ops_explain_git_error($run['output']);
            if ($reason === '') {
                $reason = 'deploy wrapper failed (is sudoers installed for ' . $wrapper . '?).';
            }
            $output = implode("\n", $lines);
            $to = $from;
            $now = ops_git(['rev-parse', 'HEAD'], $root, 8);
            if ($now['ok']) {
                $to = trim($now['output']);
            }
            return [
                'ok' => false,
                'from' => $from,
                'to' => $to,
                'output' => $output,
                'reason' => $reason,
            ];
        }
    } else {
        $lines[] = '$ git fetch origin';
        $fetch = ops_git(['fetch', 'origin'], $root, 30);
        $lines[] = $fetch['output'] !== '' ? $fetch['output'] : '(no output)';
        if (!$fetch['ok']) {
            $reason = ops_explain_git_error($fetch['output']) ?: 'git fetch failed.';
            $output = implode("\n", $lines);
            return [
                'ok' => false,
                'from' => $from,
                'to' => $from,
                'output' => $output,
                'reason' => $reason,
            ];
        }

        $lines[] = '$ git pull --ff-only origin main';
        $pull = ops_git(['pull', '--ff-only', 'origin', 'main'], $root, 30);
        $lines[] = $pull['output'] !== '' ? $pull['output'] : '(no output)';
        if (!$pull['ok']) {
            $reason = ops_explain_git_error($pull['output']) ?: 'fast-forward pull failed.';
            $output = implode("\n", $lines);
            $to = $from;
            $now = ops_git(['rev-parse', 'HEAD'], $root, 8);
            if ($now['ok']) {
                $to = trim($now['output']);
            }
            return [
                'ok' => false,
                'from' => $from,
                'to' => $to,
                'output' => $output,
                'reason' => $reason,
            ];
        }
    }

    $to = $from;
    $now = ops_git(['rev-parse', 'HEAD'], $root, 8);
    if ($now['ok']) {
        $to = trim($now['output']);
    }
    $output = implode("\n", $lines);
    return [
        'ok' => true,
        'from' => $from,
        'to' => $to,
        'output' => $output,
        'reason' => '',
    ];
}
