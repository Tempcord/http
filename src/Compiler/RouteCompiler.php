<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Compiler;

use RuntimeException;
use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Definitions\RouteDefinition;
use Tempest\Reflection\ClassReflector;

final readonly class RouteCompiler
{
    public function compile(ClassReflector $class, Route $route): RouteDefinition
    {
        if (!$class->getReflection()->hasMethod('__invoke')) {
            throw new RuntimeException(
                'Class [' . $class->getName() . '] should declare an __invoke method',
            );
        }

        if (!str_starts_with($route->path, '/')) {
            /*
             * A path that does not start with a slash would never match, and
             * would do it silently — the request simply falls through to the
             * 404. Better said while the bot is starting.
             */
            throw new RuntimeException(
                'Route [' . $route->path . '] on ' . $class->getName() . ' must start with "/"',
            );
        }

        [$pattern, $parameters] = $this->patternFor($route->path);

        return new RouteDefinition(
            method: $route->method,
            path: $route->path,
            pattern: $pattern,
            parameters: $parameters,
            handler: $class->getName(),
            invoke: $class->getMethod('__invoke'),
            middleware: $route->middleware,
        );
    }

    /**
     * Turns "/guilds/{guild}/members/{member}" into a regular expression that
     * captures each named segment.
     *
     * A segment matches anything but a slash, so a name cannot swallow the rest
     * of the path — which is what makes /guilds/{id} and /guilds/{id}/members
     * two different routes rather than one greedy one.
     *
     * @return array{string, list<string>}
     */
    private function patternFor(string $path): array
    {
        $parameters = [];

        $pattern = preg_replace_callback(
            '/\{(\w+)\}/',
            static function (array $found) use (&$parameters): string {
                $parameters[] = $found[1];

                return '(?P<' . $found[1] . '>[^/]+)';
            },
            // Everything outside a placeholder is taken literally.
            implode('', array_map(
                static fn(string $piece) => str_starts_with($piece, '{') ? $piece : preg_quote($piece, '#'),
                preg_split('/(\{\w+\})/', $path, flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [],
            )),
        );

        // A trailing slash is the same route, since nobody means otherwise.
        return ['#\A' . $pattern . '/?\z#', $parameters];
    }
}
