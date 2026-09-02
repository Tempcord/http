<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Fixtures;

use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Http\Method;

/**
 * A handler with nothing to say, which is a 204 rather than a mistake.
 */
#[Route(Method::DELETE, '/silent')]
final class Silent
{
    public function __invoke(): void {}
}
