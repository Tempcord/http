<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Fixtures;

use Tempcord\Plugins\Http\Http\Middleware;
use Tempcord\Plugins\Http\Http\Request;
use Tempcord\Plugins\Http\Http\Response;

final class Second implements Middleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        Trail::$steps[] = 'second:before';

        return $next($request);
    }
}
