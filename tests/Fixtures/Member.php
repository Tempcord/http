<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Fixtures;

use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Http\Method;
use Tempcord\Plugins\Http\Http\Request;
use Tempcord\Plugins\Http\Http\Response;

/**
 * Takes the request and both named segments, in an order the handler chose
 * rather than the one the path declares.
 */
#[Route(Method::GET, '/guilds/{guild}/members/{member}')]
final class Member
{
    public function __invoke(string $member, Request $request, string $guild): Response
    {
        return Response::json([
            'guild' => $guild,
            'member' => $member,
            'path' => $request->path(),
        ]);
    }
}
