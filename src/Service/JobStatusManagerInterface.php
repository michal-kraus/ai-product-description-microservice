<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\JobStatus;

interface JobStatusManagerInterface
{
    public function createJob(string $jobId): void;

    /**
     * @param array<string, mixed> $data
     */
    public function updateJob(string $jobId, array $data): void;

    public function getJob(string $jobId): ?JobStatus;
}
