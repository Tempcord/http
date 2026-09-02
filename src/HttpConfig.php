<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http;

/**
 * Where the bot listens.
 *
 * Loopback by default, and deliberately: a bot process is not a web server, and
 * a route that reaches the live gateway connection is not something to expose
 * to the open internet by accident. Put a reverse proxy in front of it, or set
 * the host on purpose knowing what is behind it.
 */
final readonly class HttpConfig
{
    public function __construct(
        public int $port = 8080,
        public string $host = '127.0.0.1',
        public bool $enabled = true,
    ) {}

    public function address(): string
    {
        return $this->host . ':' . $this->port;
    }
}
