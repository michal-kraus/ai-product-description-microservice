<?php

declare(strict_types=1);

namespace App\DTO\Response;

use JsonSerializable;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AsyncJobStatusResponse implements JsonSerializable
{
    public function __construct(
        #[SerializedName('job_id')]
        public string $jobId,
        public string $status,
        public ?string $description = null,
        public ?string $error = null,
    ) {}

    /**
     * @return array{job_id: string, status: string, description: string|null, error: string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'job_id' => $this->jobId,
            'status' => $this->status,
            'description' => $this->description,
            'error' => $this->error,
        ];
    }
}
