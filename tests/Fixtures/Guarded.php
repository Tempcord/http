<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Fixtures;

use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Http\Method;
use Tempcord\Plugins\Http\Http\Response;

#[Route(Method::GET, '/guarded', middleware: [First::class, Second::class])]
final class Guarded
{
    public function __invoke(): Response
    {
        Trail::$steps[] = 'handler';

        return Response::text('through');
    }
}
