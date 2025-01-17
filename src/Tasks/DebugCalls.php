<?php

namespace Shift\Cli\Tasks;

use Shift\Cli\Sdk\Contracts\Task;
use Shift\Cli\Sdk\Facades\Comment;
use Shift\Cli\Sdk\Parsers\NikicParser;
use Shift\Cli\Sdk\Traits\FindsFiles;

class DebugCalls implements Task
{
    use FindsFiles;

    public static string $name = 'debug-calls';

    public static string $description = 'Removes calls to common debugging functions';

    public function perform(): int
    {
        $files = $this->findFiles();
        if (empty($files)) {
            return 0;
        }

        $finder = new NikicParser(new \Shift\Cli\Sdk\Parsers\Finders\DebugCalls());
        $failure = false;

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            if (! preg_match('/\b(print_r|var_dump|var_export|dd)\(/', $contents)) {
                continue;
            }

            $instances = $finder->parse($contents);
            if (empty($instances)) {
                continue;
            }

            $failure = true;
            $this->leaveComment($file, $instances);

            foreach (array_reverse($instances) as $instance) {
                $contents = substr_replace(
                    $contents,
                    '',
                    $instance['offset']['start'],
                    $instance['offset']['end'] - $instance['offset']['start'] + 1
                );
            }

            file_put_contents($file, $contents);
        }

        return $failure ? 1 : 0;
    }

    private function leaveComment(string $path, array $calls): void
    {
        $instances = array_map(
            fn ($call) => sprintf('Line %d: contains call to `%s`', $call['line']['start'], $call['function']),
            $calls
        );

        Comment::addComment($path, $instances);
    }
}
