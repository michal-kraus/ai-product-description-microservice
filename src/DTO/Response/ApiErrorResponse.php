<?php

declare(strict_types=1);

namespace App\DTO\Response;

use JsonSerializable;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class ApiErrorResponse implements JsonSerializable
{
    public function __construct(
        public string $error,
        #[SerializedName('job_id')]
        public ?string $jobId = null,
    ) {}

    /**
     * @return array{error: string, job_id?: string}
     */
    public function jsonSerialize(): array
    {
        $data = ['error' => $this->error];
        if ($this->jobId !== null) {
            $data['job_id'] = $this->jobId;
        }

        return $data;
    }
}
