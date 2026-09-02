<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Definitions;

use Tempcord\Plugins\Http\Http\Method;
use Tempest\Reflection\MethodReflector;

/**
 * One route, compiled: what to match and what to call.
 */
final readonly class RouteDefinition
{
    /**
     * @param string $pattern the path as a regular expression, with each named
     *        segment captured under its own name
     * @param list<string> $parameters the segment names, in the order the path
     *        declares them
     * @param list<class-string<\Tempcord\Plugins\Http\Http\Middleware>> $middleware
     */
    public function __construct(
        public Method $method,
        public string $path,
        public string $pattern,
        public array $parameters,
        public string $handler,
        public MethodReflector $invoke,
        public array $middleware = [],
    ) {}

    /**
     * The segments this path named, or null when it does not match at all.
     *
     * @return array<string, string>|null
     */
    public function match(string $path): ?array
    {
        if (preg_match($this->pattern, $path, $found) !== 1) {
            return null;
        }

        $parameters = [];

        foreach ($this->parameters as $name) {
            $parameters[$name] = $found[$name] ?? '';
        }

        return $parameters;
    }

    /**
     * Routes with no segments of their own are tried first, so a literal
     * /guilds/mine is not swallowed by /guilds/{id}.
     */
    public function specificity(): int
    {
        return count($this->parameters);
    }
}
