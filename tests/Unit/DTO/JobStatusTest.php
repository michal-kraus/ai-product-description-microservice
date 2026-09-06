<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\JobStatus;
use App\Enum\GenerateProductDescriptionMessageStatus;
use PHPUnit\Framework\TestCase;

final class JobStatusTest extends TestCase
{
    public function testItInstantiatesWithRequiredProperties(): void
    {
        $status = new JobStatus(
            jobId: 'job-123',
            status: GenerateProductDescriptionMessageStatus::PENDING,
        );

        $this->assertSame('job-123', $status->jobId);
        $this->assertSame(GenerateProductDescriptionMessageStatus::PENDING, $status->status);
        $this->assertNull($status->description);
        $this->assertNull($status->error);
        $this->assertNull($status->createdAt);
        $this->assertNull($status->updatedAt);
        $this->assertSame([], $status->extra);
    }

    public function testItCreatesFromArrayWithFullData(): void
    {
        $data = [
            'status' => 'completed',
            'description' => 'Great product description',
            'error' => null,
            'created_at' => '2026-09-06T12:00:00+00:00',
            'updated_at' => '2026-09-06T12:01:00+00:00',
            'custom_field' => 'custom_val',
        ];

        $status = JobStatus::fromArray('job-456', $data);

        $this->assertSame('job-456', $status->jobId);
        $this->assertSame(GenerateProductDescriptionMessageStatus::COMPLETED, $status->status);
        $this->assertSame('Great product description', $status->description);
        $this->assertNull($status->error);
        $this->assertSame('2026-09-06T12:00:00+00:00', $status->createdAt);
        $this->assertSame('2026-09-06T12:01:00+00:00', $status->updatedAt);
        $this->assertSame('custom_val', $status->extra['custom_field']);
    }

    public function testItCreatesFromArrayWithMinimalAndInvalidStatusData(): void
    {
        $status = JobStatus::fromArray('job-789', [
            'status' => 'non_existent_status_value',
        ]);

        $this->assertSame(GenerateProductDescriptionMessageStatus::PENDING, $status->status);
        $this->assertNull($status->description);
        $this->assertNull($status->error);
    }

    public function testItConvertsToArray(): void
    {
        $status = new JobStatus(
            jobId: 'job-999',
            status: GenerateProductDescriptionMessageStatus::FAILED,
            description: null,
            error: 'Fatal AI error',
            createdAt: '2026-09-06T12:00:00+00:00',
            updatedAt: '2026-09-06T12:02:00+00:00',
            extra: ['foo' => 'bar'],
        );

        $array = $status->toArray();

        $this->assertSame('failed', $array['status']);
        $this->assertNull($array['description']);
        $this->assertSame('Fatal AI error', $array['error']);
        $this->assertSame('2026-09-06T12:00:00+00:00', $array['created_at']);
        $this->assertSame('2026-09-06T12:02:00+00:00', $array['updated_at']);
        $this->assertSame('bar', $array['foo']);
    }
}
