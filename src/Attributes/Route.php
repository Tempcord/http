<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Attributes;

use Attribute;
use Tempcord\Plugins\Http\Http\Method;

/**
 * Declares an invokable class as the answer to one HTTP request.
 *
 * The path may name segments in braces, and a handler is given each by the name
 * it was declared under:
 *
 *     #[Route(Method::GET, '/guilds/{guild}/members/{member}')]
 *     final readonly class Member
 *     {
 *         public function __invoke(string $guild, string $member): Response
 *         {
 *             // ...
 *         }
 *     }
 *
 * A parameter typed as Request is given the request instead, wherever it sits.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Route
{
    /**
     * @param list<class-string<\Tempcord\Plugins\Http\Http\Middleware>> $middleware
     *        run in the order given, outermost first; any of them may answer
     *        instead of letting the handler run
     */
    public function __construct(
        public Method $method,
        public string $path,
        public array $middleware = [],
    ) {}
}
