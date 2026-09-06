<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

final class RequestIdListener
{
    public const REQUEST_ID_ATTRIBUTE = 'request_id';
    public const HEADER_NAME = 'X-Request-ID';
    private const MAX_REQUEST_ID_LENGTH = 64;

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 250)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $rawRequestId = $request->headers->get(self::HEADER_NAME);

        if (\is_string($rawRequestId) && $this->isValidRequestId($rawRequestId)) {
            $requestId = $rawRequestId;
        } else {
            $requestId = Uuid::v7()->toRfc4122();
        }

        $request->attributes->set(self::REQUEST_ID_ATTRIBUTE, $requestId);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -250)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $requestId = $request->attributes->get(self::REQUEST_ID_ATTRIBUTE);

        if (\is_string($requestId) && $requestId !== '') {
            $event->getResponse()->headers->set(self::HEADER_NAME, $requestId);
        }
    }

    private function isValidRequestId(string $requestId): bool
    {
        $trimmed = trim($requestId);
        if ($trimmed === '' || \strlen($trimmed) > self::MAX_REQUEST_ID_LENGTH) {
            return false;
        }

        return preg_match('/^[a-zA-Z0-9_\-]+$/', $trimmed) === 1;
    }
}
