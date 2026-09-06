<?php

declare(strict_types=1);

namespace App\Service;

interface JobStatusManagerInterface
{
    public function createJob(string $jobId): void;

    /**
     * @param array<string, mixed> $data
     */
    public function updateJob(string $jobId, array $data): void;

    /**
     * @return array<string, mixed>|null
     */
    public function getJob(string $jobId): ?array;
}
