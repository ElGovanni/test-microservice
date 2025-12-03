<?php

namespace App\Command;

use App\Service\RabbitMqConsumer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:consume-user-events',
    description: 'Consume user.created events from RabbitMQ',
)]
class ConsumeUserEventsCommand extends Command
{
    public function __construct(
        private RabbitMqConsumer $rabbitMqConsumer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting to consume user.created events...');

        try {
            $this->rabbitMqConsumer->consumeUserCreatedEvents();
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
