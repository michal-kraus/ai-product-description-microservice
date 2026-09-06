<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\GenerateProductDescriptionMessageStatus;
use App\Service\JobStatusManager;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use ValueError;

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

    #[DataProvider('provideValidTransitions')]
    public function testValidTransitions(
        GenerateProductDescriptionMessageStatus $from,
        GenerateProductDescriptionMessageStatus $to,
    ): void {
        $jobId = 'job-valid-' . $from->value . '-' . $to->value;

        $this->manager->createJob($jobId);
        if ($from !== GenerateProductDescriptionMessageStatus::PENDING) {
            $this->manager->updateJob($jobId, ['status' => GenerateProductDescriptionMessageStatus::PROCESSING->value]);
            if ($from !== GenerateProductDescriptionMessageStatus::PROCESSING) {
                $this->manager->updateJob($jobId, ['status' => $from->value]);
            }
        }

        $this->manager->updateJob($jobId, ['status' => $to->value]);

        $job = $this->manager->getJob($jobId);
        $this->assertNotNull($job);
        $this->assertSame($to->value, $job['status']);
    }

    /**
     * @return array<string, array{GenerateProductDescriptionMessageStatus, GenerateProductDescriptionMessageStatus}>
     */
    public static function provideValidTransitions(): array
    {
        return [
            'PENDING to PROCESSING' => [
                GenerateProductDescriptionMessageStatus::PENDING,
                GenerateProductDescriptionMessageStatus::PROCESSING,
            ],
            'PENDING to FAILED' => [
                GenerateProductDescriptionMessageStatus::PENDING,
                GenerateProductDescriptionMessageStatus::FAILED,
            ],
            'PROCESSING to COMPLETED' => [
                GenerateProductDescriptionMessageStatus::PROCESSING,
                GenerateProductDescriptionMessageStatus::COMPLETED,
            ],
            'PROCESSING to FAILED' => [
                GenerateProductDescriptionMessageStatus::PROCESSING,
                GenerateProductDescriptionMessageStatus::FAILED,
            ],
            'PROCESSING to PROCESSING (retry idempotent)' => [
                GenerateProductDescriptionMessageStatus::PROCESSING,
                GenerateProductDescriptionMessageStatus::PROCESSING,
            ],
        ];
    }

    #[DataProvider('provideInvalidTransitions')]
    public function testInvalidTransitions(
        GenerateProductDescriptionMessageStatus $from,
        GenerateProductDescriptionMessageStatus $to,
    ): void {
        $jobId = 'job-invalid-' . $from->value . '-' . $to->value;

        $this->manager->createJob($jobId);
        if ($from !== GenerateProductDescriptionMessageStatus::PENDING) {
            $this->manager->updateJob($jobId, ['status' => GenerateProductDescriptionMessageStatus::PROCESSING->value]);
            if ($from !== GenerateProductDescriptionMessageStatus::PROCESSING) {
                $this->manager->updateJob($jobId, ['status' => $from->value]);
            }
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(\sprintf('Invalid job status transition from "%s" to "%s".', $from->value, $to->value));

        $this->manager->updateJob($jobId, ['status' => $to->value]);
    }

    /**
     * @return array<string, array{GenerateProductDescriptionMessageStatus, GenerateProductDescriptionMessageStatus}>
     */
    public static function provideInvalidTransitions(): array
    {
        return [
            'PENDING to COMPLETED' => [
                GenerateProductDescriptionMessageStatus::PENDING,
                GenerateProductDescriptionMessageStatus::COMPLETED,
            ],
            'PENDING to PENDING' => [
                GenerateProductDescriptionMessageStatus::PENDING,
                GenerateProductDescriptionMessageStatus::PENDING,
            ],
            'PROCESSING to PENDING' => [
                GenerateProductDescriptionMessageStatus::PROCESSING,
                GenerateProductDescriptionMessageStatus::PENDING,
            ],
            'COMPLETED to PENDING' => [
                GenerateProductDescriptionMessageStatus::COMPLETED,
                GenerateProductDescriptionMessageStatus::PENDING,
            ],
            'COMPLETED to PROCESSING' => [
                GenerateProductDescriptionMessageStatus::COMPLETED,
                GenerateProductDescriptionMessageStatus::PROCESSING,
            ],
            'COMPLETED to FAILED' => [
                GenerateProductDescriptionMessageStatus::COMPLETED,
                GenerateProductDescriptionMessageStatus::FAILED,
            ],
            'COMPLETED to COMPLETED' => [
                GenerateProductDescriptionMessageStatus::COMPLETED,
                GenerateProductDescriptionMessageStatus::COMPLETED,
            ],
            'FAILED to PENDING' => [
                GenerateProductDescriptionMessageStatus::FAILED,
                GenerateProductDescriptionMessageStatus::PENDING,
            ],
            'FAILED to PROCESSING' => [
                GenerateProductDescriptionMessageStatus::FAILED,
                GenerateProductDescriptionMessageStatus::PROCESSING,
            ],
            'FAILED to COMPLETED' => [
                GenerateProductDescriptionMessageStatus::FAILED,
                GenerateProductDescriptionMessageStatus::COMPLETED,
            ],
            'FAILED to FAILED' => [
                GenerateProductDescriptionMessageStatus::FAILED,
                GenerateProductDescriptionMessageStatus::FAILED,
            ],
        ];
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

    public function testItThrowsValueErrorOnInvalidStatusString(): void
    {
        $jobId = 'job-invalid-status';
        $this->manager->createJob($jobId);

        $this->expectException(ValueError::class);

        $this->manager->updateJob($jobId, [
            'status' => 'non_existent_status',
        ]);
    }

    public function testItAcquiresAndReleasesLockDuringUpdateJob(): void
    {
        $lock = $this->createMock(\Symfony\Component\Lock\SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(true)
            ->willReturn(true);
        $lock->expects($this->once())
            ->method('release');

        $lockFactory = $this->createMock(\Symfony\Component\Lock\LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with(JobStatusManager::KEY_PREFIX . 'locked-job', JobStatusManager::LOCK_TTL)
            ->willReturn($lock);

        $manager = new JobStatusManager($this->cache, 3600, $lockFactory);
        $manager->createJob('locked-job');

        $manager->updateJob('locked-job', [
            'status' => GenerateProductDescriptionMessageStatus::PROCESSING->value,
        ]);

        $job = $manager->getJob('locked-job');
        $this->assertNotNull($job);
        $this->assertSame(GenerateProductDescriptionMessageStatus::PROCESSING->value, $job['status']);
    }

    public function testItReleasesLockWhenExceptionThrownInUpdateJob(): void
    {
        $lock = $this->createMock(\Symfony\Component\Lock\SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(true)
            ->willReturn(true);
        $lock->expects($this->once())
            ->method('release');

        $lockFactory = $this->createMock(\Symfony\Component\Lock\LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->willReturn($lock);

        $manager = new JobStatusManager($this->cache, 3600, $lockFactory);
        $manager->createJob('locked-job-fail');

        $this->expectException(DomainException::class);

        $manager->updateJob('locked-job-fail', [
            'status' => GenerateProductDescriptionMessageStatus::COMPLETED->value,
        ]);
    }
}
