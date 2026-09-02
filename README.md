# Tempcord HTTP

An HTTP server that runs **inside** the bot, on the same event loop as the
gateway, for the [Tempcord](https://github.com/Tempcord/framework) Discord bot
framework.

## Installation

```bash
composer require tempcord/http
```

## A route

```php
use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Http\Method;
use Tempcord\Plugins\Http\Http\Response;

#[Route(Method::GET, '/health')]
final readonly class Health
{
    public function __construct(private Discord $discord) {}

    public function __invoke(): Response
    {
        return Response::json(['ready' => $this->discord->gateway->isReady()]);
    }
}
```

That is the whole registration. Routes are discovered like commands and
listeners, built by the container, and served as soon as the bot boots.

## Why in the bot rather than behind PHP-FPM

Because of what a handler can reach. The gateway lives in a long-running
process; a request handled by FPM is a different process entirely, with no
access to the connection, the guild cache, or anything the bot learned this
minute. It could only do what any outside script could do — call the REST API
and hope.

Served here, a route shares the container, the database, the cache and the live
connection:

- `/health` answers with what the gateway is actually doing;
- a webhook from an outside system posts to a channel the moment it arrives;
- a form can read and write the same rows a command does, with no queue between.

The cost is the other side of the same coin: a handler that blocks blocks the
gateway too. That is the bargain every other part of a Tempcord bot already
makes, and the reason a slow handler should `await` rather than sleep.

## Named segments

```php
#[Route(Method::GET, '/guilds/{guild}/members/{member}')]
final readonly class Member
{
    public function __invoke(string $guild, string $member): Response { /* ... */ }
}
```

A handler takes what it asks for, in whatever order: a parameter typed as
`Request` is given the request, and every other one is given the segment of the
same name.

A segment matches anything but a slash, so `/guilds/{guild}` does not swallow
`/guilds/1/members`. A literal path always wins over one with a segment, so
`/guilds/mine` works whichever order the two are discovered in.

## Answers

```php
Response::json(['ok' => true]);
Response::json($body, 201, ['X-Trace' => $id]);
Response::text('pong');
Response::noContent();          // 204
Response::notFound();           // 404
Response::badRequest('Expected JSON');
Response::unauthorized();
```

A handler returning nothing answers `204`. A handler that throws is logged and
answers `500` with nothing else — an exception message is not something to hand
to whoever is on the other end of the socket.

A path that exists under a different method answers `405`, not `404`: the
difference is the whole of "you asked wrongly" versus "there is nothing here",
and it is what somebody debugging a webhook needs to see.

## Reading a request

```php
$request->method();              // 'POST'
$request->path();                // '/webhook'
$request->parameter('guild');    // a named segment
$request->query('since');        // a query string value
$request->header('X-Signature');
$request->body();                // the raw body
$request->json();                // decoded, or null when it is not JSON
$request->psr;                   // the PSR-7 request, for everything else
```

`json()` answers null rather than throwing: a body that is not what was expected
is an ordinary thing to receive from the open internet, and a handler replying
`400` reads better than one wrapped in a `try`.

## Configuration

`app/config/http.config.php`:

```php
use Tempcord\Plugins\Http\HttpConfig;

return new HttpConfig(
    port: 8080,
    host: '127.0.0.1',
    enabled: true,
);
```

**It binds to loopback by default, on purpose.** A bot process is not a web
server, and a route that reaches the live gateway connection is not something to
put on the open internet by accident. Put a reverse proxy in front of it, or set
the host deliberately, knowing what is behind it.

Nothing listens when no routes were discovered, and a port already taken is
logged rather than thrown — a gateway that will not start because a stale
process holds 8080 is a bad trade.

## Requirements

- PHP 8.5
- Tempcord Framework >= 0.12

## License

MIT
