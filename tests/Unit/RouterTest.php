<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use React\Http\Message\ServerRequest;
use RuntimeException;
use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Compiler\RouteCompiler;
use Tempcord\Plugins\Http\Definitions\RouteDefinition;
use Tempcord\Plugins\Http\Http\Method;
use Tempcord\Plugins\Http\Http\Request;
use Tempcord\Plugins\Http\Http\Response;
use Tempcord\Plugins\Http\Router;
use Tempcord\Plugins\Http\Tests\Doubles\RecordingLogger;
use Tempcord\Plugins\Http\Tests\Fixtures\Boom;
use Tempcord\Plugins\Http\Tests\Fixtures\Handlerless;
use Tempcord\Plugins\Http\Tests\Fixtures\Health;
use Tempcord\Plugins\Http\Tests\Fixtures\Member;
use Tempcord\Plugins\Http\Tests\Fixtures\MyGuild;
use Tempcord\Plugins\Http\Tests\Fixtures\Relative;
use Tempcord\Plugins\Http\Tests\Fixtures\Silent;
use Tempcord\Plugins\Http\Tests\Fixtures\Webhook;
use Tempest\Container\GenericContainer;
use Tempest\Log\Logger;
use Tempest\Reflection\ClassReflector;

#[CoversClass(Router::class)]
#[CoversClass(RouteCompiler::class)]
#[CoversClass(RouteDefinition::class)]
#[CoversClass(Response::class)]
#[CoversClass(Request::class)]
final class RouterTest extends TestCase
{
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    private function router(string ...$classes): Router
    {
        $container = new GenericContainer();
        $container->singleton(Logger::class, $this->logger);

        $router = new Router($container, $this->logger);
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
     * @return array<mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode($response->body, associative: true) ?? [];
    }

    public function test_a_route_answers_its_own_path(): void
    {
        $response = $this->get($this->router(Health::class), '/health');

        $this->assertSame(200, $response->status);
        $this->assertSame(['status' => 'ok'], $this->decode($response));
    }

    public function test_a_path_nothing_serves_is_not_found(): void
    {
        $this->assertSame(404, $this->get($this->router(Health::class), '/nothing')->status);
    }

    /**
     * The difference between "you asked wrongly" and "there is nothing here" is
     * the whole of what a caller debugging a webhook needs to know.
     */
    public function test_a_path_that_exists_under_another_method_is_not_a_404(): void
    {
        $router = $this->router(Webhook::class);

        $this->assertSame(405, $this->get($router, '/webhook')->status);
    }

    public function test_a_route_may_answer_more_than_one_method(): void
    {
        $router = $this->router(Webhook::class);

        foreach (['POST', 'PUT'] as $method) {
            $request = new ServerRequest($method, 'http://bot.test/webhook', [], '{"a":1}');

            $this->assertSame(200, $router->handle($request)->status, $method);
        }
    }

    public function test_a_named_segment_reaches_the_handler(): void
    {
        $response = $this->get($this->router(Member::class), '/guilds/123/members/456');

        $this->assertSame(
            ['guild' => '123', 'member' => '456', 'path' => '/guilds/123/members/456'],
            $this->decode($response),
        );
    }

    /**
     * A handler takes what it asks for in whatever order it asks for it, the
     * way a command handler does.
     */
    public function test_the_request_is_given_wherever_it_is_asked_for(): void
    {
        $response = $this->get($this->router(Member::class), '/guilds/1/members/2');

        $this->assertArrayHasKey('path', $this->decode($response));
    }

    /**
     * A segment matches anything but a slash, so a name cannot swallow the rest
     * of the path.
     */
    public function test_a_segment_does_not_swallow_the_rest_of_the_path(): void
    {
        $router = $this->router(Member::class);

        $this->assertSame(404, $this->get($router, '/guilds/123/members/456/roles')->status);
    }

    /**
     * Declared in either order, a literal path wins over one with a segment —
     * otherwise /guilds/mine depends on which file discovery reached first.
     */
    public function test_a_literal_path_is_not_swallowed_by_a_named_one(): void
    {
        foreach ([[MyGuild::class, Member::class], [Member::class, MyGuild::class]] as $order) {
            $response = $this->get($this->router(...$order), '/guilds/mine');

            $this->assertSame('mine', $response->body);
        }
    }

    public function test_a_trailing_slash_is_the_same_route(): void
    {
        $this->assertSame(200, $this->get($this->router(Health::class), '/health/')->status);
    }

    public function test_a_handler_with_nothing_to_say_answers_no_content(): void
    {
        $router = $this->router(Silent::class);
        $response = $router->handle(new ServerRequest('DELETE', 'http://bot.test/silent'));

        $this->assertSame(204, $response->status);
        $this->assertSame('', $response->body);
    }

    /**
     * Whatever went wrong is in the log; the caller is told nothing, because an
     * exception message is not something to hand to whoever is on the other end
     * of the socket.
     */
    public function test_a_handler_that_throws_is_contained(): void
    {
        $response = $this->get($this->router(Boom::class), '/boom');

        $this->assertSame(500, $response->status);
        $this->assertSame(['error' => 'Internal Server Error'], $this->decode($response));
        $this->assertStringNotContainsString('database is on fire', $response->body);
        $this->assertTrue($this->logger->has('the database is on fire'));
    }

    /**
     * One route falling over must not take the others with it.
     */
    public function test_a_failed_route_leaves_the_rest_serving(): void
    {
        $router = $this->router(Boom::class, Health::class);

        $this->get($router, '/boom');

        $this->assertSame(200, $this->get($router, '/health')->status);
    }

    public function test_a_body_that_is_not_json_is_reported_by_the_handler(): void
    {
        $router = $this->router(Webhook::class);
        $request = new ServerRequest('POST', 'http://bot.test/webhook', [], 'not json at all');

        $this->assertSame(400, $router->handle($request)->status);
    }

    public function test_a_class_without_an_invoke_method_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('should declare an __invoke method');

        $this->router(Handlerless::class);
    }

    /**
     * A path without a leading slash would never match, and would do it
     * silently — the request just falls through to the 404.
     */
    public function test_a_path_that_could_never_match_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must start with "/"');

        $this->router(Relative::class);
    }

    public function test_it_lists_what_it_serves(): void
    {
        $this->assertCount(2, $this->router(Health::class, MyGuild::class)->all());
        $this->assertCount(2, $this->router(Webhook::class)->all());
    }
}
