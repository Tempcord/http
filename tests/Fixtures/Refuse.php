<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Fixtures;

use Tempcord\Plugins\Http\Http\Middleware;
use Tempcord\Plugins\Http\Http\Request;
use Tempcord\Plugins\Http\Http\Response;

/**
 * Answers instead of letting the handler run, which is what anything checking
 * a caller's right to be asking has to be able to do.
 */
final class Refuse implements Middleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        Trail::$steps[] = 'refused';

        return Response::unauthorized();
    }
}
