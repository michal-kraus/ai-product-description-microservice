<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\GenerateProductDescriptionMessageStatus;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use Psr\Cache\CacheItemPoolInterface;

class JobStatusManager
{
    public const KEY_PREFIX = 'job_';
    public const DEFAULT_TTL = 3600;

    public function __construct(
        private CacheItemPoolInterface $messengerJobsCache,
        private int $ttl = self::DEFAULT_TTL,
    ) {}

    public function createJob(string $jobId): void
    {
        $item = $this->messengerJobsCache->getItem(self::KEY_PREFIX . $jobId);
        $item->set([
            'status' => GenerateProductDescriptionMessageStatus::PENDING->value,
            'created_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ]);
        $item->expiresAfter($this->ttl);
        $this->messengerJobsCache->save($item);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateJob(string $jobId, array $data): void
    {
        $item = $this->messengerJobsCache->getItem(self::KEY_PREFIX . $jobId);
        $existing = $item->isHit() ? (array) $item->get() : [];

        if (isset($data['status'])) {
            if ($data['status'] instanceof GenerateProductDescriptionMessageStatus) {
                $data['status'] = $data['status']->value;
            }

            if (isset($existing['status']) && \is_string($existing['status']) && \is_string($data['status'])) {
                $currentStatus = GenerateProductDescriptionMessageStatus::tryFrom($existing['status']);
                $newStatus = GenerateProductDescriptionMessageStatus::tryFrom($data['status']);

                if ($currentStatus !== null && $newStatus !== null && !$currentStatus->canTransitionTo($newStatus)) {
                    throw new DomainException(\sprintf(
                        'Invalid job status transition from "%s" to "%s".',
                        $currentStatus->value,
                        $newStatus->value,
                    ));
                }
            }
        }

        $merged = array_merge($existing, $data, [
            'updated_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ]);

        $item->set($merged);
        $item->expiresAfter($this->ttl);
        $this->messengerJobsCache->save($item);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJob(string $jobId): ?array
    {
        $item = $this->messengerJobsCache->getItem(self::KEY_PREFIX . $jobId);
        if (!$item->isHit()) {
            return null;
        }

        $data = $item->get();

        return \is_array($data) ? $data : null;
    }
}
