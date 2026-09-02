<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The request, with the awkward parts of PSR-7 answered.
 *
 * A thin wrapper rather than a replacement: the underlying request is right
 * there for anything this does not cover, and swapping the server underneath
 * would not change what a handler sees.
 */
final readonly class Request
{
    /**
     * @param array<string, string> $parameters the segments the path named
     */
    public function __construct(
        public ServerRequestInterface $psr,
        public array $parameters = [],
    ) {}

    public function method(): string
    {
        return $this->psr->getMethod();
    }

    public function path(): string
    {
        return $this->psr->getUri()->getPath();
    }

    public function parameter(string $name): ?string
    {
        return $this->parameters[$name] ?? null;
    }

    /**
     * A query string value, or null when it was not given.
     */
    public function query(string $name): ?string
    {
        $value = $this->psr->getQueryParams()[$name] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    public function header(string $name): ?string
    {
        return $this->psr->hasHeader($name) ? $this->psr->getHeaderLine($name) : null;
    }

    public function body(): string
    {
        return (string) $this->psr->getBody();
    }

    /**
     * The body read as JSON, or null when it is not JSON at all.
     *
     * Null rather than an exception: a body that is not what was expected is an
     * ordinary thing to receive from the open internet, and a handler answering
     * 400 reads better than one wrapped in a try.
     *
     * @return array<mixed>|null
     */
    public function json(): ?array
    {
        $decoded = json_decode($this->body(), associative: true);

        return is_array($decoded) ? $decoded : null;
    }
}
