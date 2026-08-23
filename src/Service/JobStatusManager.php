<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\GenerateProductDescriptionMessageStatus;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemPoolInterface;

class JobStatusManager
{
    public const KEY_PREFIX = 'job_';
    public const DEFAULT_TTL = 3600;

    public function __construct(
        private CacheItemPoolInterface $messengerJobsCache,
        private int $ttl = self::DEFAULT_TTL
    ) {}

    public function createJob(string $jobId): void
    {
        $item = $this->messengerJobsCache->getItem(self::KEY_PREFIX . $jobId);
        $item->set([
            'status' => GenerateProductDescriptionMessageStatus::CREATED->value,
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
        return is_array($data) ? $data : null;
    }
}
