<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Fixtures;

use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Http\Method;
use Tempcord\Plugins\Http\Http\Response;

#[Route(Method::GET, '/refused', middleware: [Refuse::class, Second::class])]
final class Refused
{
    public function __invoke(): Response
    {
        Trail::$steps[] = 'handler';

        return Response::text('should never run');
    }
}
