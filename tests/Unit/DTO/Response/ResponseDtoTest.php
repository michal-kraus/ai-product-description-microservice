<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO\Response;

use App\DTO\Response\ApiErrorResponse;
use App\DTO\Response\AsyncJobCreatedResponse;
use App\DTO\Response\AsyncJobStatusResponse;
use App\DTO\Response\SyncDescriptionResponse;
use PHPUnit\Framework\TestCase;

class ResponseDtoTest extends TestCase
{
    public function testSyncDescriptionResponse(): void
    {
        $dto = new SyncDescriptionResponse('Sample description');
        $this->assertSame('Sample description', $dto->description);
        $this->assertSame(['description' => 'Sample description'], $dto->jsonSerialize());
    }

    public function testAsyncJobCreatedResponse(): void
    {
        $dto = new AsyncJobCreatedResponse('job-uuid-123', 'pending');
        $this->assertSame('job-uuid-123', $dto->jobId);
        $this->assertSame('pending', $dto->status);
        $this->assertSame([
            'job_id' => 'job-uuid-123',
            'status' => 'pending',
        ], $dto->jsonSerialize());
    }

    public function testAsyncJobStatusResponse(): void
    {
        $dto = new AsyncJobStatusResponse('job-uuid-123', 'completed', 'Desc text', null);
        $this->assertSame('job-uuid-123', $dto->jobId);
        $this->assertSame('completed', $dto->status);
        $this->assertSame('Desc text', $dto->description);
        $this->assertNull($dto->error);
        $this->assertSame([
            'job_id' => 'job-uuid-123',
            'status' => 'completed',
            'description' => 'Desc text',
            'error' => null,
        ], $dto->jsonSerialize());
    }

    public function testApiErrorResponseWithoutJobId(): void
    {
        $dto = new ApiErrorResponse('An error occurred');
        $this->assertSame('An error occurred', $dto->error);
        $this->assertNull($dto->jobId);
        $this->assertSame(['error' => 'An error occurred'], $dto->jsonSerialize());
    }

    public function testApiErrorResponseWithJobId(): void
    {
        $dto = new ApiErrorResponse('Job not found.', jobId: 'missing-job-123');
        $this->assertSame('Job not found.', $dto->error);
        $this->assertSame('missing-job-123', $dto->jobId);
        $this->assertSame([
            'error' => 'Job not found.',
            'job_id' => 'missing-job-123',
        ], $dto->jsonSerialize());
    }
}
