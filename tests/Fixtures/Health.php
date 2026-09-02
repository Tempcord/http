<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Fixtures;

use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Http\Method;
use Tempcord\Plugins\Http\Http\Response;

#[Route(Method::GET, '/health')]
final class Health
{
    public function __invoke(): Response
    {
        return Response::json(['status' => 'ok']);
    }
}
