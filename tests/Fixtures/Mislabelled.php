<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Fixtures;

use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Http\Method;
use Tempcord\Plugins\Http\Http\Response;

/** @phpstan-ignore argument.type */
#[Route(Method::GET, '/mislabelled', middleware: [NotMiddleware::class])]
final class Mislabelled
{
    public function __invoke(): Response
    {
        return Response::noContent();
    }
}
