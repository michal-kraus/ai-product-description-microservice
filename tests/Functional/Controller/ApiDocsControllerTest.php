<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

final class ApiDocsControllerTest extends WebTestCase
{
    public function testItRendersSwaggerUi(): void
    {
        $client = static::createClient();
        $router = static::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $client->request('GET', $router->generate('app_api_docs'));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/html; charset=utf-8');
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('/swagger-ui/swagger-ui-bundle.js', $content);
        self::assertStringContainsString('/swagger-ui/swagger-ui.css', $content);
        self::assertStringNotContainsString('unpkg.com', $content);
    }

    public function testItServesOpenApiSpec(): void
    {
        $client = static::createClient();
        $router = static::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $client->request('GET', $router->generate('app_api_docs_spec'));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/yaml; charset=utf-8');
        self::assertStringContainsString('openapi: 3.1.0', (string) $client->getResponse()->getContent());
    }

    public function testItReturns404WhenSpecFileNotFound(): void
    {
        $kernel = $this->createStub(\Symfony\Component\HttpKernel\KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn('/non-existent-dir');

        $controller = new \App\Controller\ApiDocsController($kernel);
        $response = $controller->spec();

        self::assertSame(\Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('not found', (string) $response->getContent());
    }
}
