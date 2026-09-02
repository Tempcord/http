<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use Tempcord\Plugins\Plugin;
use Tempcord\Tempcord;
use Tempest\Log\Logger;
use Throwable;

/**
 * Serves HTTP from inside the bot.
 *
 * On the bot's own event loop rather than behind PHP-FPM, and that is the whole
 * point: a route handled here shares the container, the database, the gateway
 * cache and the live connection. A request can answer with what the bot knows
 * this second, or make it do something, neither of which a separate web process
 * could do without inventing a channel between the two.
 *
 * The cost is that a handler blocking blocks the gateway as well — the same
 * bargain every other part of the bot already makes, and the reason a slow
 * handler should await rather than sleep.
 */
final readonly class HttpPlugin implements Plugin
{
    public function __construct(
        private Router $router,
        private HttpConfig $config,
        private Logger $logger,
    ) {}

    public function boot(Tempcord $tempcord): void
    {
        if (!$this->config->enabled || $this->router->all() === []) {
            return;
        }

        $server = new HttpServer(
            fn(ServerRequestInterface $request) => $this->router->handle($request)->toReact(),
        );

        try {
            $socket = new SocketServer($this->config->address(), [], Loop::get());
        } catch (Throwable $throwable) {
            /*
             * A port already taken must not stop the bot: everything else it
             * does still works, and a gateway that will not start because a
             * stale process holds 8080 is a bad trade.
             */
            $this->logger->error(
                'Could not listen on ' . $this->config->address() . ': ' . $throwable->getMessage(),
            );

            return;
        }

        $server->listen($socket);

        $this->logger->info(sprintf(
            'HTTP listening on %s with %d route(s).',
            $this->config->address(),
            count($this->router->all()),
        ));
    }
}
