<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Fixtures;

use RuntimeException;
use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Http\Method;

#[Route(Method::GET, '/boom')]
final class Boom
{
    public function __invoke(): never
    {
        throw new RuntimeException('the database is on fire');
    }
}
