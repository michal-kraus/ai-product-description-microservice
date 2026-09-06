<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\RequestIdListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Uid\Uuid;

class RequestIdListenerTest extends TestCase
{
    private RequestIdListener $listener;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->listener = new RequestIdListener();
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    public function testItGeneratesUuidV7WhenHeaderIsMissing(): void
    {
        $request = new Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onKernelRequest($event);

        $requestId = $request->attributes->get(RequestIdListener::REQUEST_ID_ATTRIBUTE);
        $this->assertIsString($requestId);
        $this->assertTrue(Uuid::isValid($requestId));
    }

    public function testItPreservesValidIncomingHeader(): void
    {
        $request = new Request();
        $request->headers->set(RequestIdListener::HEADER_NAME, 'corr-id-123_456');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onKernelRequest($event);

        $this->assertSame('corr-id-123_456', $request->attributes->get(RequestIdListener::REQUEST_ID_ATTRIBUTE));
    }

    public function testItGeneratesNewUuidWhenIncomingHeaderIsInvalid(): void
    {
        $request = new Request();
        $request->headers->set(RequestIdListener::HEADER_NAME, 'invalid header with spaces & <chars>');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onKernelRequest($event);

        $requestId = $request->attributes->get(RequestIdListener::REQUEST_ID_ATTRIBUTE);
        $this->assertIsString($requestId);
        $this->assertNotSame('invalid header with spaces & <chars>', $requestId);
        $this->assertTrue(Uuid::isValid($requestId));
    }

    public function testItAppendsHeaderToResponse(): void
    {
        $request = new Request();
        $request->attributes->set(RequestIdListener::REQUEST_ID_ATTRIBUTE, 'corr-abc-999');

        $response = new Response();
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->onKernelResponse($event);

        $this->assertTrue($response->headers->has(RequestIdListener::HEADER_NAME));
        $this->assertSame('corr-abc-999', $response->headers->get(RequestIdListener::HEADER_NAME));
    }

    public function testItIgnoresSubRequests(): void
    {
        $request = new Request();
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);
        $this->listener->onKernelRequest($requestEvent);
        $this->assertFalse($request->attributes->has(RequestIdListener::REQUEST_ID_ATTRIBUTE));

        $response = new Response();
        $responseEvent = new ResponseEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);
        $this->listener->onKernelResponse($responseEvent);
        $this->assertFalse($response->headers->has(RequestIdListener::HEADER_NAME));
    }

    public function testItReplacesEmptyHeader(): void
    {
        $request = new Request();
        $request->headers->set(RequestIdListener::HEADER_NAME, '   ');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->listener->onKernelRequest($event);
        $id = $request->attributes->get(RequestIdListener::REQUEST_ID_ATTRIBUTE);
        $this->assertIsString($id);
        $this->assertTrue(Uuid::isValid($id));
    }

    public function testItReplacesTooLongHeader(): void
    {
        $request = new Request();
        $request->headers->set(RequestIdListener::HEADER_NAME, str_repeat('a', 65));
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->listener->onKernelRequest($event);
        $id = $request->attributes->get(RequestIdListener::REQUEST_ID_ATTRIBUTE);
        $this->assertIsString($id);
        $this->assertTrue(Uuid::isValid($id));
        $this->assertNotSame(str_repeat('a', 65), $id);
    }
}
