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
        self::assertStringContainsString('cli_', $output);
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

    private function createCommandTester(?AIClientInterface $aiClient = null): CommandTester
    {
        $kernel = self::bootKernel();

        if ($aiClient !== null) {
            static::getContainer()->set(AIClientInterface::class, $aiClient);
        }

        $application = new Application($kernel);
        $command = $application->find('app:generate-description');

        return new CommandTester($command);
    }
}
