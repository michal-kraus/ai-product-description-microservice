<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\GenerateProductDescriptionMessageStatus;

final readonly class JobStatus
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $jobId,
        public GenerateProductDescriptionMessageStatus $status,
        public ?string $description = null,
        public ?string $error = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public array $extra = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(string $jobId, array $data): self
    {
        $statusValue = (string) ($data['status'] ?? GenerateProductDescriptionMessageStatus::PENDING->value);
        $status = GenerateProductDescriptionMessageStatus::tryFrom($statusValue)
            ?? GenerateProductDescriptionMessageStatus::PENDING;

        return new self(
            jobId: $jobId,
            status: $status,
            description: isset($data['description']) && \is_string($data['description']) ? $data['description'] : null,
            error: isset($data['error']) && \is_string($data['error']) ? $data['error'] : null,
            createdAt: isset($data['created_at']) && \is_string($data['created_at']) ? $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) && \is_string($data['updated_at']) ? $data['updated_at'] : null,
            extra: $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->extra, [
            'status' => $this->status->value,
            'description' => $this->description,
            'error' => $this->error,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ]);
    }
}
