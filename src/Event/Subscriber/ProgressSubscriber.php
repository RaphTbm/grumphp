<?php

namespace GrumPHP\Event\Subscriber;

use GrumPHP\Event\RunnerEvent;
use GrumPHP\Event\RunnerEvents;
use GrumPHP\Event\TaskEvent;
use GrumPHP\Event\TaskEvents;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use ReflectionClass;

class ProgressSubscriber implements EventSubscriberInterface
{
    /**
     * @var ProgressBar
     */
    private $progressBar;

    /**
     * @var string
     */
    private $progressFormat;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @param OutputInterface  $output
     * @param ProgressBar $progressBar
     */
    public function __construct(OutputInterface $output, ProgressBar $progressBar)
    {
        $this->output = $output;
        $this->progressBar = $progressBar ?: new ProgressBar($output);
        $this->progressBar->setOverwrite(false);
        $this->progressFormat = '<fg=yellow>Running task %current%/%max%:</fg=yellow> %message%... ';
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            RunnerEvents::RUNNER_RUN => 'startProgress',
            TaskEvents::TASK_RUN => 'advanceProgress',
            TaskEvents::TASK_COMPLETE => 'onTaskProgress',
            TaskEvents::TASK_FAILED => 'onTaskProgress',
            TaskEvents::TASK_SKIPPED => 'onTaskProgress',
            RunnerEvents::RUNNER_COMPLETE => 'finishProgress',
            RunnerEvents::RUNNER_FAILED => 'finishProgress',
        ];
    }

    /**
     * @param RunnerEvent $event
     */
    public function startProgress(RunnerEvent $event)
    {
        $numberOftasks = $event->getTasks()->count();
        $this->progressBar->setFormat('<fg=yellow>%message%</fg=yellow>');
        $this->progressBar->setMessage('GrumPHP is sniffing your code!');
        $this->progressBar->start($numberOftasks);
    }

    /**
     * @param TaskEvent $event
     */
    public function advanceProgress(TaskEvent $event)
    {
        $task = $event->getTask();
        $taskReflection = new ReflectionClass($task);
        $taskName = $taskReflection->getShortName();
        if (method_exists($task, 'getExtraConfig') && $task->getExtraConfig('label')) {
            $taskName = '[' . $taskName . '] ' . $task->getExtraConfig('label');
        }

        $this->progressBar->setFormat($this->progressFormat);
        $this->progressBar->setMessage($taskName);
        $this->progressBar->advance();
    }

    /**
     * @param TaskEvent $task
     * @param string $event
     */
    public function onTaskProgress(TaskEvent $task, $event)
    {
        switch ($event) {
            case TaskEvents::TASK_COMPLETE:
                $this->output->write('<fg=green>✔</fg=green>');
                break;

            case TaskEvents::TASK_FAILED:
                $this->output->write('<fg=red>✘</fg=red>');
                break;

            case TaskEvents::TASK_SKIPPED:
                $this->output->write('', true);
                $this->output->write('<fg=yellow>Oh no, we hit the windows cmd input limit!</fg=yellow>', true);
                $this->output->write('<fg=yellow>Skipping task...</fg=yellow>');
        }
    }

    /**
     * @param RunnerEvent $runnerEvent
     */
    public function finishProgress(RunnerEvent $runnerEvent)
    {
        if ($this->progressBar->getProgress() !== $this->progressBar->getMaxSteps()) {
            $this->progressBar->setFormat('<fg=red>%message%</fg=red>');
            $this->progressBar->setMessage('Aborted ...');
        }

        $this->progressBar->finish();
        $this->output->writeln('');
    }
}
