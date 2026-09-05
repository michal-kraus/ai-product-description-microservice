<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\GenerateProductDescriptionMessageStatus;
use App\Service\JobStatusManager;
use DomainException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class JobStatusManagerTest extends TestCase
{
    private ArrayAdapter $cache;
    private JobStatusManager $manager;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->manager = new JobStatusManager($this->cache, 3600);
    }

    public function testItCreatesJobWithPendingStatusAndTimestamp(): void
    {
        $jobId = 'job-123';
        $this->manager->createJob($jobId);

        $jobData = $this->manager->getJob($jobId);

        $this->assertNotNull($jobData);
        $this->assertSame(GenerateProductDescriptionMessageStatus::PENDING->value, $jobData['status']);
        $this->assertArrayHasKey('created_at', $jobData);
    }

    public function testItUpdatesJobAndPreservesCreatedAt(): void
    {
        $jobId = 'job-123';
        $this->manager->createJob($jobId);
        $initialJobData = $this->manager->getJob($jobId);
        $this->assertNotNull($initialJobData);
        $initialCreatedAt = $initialJobData['created_at'];

        $this->manager->updateJob($jobId, [
            'status' => GenerateProductDescriptionMessageStatus::PROCESSING->value,
        ]);

        $this->manager->updateJob($jobId, [
            'status' => GenerateProductDescriptionMessageStatus::COMPLETED->value,
            'description' => 'Test description',
        ]);

        $updatedJobData = $this->manager->getJob($jobId);
        $this->assertNotNull($updatedJobData);
        $this->assertSame(GenerateProductDescriptionMessageStatus::COMPLETED->value, $updatedJobData['status']);
        $this->assertSame('Test description', $updatedJobData['description']);
        $this->assertSame($initialCreatedAt, $updatedJobData['created_at']);
        $this->assertArrayHasKey('updated_at', $updatedJobData);
    }

    public function testItThrowsOnInvalidStateTransition(): void
    {
        $jobId = 'job-terminal';
        $this->manager->createJob($jobId);

        $this->manager->updateJob($jobId, [
            'status' => GenerateProductDescriptionMessageStatus::PROCESSING->value,
        ]);
        $this->manager->updateJob($jobId, [
            'status' => GenerateProductDescriptionMessageStatus::COMPLETED->value,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid job status transition from "completed" to "processing"');

        $this->manager->updateJob($jobId, [
            'status' => GenerateProductDescriptionMessageStatus::PROCESSING->value,
        ]);
    }

    public function testItAcceptsEnumInstanceInUpdateJob(): void
    {
        $jobId = 'job-enum';
        $this->manager->createJob($jobId);
        $this->manager->updateJob($jobId, [
            'status' => GenerateProductDescriptionMessageStatus::PROCESSING,
        ]);

        $jobData = $this->manager->getJob($jobId);
        $this->assertNotNull($jobData);
        $this->assertSame(GenerateProductDescriptionMessageStatus::PROCESSING->value, $jobData['status']);
    }

    public function testItReturnsNullForNonExistentJob(): void
    {
        $this->assertNull($this->manager->getJob('non-existent-job-id'));
    }
}
