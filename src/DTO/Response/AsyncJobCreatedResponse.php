<?php

declare(strict_types=1);

namespace App\DTO\Response;

use JsonSerializable;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AsyncJobCreatedResponse implements JsonSerializable
{
    public function __construct(
        #[SerializedName('job_id')]
        public string $jobId,
        public string $status,
    ) {}

    /**
     * @return array{job_id: string, status: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'job_id' => $this->jobId,
            'status' => $this->status,
        ];
    }
}
