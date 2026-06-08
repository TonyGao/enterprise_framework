<?php

namespace App\Command;

use App\Service\Task\TaskScheduler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:scheduler:run',
    description: 'Run due scheduler tasks and dispatch execution messages.',
)]
class RunSchedulerCommand extends Command
{
    public function __construct(private readonly TaskScheduler $scheduler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dispatched = $this->scheduler->run();

        $io->success(sprintf('scheduler dispatched=%d', $dispatched));

        return Command::SUCCESS;
    }
}
