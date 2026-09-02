<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Fixtures;

use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Http\Method;
use Tempcord\Plugins\Http\Http\Request;
use Tempcord\Plugins\Http\Http\Response;

#[Route(Method::POST, '/webhook')]
#[Route(Method::PUT, '/webhook')]
final class Webhook
{
    public function __invoke(Request $request): Response
    {
        $body = $request->json();

        return $body === null
            ? Response::badRequest('Expected JSON')
            : Response::json(['received' => $body, 'method' => $request->method()]);
    }
}
