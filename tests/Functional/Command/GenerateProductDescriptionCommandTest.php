<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\AI\Client\AIClientInterface;
use App\AI\DTO\AIResponse;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateProductDescriptionCommandTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('COLUMNS=120');
        $_SERVER['COLUMNS'] = '120';
        $_ENV['COLUMNS'] = '120';
    }

    public function testItGeneratesDescriptionSynchronously(): void
    {
        $aiClientMock = $this->createMock(AIClientInterface::class);
        $aiClientMock->expects($this->once())
            ->method('generateDescription')
            ->willReturn(new AIResponse('Opis wygenerowany dla testowego laptopa.'));

        $commandTester = $this->createCommandTester($aiClientMock);

        $exitCode = $commandTester->execute([
            'name' => 'Laptop Pro',
            'features' => '16GB RAM, SSD 1TB',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Laptop Pro', $output);
        self::assertStringContainsString('Opis wygenerowany dla testowego laptopa.', $output);
    }

    public function testItDispatchesJobAsynchronously(): void
    {
        $commandTester = $this->createCommandTester();

        $exitCode = $commandTester->execute([
            'name' => 'Smartfon Galaxy',
            'features' => 'AMOLED 120Hz',
            '--async' => true,
        ], ['decorated' => false, 'interactive' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Job dispatched successfully', $output);
        self::assertMatchesRegularExpression('/Job ID:\s+[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i', $output);
    }

    public function testItReturnsInvalidWhenArgumentsAreEmpty(): void
    {
        $commandTester = $this->createCommandTester();

        $exitCode = $commandTester->execute([
            'name' => '   ',
            'features' => '   ',
        ], ['decorated' => false, 'interactive' => false]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Product name and features cannot be', $commandTester->getDisplay());
    }

    public function testItReturnsFailureWhenGeneratorThrows(): void
    {
        $aiClientMock = $this->createStub(AIClientInterface::class);
        $aiClientMock->method('generateDescription')
            ->willThrowException(new RuntimeException('AI service offline'));

        $commandTester = $this->createCommandTester($aiClientMock);

        $exitCode = $commandTester->execute([
            'name' => 'Broken Product',
            'features' => 'Some features',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Failed to generate description', $commandTester->getDisplay());
    }

    public function testItFailsWhenAsyncDispatchThrows(): void
    {
        $bus = $this->createStub(\Symfony\Component\Messenger\MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new RuntimeException('Broker unavailable'));

        $commandTester = $this->createCommandTester(messageBus: $bus);
        $exitCode = $commandTester->execute([
            'name' => 'Test Product',
            'features' => 'Test features',
            '--async' => true,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Failed to dispatch job', $commandTester->getDisplay());
    }

    private function createCommandTester(
        ?AIClientInterface $aiClient = null,
        ?\Symfony\Component\Messenger\MessageBusInterface $messageBus = null,
    ): CommandTester {
        $kernel = self::bootKernel();

        if ($aiClient !== null) {
            static::getContainer()->set(AIClientInterface::class, $aiClient);
        }

        if ($messageBus !== null) {
            static::getContainer()->set(\Symfony\Component\Messenger\MessageBusInterface::class, $messageBus);
        }

        $application = new Application($kernel);
        $command = $application->find('app:generate-description');

        return new CommandTester($command);
    }
}
