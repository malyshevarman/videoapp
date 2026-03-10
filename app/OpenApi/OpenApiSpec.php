<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="API VideoApp",
 *     version="1.0.0",
 *     description="Документация API для интеграции с внешними системами."
 * )
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Основной сервер API"
 * )
 * @OA\Tag(
 *     name="External Services",
 *     description="Методы для синхронизации заказ-нарядов и связанных данных из внешних систем."
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Bearer",
 *     description="Bearer token, сгенерированный в админке в разделе Настройки."
 * )
 */
class OpenApiSpec
{
}
