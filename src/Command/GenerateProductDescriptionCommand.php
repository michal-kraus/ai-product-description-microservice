<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\GenerateProductDescriptionMessageStatus;
use App\Message\GenerateProductDescriptionMessage;
use App\Service\JobStatusManager;
use App\Service\ProductDescriptionGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

#[AsCommand(
    name: 'app:generate-description',
    description: 'Generates an e-commerce product description using AI (sync or async via Messenger)',
)]
final class GenerateProductDescriptionCommand extends Command
{
    public function __construct(
        private readonly ProductDescriptionGenerator $generator,
        private readonly MessageBusInterface $messageBus,
        private readonly JobStatusManager $jobStatusManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Product name')
            ->addArgument('features', InputArgument::REQUIRED, 'Product features (comma-separated)')
            ->addOption('async', 'a', InputOption::VALUE_NONE, 'Dispatch job asynchronously');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = trim((string) $input->getArgument('name'));
        $features = trim((string) $input->getArgument('features'));
        $isAsync = (bool) $input->getOption('async');

        if ($name === '' || $features === '') {
            $io->error('Product name and features cannot be empty.');

            return Command::INVALID;
        }

        $io->title('AI Product Description Generator');
        $io->definitionList(
            ['Product Name' => $name],
            ['Features' => $features],
            ['Mode' => $isAsync ? 'Asynchronous (Messenger queue)' : 'Synchronous'],
        );

        if ($isAsync) {
            $jobId = Uuid::v7()->toRfc4122();
            $this->jobStatusManager->createJob($jobId);

            try {
                $this->messageBus->dispatch(new GenerateProductDescriptionMessage($jobId, $name, $features));
            } catch (Throwable $e) {
                $this->jobStatusManager->updateJob($jobId, [
                    'status' => GenerateProductDescriptionMessageStatus::FAILED->value,
                    'error' => 'Unable to dispatch job.',
                ]);
                $io->error(\sprintf('Failed to dispatch job: %s', $e->getMessage()));

                return Command::FAILURE;
            }

            $io->success(\sprintf('Job dispatched successfully! Job ID: %s', $jobId));
            $io->note(\sprintf('Check status via API: GET /product/descriptions/async/%s', $jobId));

            return Command::SUCCESS;
        }

        $io->section('Generating description...');

        try {
            $description = $this->generator->generate($name, $features);
            $io->success('Description generated successfully:');
            $io->writeln($description);
            $io->newLine();

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error(\sprintf('Failed to generate description: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
