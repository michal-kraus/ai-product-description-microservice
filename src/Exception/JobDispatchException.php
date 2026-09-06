<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;
use Throwable;

class JobDispatchException extends RuntimeException
{
    public function __construct(
        string $message = 'Unable to dispatch job.',
        private ?string $jobId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getJobId(): ?string
    {
        return $this->jobId;
    }
}
