<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Fixtures;

use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Http\Method;
use Tempcord\Plugins\Http\Http\Response;

/**
 * A literal path that /guilds/{guild} would otherwise swallow.
 */
#[Route(Method::GET, '/guilds/mine')]
final class MyGuild
{
    public function __invoke(): Response
    {
        return Response::text('mine');
    }
}
