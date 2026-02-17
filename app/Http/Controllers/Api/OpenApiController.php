<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OpenApiController extends Controller
{
    /**
     * Generate OpenAPI specification manually
     *
     * @return JsonResponse
     */
    public function spec(): JsonResponse
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Octadecimal CMS API',
                'version' => '1.0.0',
                'description' => 'Dynamic content API for Next.js templates',
            ],
            'servers' => array_merge(
                config('app.env') === 'local' ? [
                    ['url' => rtrim(config('app.url'), '/') . '/api', 'description' => 'Local'],
                ] : [],
                [
                    ['url' => 'https://api.example.test/api', 'description' => 'Development'],
                    ['url' => 'https://octadecimal.studio/api', 'description' => 'Production'],
                ]
            ),
            'paths' => [
                '/v1/content' => [
                    'get' => [
                        'operationId' => 'getContent',
                        'tags' => ['Content'],
                        'summary' => 'Get content blocks for a template',
                        'description' => 'Retrieve content blocks filtered by template and/or section. Tenant is identified via X-Tenant-ID header, ?tenant_id query, or subdomain.',
                        'parameters' => [
                            [
                                'name' => 'X-Tenant-ID',
                                'in' => 'header',
                                'required' => false,
                                'description' => 'Tenant UUID (highest priority)',
                                'schema' => [
                                    'type' => 'string',
                                    'format' => 'uuid',
                                ],
                            ],
                            [
                                'name' => 'tenant_id',
                                'in' => 'query',
                                'required' => false,
                                'description' => 'Tenant UUID (fallback if header not provided)',
                                'schema' => [
                                    'type' => 'string',
                                    'format' => 'uuid',
                                ],
                            ],
                            [
                                'name' => 'template',
                                'in' => 'query',
                                'required' => false,
                                'description' => 'Template slug (e.g. bar-mobilny)',
                                'schema' => [
                                    'type' => 'string',
                                ],
                            ],
                            [
                                'name' => 'section',
                                'in' => 'query',
                                'required' => false,
                                'description' => 'Content section (hero, gallery, testimonials, contact)',
                                'schema' => [
                                    'type' => 'string',
                                    'enum' => ['hero', 'gallery', 'testimonials', 'contact'],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Content blocks',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'data' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        '$ref' => '#/components/schemas/ContentBlock',
                                                    ],
                                                ],
                                                'meta' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'count' => [
                                                            'type' => 'integer',
                                                        ],
                                                        'template' => [
                                                            'type' => 'string',
                                                            'nullable' => true,
                                                        ],
                                                        'section' => [
                                                            'type' => 'string',
                                                            'nullable' => true,
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '404' => [
                                'description' => 'Tenant not found',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/Error',
                                        ],
                                    ],
                                ],
                            ],
                            '429' => [
                                'description' => 'Rate limit exceeded',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/Error',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '/v1/templates/register' => [
                    'post' => [
                        'operationId' => 'registerTemplate',
                        'tags' => ['Templates'],
                        'summary' => 'Register a deployed template',
                        'description' => 'Register or update a template with webhook URL for revalidation. Called automatically by deployment script.',
                        'parameters' => [
                            [
                                'name' => 'X-Tenant-ID',
                                'in' => 'header',
                                'required' => false,
                                'description' => 'Tenant UUID (highest priority)',
                                'schema' => [
                                    'type' => 'string',
                                    'format' => 'uuid',
                                ],
                            ],
                        ],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'slug', 'webhook_url', 'tenant_id'],
                                        'properties' => [
                                            'name' => [
                                                'type' => 'string',
                                                'example' => 'bar-mobilny',
                                            ],
                                            'slug' => [
                                                'type' => 'string',
                                                'pattern' => '^[a-z0-9-]+$',
                                                'example' => 'bar-mobilny',
                                            ],
                                            'webhook_url' => [
                                                'type' => 'string',
                                                'format' => 'uri',
                                                'example' => 'https://bar-mobilny.octadecimal.studio',
                                            ],
                                            'tenant_id' => [
                                                'type' => 'string',
                                                'format' => 'uuid',
                                            ],
                                            'deployment_env' => [
                                                'type' => 'string',
                                                'enum' => ['dev', 'prd', 'tst'],
                                                'default' => 'prd',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Template registered',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'data' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'id' => ['type' => 'string', 'format' => 'uuid'],
                                                        'name' => ['type' => 'string'],
                                                        'slug' => ['type' => 'string'],
                                                        'webhook_url' => ['type' => 'string'],
                                                        'deployment_env' => ['type' => 'string'],
                                                    ],
                                                ],
                                                'message' => ['type' => 'string'],
                                                'action' => ['type' => 'string', 'enum' => ['created', 'updated']],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '200' => [
                                'description' => 'Template updated',
                            ],
                            '400' => [
                                'description' => 'Invalid request',
                            ],
                            '404' => [
                                'description' => 'Tenant not found',
                            ],
                        ],
                    ],
                ],
                '/v1/templates/{slug}/webhook/test' => [
                    'get' => [
                        'operationId' => 'testTemplateWebhook',
                        'tags' => ['Templates'],
                        'summary' => 'Test webhook connectivity',
                        'parameters' => [
                            [
                                'name' => 'slug',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'string'],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Webhook test result',
                            ],
                            '404' => [
                                'description' => 'Template not found',
                            ],
                        ],
                    ],
                ],
                // ============================================================
                // NEW API V1 - Site-based content API (2026-01-24)
                // ============================================================
                '/v1/health' => [
                    'get' => [
                        'operationId' => 'healthCheck',
                        'tags' => ['System'],
                        'summary' => 'Check system health',
                        'responses' => [
                            '200' => [
                                'description' => 'System healthy',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'status' => ['type' => 'string', 'enum' => ['healthy', 'unhealthy']],
                                                'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                                                'version' => ['type' => 'string'],
                                                'checks' => ['type' => 'object'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '503' => ['description' => 'System unhealthy'],
                        ],
                    ],
                ],
                '/v1/sites/{slug}/content' => [
                    'get' => [
                        'operationId' => 'getSiteContent',
                        'tags' => ['Sites', 'Content'],
                        'summary' => 'Get site content for environment',
                        'parameters' => [
                            [
                                'name' => 'slug',
                                'in' => 'path',
                                'required' => true,
                                'description' => 'Site slug',
                                'schema' => ['type' => 'string'],
                            ],
                            [
                                'name' => 'env',
                                'in' => 'query',
                                'description' => 'Environment (staging or production)',
                                'schema' => ['type' => 'string', 'enum' => ['staging', 'production'], 'default' => 'production'],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Site content',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/SiteContentResponse'],
                                    ],
                                ],
                            ],
                            '404' => ['description' => 'Site not found'],
                        ],
                    ],
                ],
                '/v1/sites/{slug}/motorcycles' => [
                    'get' => [
                        'operationId' => 'listMotorcycles',
                        'tags' => ['Sites', 'Motorcycles'],
                        'summary' => 'List motorcycles with filtering and pagination',
                        'parameters' => [
                            ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                            ['name' => 'category', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'brand', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'available', 'in' => 'query', 'schema' => ['type' => 'boolean']],
                            ['name' => 'min_price', 'in' => 'query', 'schema' => ['type' => 'number']],
                            ['name' => 'max_price', 'in' => 'query', 'schema' => ['type' => 'number']],
                            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20, 'maximum' => 100]],
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                            ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['price_asc', 'price_desc', 'name_asc', 'name_desc', 'newest']]],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Paginated list of motorcycles',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/MotorcycleListResponse'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '/v1/sites/{slug}/motorcycles/{id}' => [
                    'get' => [
                        'operationId' => 'getMotorcycle',
                        'tags' => ['Sites', 'Motorcycles'],
                        'summary' => 'Get motorcycle details',
                        'parameters' => [
                            ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                            ['name' => 'id', 'in' => 'path', 'required' => true, 'description' => 'Motorcycle UUID or slug', 'schema' => ['type' => 'string']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Motorcycle details'],
                            '404' => ['description' => 'Motorcycle not found'],
                        ],
                    ],
                ],
                '/v1/webhooks/revalidate' => [
                    'post' => [
                        'operationId' => 'triggerRevalidation',
                        'tags' => ['Webhooks'],
                        'summary' => 'Trigger content revalidation',
                        'security' => [['revalidateSecret' => []]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['site_id'],
                                        'properties' => [
                                            'site_id' => ['type' => 'string', 'format' => 'uuid'],
                                            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                                            'path' => ['type' => 'string'],
                                            'environment' => ['type' => 'string', 'enum' => ['staging', 'production']],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Revalidation triggered'],
                            '401' => ['description' => 'Invalid secret'],
                            '404' => ['description' => 'Site not found'],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'ContentBlock' => [
                        'type' => 'object',
                        'required' => ['id', 'type', 'section', 'data', 'order'],
                        'properties' => [
                            'id' => [
                                'type' => 'string',
                                'format' => 'uuid',
                                'description' => 'Unique identifier',
                            ],
                            'type' => [
                                'type' => 'string',
                                'enum' => ['hero', 'text', 'image', 'gallery', 'testimonials', 'contact', 'custom'],
                                'description' => 'Content block type',
                            ],
                            'section' => [
                                'type' => 'string',
                                'description' => 'Content section identifier',
                            ],
                            'data' => [
                                'type' => 'object',
                                'description' => 'Flexible JSON content',
                                'additionalProperties' => true,
                            ],
                            'order' => [
                                'type' => 'integer',
                                'description' => 'Display order',
                            ],
                            'metadata' => [
                                'type' => 'object',
                                'description' => 'Additional metadata',
                                'additionalProperties' => true,
                                'nullable' => true,
                            ],
                            'created_at' => [
                                'type' => 'string',
                                'format' => 'date-time',
                                'description' => 'Creation timestamp',
                            ],
                            'updated_at' => [
                                'type' => 'string',
                                'format' => 'date-time',
                                'description' => 'Last update timestamp',
                            ],
                        ],
                    ],
                    'Error' => [
                        'type' => 'object',
                        'required' => ['error'],
                        'properties' => [
                            'error' => [
                                'type' => 'object',
                                'required' => ['code', 'message'],
                                'properties' => [
                                    'code' => [
                                        'type' => 'string',
                                        'description' => 'Error code',
                                    ],
                                    'message' => [
                                        'type' => 'string',
                                        'description' => 'Error message',
                                    ],
                                    'details' => [
                                        'type' => 'object',
                                        'description' => 'Additional error details',
                                        'additionalProperties' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    // NEW SCHEMAS (2026-01-24)
                    'SiteContentResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'site' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'string', 'format' => 'uuid'],
                                    'name' => ['type' => 'string'],
                                    'slug' => ['type' => 'string'],
                                    'tagline' => ['type' => 'string', 'nullable' => true],
                                    'logo' => ['type' => 'string', 'nullable' => true],
                                    'environment' => ['type' => 'string'],
                                ],
                            ],
                            'navigation' => ['type' => 'object', 'additionalProperties' => true],
                            'sections' => ['type' => 'object', 'additionalProperties' => true],
                            'footer' => ['type' => 'object', 'additionalProperties' => true],
                            'meta' => [
                                'type' => 'object',
                                'properties' => [
                                    'generated_at' => ['type' => 'string', 'format' => 'date-time'],
                                    'cache_ttl' => ['type' => 'integer'],
                                ],
                            ],
                        ],
                    ],
                    'MotorcycleListResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/Motorcycle'],
                            ],
                            'meta' => [
                                'type' => 'object',
                                'properties' => [
                                    'current_page' => ['type' => 'integer'],
                                    'per_page' => ['type' => 'integer'],
                                    'total' => ['type' => 'integer'],
                                    'last_page' => ['type' => 'integer'],
                                    'from' => ['type' => 'integer'],
                                    'to' => ['type' => 'integer'],
                                ],
                            ],
                            'links' => [
                                'type' => 'object',
                                'properties' => [
                                    'first' => ['type' => 'string', 'nullable' => true],
                                    'last' => ['type' => 'string', 'nullable' => true],
                                    'prev' => ['type' => 'string', 'nullable' => true],
                                    'next' => ['type' => 'string', 'nullable' => true],
                                ],
                            ],
                        ],
                    ],
                    'Motorcycle' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'],
                            'name' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                            'brand' => ['type' => 'object'],
                            'category' => ['type' => 'object'],
                            'engine' => [
                                'type' => 'object',
                                'properties' => [
                                    'capacity' => ['type' => 'integer'],
                                    'capacity_formatted' => ['type' => 'string'],
                                ],
                            ],
                            'year' => ['type' => 'integer'],
                            'pricing' => [
                                'type' => 'object',
                                'properties' => [
                                    'per_day' => ['type' => 'number'],
                                    'per_week' => ['type' => 'number'],
                                    'per_month' => ['type' => 'number'],
                                    'deposit' => ['type' => 'number'],
                                    'currency' => ['type' => 'string'],
                                ],
                            ],
                            'availability' => [
                                'type' => 'object',
                                'properties' => [
                                    'available' => ['type' => 'boolean'],
                                    'featured' => ['type' => 'boolean'],
                                ],
                            ],
                            'images' => ['type' => 'object'],
                        ],
                    ],
                ],
                'securitySchemes' => [
                    'revalidateSecret' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-Revalidate-Secret',
                    ],
                ],
            ],
        ];

        return response()->json($spec);
    }
}
