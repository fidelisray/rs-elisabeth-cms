<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'RS Elisabeth CMS API',
    description: 'API documentation for RS Elisabeth CMS endpoints.'
)]
#[OA\SecurityScheme(
    securityScheme: 'HmacAuth',
    type: 'apiKey',
    in: 'header',
    name: 'X-Signature'
)]
class SwaggerAnnotations {}
