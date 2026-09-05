<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\GenerateProductDescriptionMessageStatus;
use PHPUnit\Framework\TestCase;

class GenerateProductDescriptionMessageStatusTest extends TestCase
{
    public function testTransitionsFromPending(): void
    {
        $status = GenerateProductDescriptionMessageStatus::PENDING;

        $this->assertTrue($status->canTransitionTo(GenerateProductDescriptionMessageStatus::PROCESSING));
        $this->assertTrue($status->canTransitionTo(GenerateProductDescriptionMessageStatus::FAILED));
        $this->assertFalse($status->canTransitionTo(GenerateProductDescriptionMessageStatus::COMPLETED));
        $this->assertFalse($status->canTransitionTo(GenerateProductDescriptionMessageStatus::PENDING));
    }

    public function testTransitionsFromProcessing(): void
    {
        $status = GenerateProductDescriptionMessageStatus::PROCESSING;

        $this->assertTrue($status->canTransitionTo(GenerateProductDescriptionMessageStatus::COMPLETED));
        $this->assertTrue($status->canTransitionTo(GenerateProductDescriptionMessageStatus::FAILED));
        $this->assertTrue($status->canTransitionTo(GenerateProductDescriptionMessageStatus::PROCESSING));
        $this->assertFalse($status->canTransitionTo(GenerateProductDescriptionMessageStatus::PENDING));
    }

    public function testTransitionsFromCompletedAndFailedStates(): void
    {
        $completed = GenerateProductDescriptionMessageStatus::COMPLETED;
        $this->assertFalse($completed->canTransitionTo(GenerateProductDescriptionMessageStatus::PROCESSING));
        $this->assertFalse($completed->canTransitionTo(GenerateProductDescriptionMessageStatus::FAILED));

        $failed = GenerateProductDescriptionMessageStatus::FAILED;
        $this->assertFalse($failed->canTransitionTo(GenerateProductDescriptionMessageStatus::PROCESSING));
        $this->assertFalse($failed->canTransitionTo(GenerateProductDescriptionMessageStatus::COMPLETED));
    }
}
