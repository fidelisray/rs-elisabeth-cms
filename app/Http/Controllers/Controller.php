<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="RS Elisabeth CMS API",
 *      description="API documentation for RS Elisabeth CMS endpoints.",
 * )
 *
 * @OA\SecurityScheme(
 *      securityScheme="HmacAuth",
 *      type="apiKey",
 *      in="header",
 *      name="X-Signature"
 * )
 */
abstract class Controller
{
    //
}
