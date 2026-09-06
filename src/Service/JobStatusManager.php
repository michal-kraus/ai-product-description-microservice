<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\GenerateProductDescriptionMessageStatus;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;

class JobStatusManager implements JobStatusManagerInterface
{
    public const KEY_PREFIX = 'job_';
    public const DEFAULT_TTL = 3600;
    public const LOCK_TTL = 15.0;

    public function __construct(
        private CacheItemPoolInterface $messengerJobsCache,
        private int $ttl = self::DEFAULT_TTL,
        private ?LockFactory $lockFactory = null,
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
        $lock = $this->lockFactory?->createLock(self::KEY_PREFIX . $jobId, self::LOCK_TTL);
        $lock?->acquire(blocking: true);

        try {
            $item = $this->messengerJobsCache->getItem(self::KEY_PREFIX . $jobId);
            $existing = $item->isHit() ? (array) $item->get() : [];

            if (isset($data['status'])) {
                $newStatus = $data['status'] instanceof GenerateProductDescriptionMessageStatus
                    ? $data['status']
                    : GenerateProductDescriptionMessageStatus::from((string) $data['status']);

                $data['status'] = $newStatus->value;

                if (isset($existing['status']) && \is_string($existing['status'])) {
                    $currentStatus = GenerateProductDescriptionMessageStatus::tryFrom($existing['status']);

                    if ($currentStatus !== null && !$currentStatus->canTransitionTo($newStatus)) {
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
        } finally {
            $lock?->release();
        }
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
