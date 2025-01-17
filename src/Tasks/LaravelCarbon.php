<?php

namespace Shift\Cli\Tasks;

use Shift\Cli\Sdk\Contracts\Task;
use Shift\Cli\Sdk\Traits\FindsFiles;

class LaravelCarbon implements Task
{
    use FindsFiles;

    public static string $name = 'laravel-carbon';

    public static string $description = 'Converts `Carbon\Carbon` imports to `Illuminate\Support\Carbon`';

    public function perform(): int
    {
        foreach ($this->findFiles() as $path) {
            $contents = file_get_contents($path);
            $contents = preg_replace('/Carbon\\\\Carbon(?![\w\\\\])/', 'Illuminate\\Support\\Carbon', $contents);
            file_put_contents($path, $contents);
        }

        return 0;
    }
}
