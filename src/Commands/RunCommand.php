<?php

namespace Shift\Cli\Commands;

use Shift\Cli\Sdk\Contracts\Task;
use Shift\Cli\Sdk\Facades\Comment;
use Shift\Cli\Sdk\Facades\Configuration;
use Shift\Cli\Sdk\Traits\FindsFiles;
use Shift\Cli\Support\TaskManifest;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'run',
    description: 'Run one or more automated tasks',
)]
class RunCommand extends Command
{
    private TaskManifest $taskManifest;

    public function __construct(TaskManifest $taskManifest)
    {
        parent::__construct();

        $this->taskManifest = $taskManifest;
    }

    protected function configure(): void
    {
        $this->addArgument('task', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'The name of the automated task');
        $this->addOption('tasks', mode: InputOption::VALUE_NONE, description: 'List the available tasks');
        $this->addOption('dirty', mode: InputOption::VALUE_NONE, description: 'Scan only dirty files');
        $this->addOption('path', mode: InputOption::VALUE_REQUIRED | InputArgument::OPTIONAL | InputOption::VALUE_IS_ARRAY, description: 'The paths to scan');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('tasks')) {
            $this->listTasks($output);

            return 0;
        }

        $tasks = empty($input->getArgument('task')) ? Configuration::get('tasks', []) : $input->getArgument('task');

        foreach ($tasks as $task) {
            $result = ($this->createTask($this->taskRegistry($task), $input))->perform();
            if ($result !== 0) {
                $output->writeln('<fg=white;bg=red>Failed to run task:</> ' . $task);

                return $result;
            }

            foreach (Comment::flush() as $comment) {
                $this->outputComment($comment, $output);
            }
        }

        return 0;
    }

    private function createTask(string $name, InputInterface $input): Task
    {
        $task = new $name;

        if (in_array(FindsFiles::class, class_uses_recursive($task))) {
            if (! empty($input->getOption('path'))) {
                $task->setPaths($input->getOption('path'));
            }

            if ($input->getOption('dirty')) {
                $task->setDirty(true);
            }
        }

        return $task;
    }

    private function outputComment(\Shift\Cli\Sdk\Models\Comment $comment, OutputInterface $output): void
    {
        $output->writeln($comment->content());

        if ($comment->hasReference()) {
            $output->writeln('Reference: ' . $comment->reference());
        }

        if ($comment->hasPaths()) {
            $output->writeln('');
        }

        foreach ($comment->paths() as $path) {
            $output->writeln('  - ' . $path);
        }
    }

    private function taskRegistry(string $task): string
    {
        $tasks = $this->taskManifest->list();

        if (! isset($tasks[$task])) {
            throw new \InvalidArgumentException('Task not registered: ' . $task);
        }

        return $tasks[$task];
    }

    private function listTasks(OutputInterface $output)
    {
        $output->writeln('<comment>Available tasks:</comment>');

        $tasks = collect($this->taskManifest->list())
            ->map(fn ($fqcn) => ['  <info>' . $fqcn::$name . '</info> ', $fqcn::$description])
            ->all();

        (new Table($output))->setStyle('compact')
            ->setRows($tasks)
            ->render();
    }
}
