<?php

declare(strict_types=1);

namespace Psl\Shell;

use Psl\Dict;
use Psl\Env;
use Psl\Str;
use Psl\Vec;

use function fclose;
use function is_dir;
use function is_resource;
use function proc_close;
use function proc_open;
use function stream_get_contents;

/**
 * Execute an external program.
 *
 * @param string $command The command to execute.
 * @param list<string> $arguments The command arguments listed as separate entries.
 * @param string $working_directory The initial working directory for the command.
 *                                  This must be an absolute directory path, or null if you want to
 *                                  use the default value ( the current directory )
 * @param array<string, string> $environment A dict with the environment variables for the command that
 *                                           will be run.
 * @param bool $escape_arguments If set to true ( default ), all $arguments will be escaped using `escape_argument`.
 *
 * @psalm-taint-sink shell $command
 *
 * @throws Exception\FailedExecutionException In case the command resulted in an exit code other than 0.
 * @throws Exception\PossibleAttackException In case the command being run is suspicious ( e.g: contains NULL byte ).
 * @throws Exception\RuntimeException In case $working_directory doesn't exist, or unable to create a new process.
 */
function execute(
    string $command,
    array $arguments = [],
    ?string $working_directory = null,
    array $environment = [],
    bool $escape_arguments = true
): string {
    if ($escape_arguments) {
        $arguments = Vec\map($arguments, static fn(string $argument): string => escape_argument($argument));
    }

    $commandline = Str\join([$command, ...$arguments], ' ');

    /** @psalm-suppress MissingThrowsDocblock - safe ( $offset is within-of-bounds ) */
    if (Str\contains($commandline, "\0")) {
        throw new Exception\PossibleAttackException('NULL byte detected.');
    }

    $descriptor = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $environment = Dict\merge(Env\get_vars(), $environment);
    $working_directory = $working_directory ?? Env\current_dir();
    if (!is_dir($working_directory)) {
        throw new Exception\RuntimeException('$working_directory does not exist.');
    }

    $process = proc_open($commandline, $descriptor, $pipes, $working_directory, $environment);
    // @codeCoverageIgnoreStart
    // not sure how to replicate this, but it can happen \_o.o_/
    if (!is_resource($process)) {
        throw new Exception\RuntimeException('Failed to open a new process.');
    }
    // @codeCoverageIgnoreEnd

    $stdout_content = stream_get_contents($pipes[1]);
    $stderr_content = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    $code = proc_close($process);
    if ($code !== 0) {
        throw new Exception\FailedExecutionException($commandline, $stdout_content, $stderr_content, $code);
    }

    return $stdout_content;
}
