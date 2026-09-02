<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use React\Http\Message\ServerRequest;
use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Compiler\RouteCompiler;
use Tempcord\Plugins\Http\Http\Response;
use Tempcord\Plugins\Http\Router;
use Tempcord\Plugins\Http\Tests\Doubles\RecordingLogger;
use Tempcord\Plugins\Http\Tests\Fixtures\Guarded;
use Tempcord\Plugins\Http\Tests\Fixtures\Health;
use Tempcord\Plugins\Http\Tests\Fixtures\Mislabelled;
use Tempcord\Plugins\Http\Tests\Fixtures\Refused;
use Tempcord\Plugins\Http\Tests\Fixtures\Trail;
use Tempest\Container\GenericContainer;
use Tempest\Log\Logger;
use Tempest\Reflection\ClassReflector;

/**
 * Middleware, and the one thing they exist for: being able to answer instead of
 * letting the handler run.
 */
#[CoversClass(Router::class)]
final class MiddlewareTest extends TestCase
{
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        Trail::reset();

        $this->logger = new RecordingLogger();
    }

    private function router(string ...$classes): Router
    {
        $container = new GenericContainer();
        $container->singleton(Logger::class, $this->logger);

        $router = new Router($container);
        $compiler = new RouteCompiler();

        foreach ($classes as $class) {
            $reflector = new ClassReflector($class);

            foreach ($reflector->getAttributes(Route::class) as $route) {
                $router->add($compiler->compile($reflector, $route));
            }
        }

        return $router;
    }

    private function get(Router $router, string $path): Response
    {
        return $router->handle(new ServerRequest('GET', 'http://bot.test' . $path));
    }

    /**
     * The first one listed is outermost, which is the order anyone reading the
     * attribute expects — and the order that matters when one of them decides
     * whether the caller may be here at all.
     */
    public function test_middleware_run_outermost_first_and_unwind(): void
    {
        $response = $this->get($this->router(Guarded::class), '/guarded');

        $this->assertSame('through', $response->body);
        $this->assertSame(
            ['first:before', 'second:before', 'handler', 'first:after'],
            Trail::$steps,
        );
    }

    public function test_middleware_may_change_the_answer_on_the_way_out(): void
    {
        $response = $this->get($this->router(Guarded::class), '/guarded');

        $this->assertSame('yes', $response->toReact()->getHeaderLine('X-First'));
    }

    /**
     * The whole point. A handler that is never reached cannot half-do the work
     * it was asked for, which is what makes this the right place for a check on
     * whether the caller may ask at all.
     */
    public function test_middleware_answering_stops_the_handler_running(): void
    {
        $response = $this->get($this->router(Refused::class), '/refused');

        $this->assertSame(401, $response->status);
        $this->assertSame(['refused'], Trail::$steps);
    }

    /**
     * And it stops the ones after it too, not just the handler.
     */
    public function test_middleware_answering_stops_the_ones_behind_it(): void
    {
        $this->get($this->router(Refused::class), '/refused');

        $this->assertNotContains('second:before', Trail::$steps);
    }

    public function test_a_route_without_middleware_is_unaffected(): void
    {
        $this->assertSame(200, $this->get($this->router(Health::class), '/health')->status);
        $this->assertSame([], Trail::$steps);
    }

    /**
     * Named on a route but not actually middleware: a 500 with the reason in
     * the log, rather than a call to something that has no idea what $next is.
     */
    public function test_something_that_is_not_middleware_is_refused(): void
    {
        $response = $this->get($this->router(Mislabelled::class), '/mislabelled');

        $this->assertSame(500, $response->status);
        $this->assertTrue($this->logger->has('must implement'));
    }
}
