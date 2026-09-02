<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http;

use Psr\Http\Message\ServerRequestInterface;
use Tempcord\Plugins\Http\Definitions\RouteDefinition;
use Tempcord\Plugins\Http\Http\Method;
use Tempcord\Plugins\Http\Http\Middleware;
use Tempcord\Plugins\Http\Http\Request;
use Tempcord\Plugins\Http\Http\Response;
use RuntimeException;
use Tempest\Container\Container;
use Tempest\Container\Singleton;
use Tempest\Log\Logger;
use Tempest\Reflection\ParameterReflector;
use Throwable;

/**
 * Holds every discovered route and answers requests with them.
 */
#[Singleton]
final class Router
{
    /** @var list<RouteDefinition> */
    private array $routes = [];

    private bool $sorted = false;

    public function __construct(
        private readonly Container $container,
    ) {}

    public function add(RouteDefinition $route): void
    {
        $this->routes[] = $route;
        $this->sorted = false;
    }

    /**
     * @return list<RouteDefinition>
     */
    public function all(): array
    {
        return $this->routes;
    }

    public function handle(ServerRequestInterface $psr): Response
    {
        $path = $psr->getUri()->getPath();
        $method = Method::tryFrom($psr->getMethod());
        $pathExists = false;

        foreach ($this->ordered() as $route) {
            $parameters = $route->match($path);

            if ($parameters === null) {
                continue;
            }

            $pathExists = true;

            if ($route->method !== $method) {
                continue;
            }

            return $this->call($route, new Request($psr, $parameters));
        }

        /*
         * A path that exists under another method is a 405 rather than a 404 —
         * the difference is the whole of "you asked wrongly" versus "there is
         * nothing here", and a caller debugging a webhook needs to know which.
         */
        return $pathExists
            ? Response::json(['error' => 'Method Not Allowed'], 405)
            : Response::notFound();
    }

    private function call(RouteDefinition $route, Request $request): Response
    {
        try {
            return $this->through($route)($request);
        } catch (Throwable $throwable) {
            /*
             * Contained, and deliberately vague to the caller: whatever went
             * wrong is in the log, and an exception message is not something to
             * hand to whoever is on the other end of the socket.
             */
            $this->logger()->error(
                'Route ' . $route->method->value . ' ' . $route->path . ' failed: '
                . $throwable->getMessage(),
                ['exception' => $throwable],
            );

            return Response::json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * The handler, wrapped in its middleware.
     *
     * Built from the inside out so that the first one listed ends up outermost,
     * which is the order anyone reading the attribute expects — and the order
     * that matters when one of them is the check that says whether the caller
     * may be here at all.
     *
     * @return callable(Request): Response
     */
    private function through(RouteDefinition $route): callable
    {
        /*
         * Normalised here rather than after the middleware have run, so that
         * one of them wrapping the answer is handed a Response and not
         * whatever a handler happened to return.
         */
        $next = function (Request $request) use ($route): Response {
            $answer = $route->invoke->invokeArgs(
                $this->container->get($route->handler),
                $this->argumentsFor($route, $request),
            );

            return $answer instanceof Response ? $answer : Response::noContent();
        };

        foreach (array_reverse($route->middleware) as $middleware) {
            $inner = $next;
            $next = fn(Request $request): Response => $this->middleware($middleware)($request, $inner);
        }

        return $next;
    }

    private function middleware(string $class): Middleware
    {
        $middleware = $this->container->get($class);

        if (!$middleware instanceof Middleware) {
            throw new RuntimeException($class . ' must implement ' . Middleware::class);
        }

        return $middleware;
    }

    /**
     * Resolved when something goes wrong rather than in the constructor.
     *
     * Discovery builds this router while the container is still being
     * assembled, before the initializers that provide the logger have
     * themselves been found — asking for one up front makes the whole
     * application fail to boot.
     */
    private function logger(): Logger
    {
        return $this->container->get(Logger::class);
    }

    /**
     * A handler takes whatever it asks for: the request where it is typed as
     * one, and otherwise the path segment of the same name.
     *
     * @return list<mixed>
     */
    private function argumentsFor(RouteDefinition $route, Request $request): array
    {
        $arguments = [];

        foreach ($route->invoke->getParameters() as $parameter) {
            $arguments[] = $this->isRequest($parameter)
                ? $request
                : $request->parameter($parameter->getName());
        }

        return $arguments;
    }

    private function isRequest(ParameterReflector $parameter): bool
    {
        return $parameter->getReflection()->hasType()
            && $parameter->getType()->getName() === Request::class;
    }

    /**
     * Literal paths before ones with segments, so /guilds/mine is not swallowed
     * by /guilds/{id}. Routes declared in either order behave the same.
     *
     * @return list<RouteDefinition>
     */
    private function ordered(): array
    {
        if (!$this->sorted) {
            usort(
                $this->routes,
                static fn(RouteDefinition $a, RouteDefinition $b) => $a->specificity() <=> $b->specificity(),
            );

            $this->sorted = true;
        }

        return $this->routes;
    }
}
