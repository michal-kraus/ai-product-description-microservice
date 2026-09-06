<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ApiDocsController
{
    public function __construct(
        private KernelInterface $kernel,
    ) {}

    #[Route('/api/docs', name: 'app_api_docs', methods: ['GET'])]
    public function ui(): Response
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Product Description Microservice - API Docs</title>
                <link rel="stylesheet" href="/swagger-ui/swagger-ui.css" />
                <link rel="icon" type="image/png" href="/swagger-ui/favicon-32x32.png" sizes="32x32" />
                <style>
                    body { margin: 0; padding: 0; background: #fafafa; }
                    .swagger-ui .topbar { display: none; }
                </style>
            </head>
            <body>
                <div id="swagger-ui"></div>
                <script src="/swagger-ui/swagger-ui-bundle.js"></script>
                <script src="/swagger-ui/swagger-ui-standalone-preset.js"></script>
                <script>
                    window.onload = function() {
                        SwaggerUIBundle({
                            url: "/api/docs/openapi.yaml",
                            dom_id: '#swagger-ui',
                            deepLinking: true,
                            presets: [
                                SwaggerUIBundle.presets.apis,
                                SwaggerUIStandalonePreset
                            ],
                            layout: "StandaloneLayout"
                        });
                    };
                </script>
            </body>
            </html>
            HTML;

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    #[Route('/api/docs/openapi.yaml', name: 'app_api_docs_spec', methods: ['GET'])]
    public function spec(): Response
    {
        $specPath = $this->kernel->getProjectDir() . '/openapi.yaml';

        if (!file_exists($specPath)) {
            return new Response('OpenAPI specification not found.', Response::HTTP_NOT_FOUND, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        $content = (string) file_get_contents($specPath);

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/yaml; charset=utf-8',
        ]);
    }
}
