<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Fixtures;

use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Http\Method;
use Tempcord\Plugins\Http\Http\Response;

/**
 * A path that would never match, and would do it silently.
 */
#[Route(Method::GET, 'health')]
final class Relative
{
    public function __invoke(): Response
    {
        return Response::noContent();
    }
}
