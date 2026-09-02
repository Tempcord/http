<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Http;

use React\Http\Message\Response as ReactResponse;

/**
 * What to answer with.
 *
 * Named constructors rather than a builder: an answer is nearly always one of
 * a handful of shapes, and the ones that are not can be built from the react
 * response directly.
 */
final readonly class Response
{
    /**
     * @param array<string, string> $headers
     * @param list<Cookie> $cookies
     */
    private function __construct(
        public int $status,
        public string $body,
        public array $headers,
        public array $cookies = [],
    ) {}

    /**
     * The same answer, with a cookie set on it.
     *
     * On the response rather than through setcookie(): there are no headers to
     * send in a long-running server, and a cookie belongs to one answer rather
     * than to the process that happened to build it.
     */
    public function withCookie(Cookie $cookie): self
    {
        return new self($this->status, $this->body, $this->headers, [...$this->cookies, $cookie]);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, $this->body, [...$this->headers, $name => $value], $this->cookies);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, ['Content-Type' => 'text/plain; charset=utf-8', ...$headers]);
    }

    /**
     * @param array<mixed> $body
     * @param array<string, string> $headers
     */
    public static function json(array $body, int $status = 200, array $headers = []): self
    {
        return new self(
            $status,
            json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['Content-Type' => 'application/json; charset=utf-8', ...$headers],
        );
    }

    public static function noContent(): self
    {
        return new self(204, '', []);
    }

    public static function notFound(string $message = 'Not Found'): self
    {
        return self::json(['error' => $message], 404);
    }

    public static function badRequest(string $message = 'Bad Request'): self
    {
        return self::json(['error' => $message], 400);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return self::json(['error' => $message], 401);
    }

    public function toReact(): ReactResponse
    {
        $headers = $this->headers;

        /*
         * Set-Cookie is the one header that may legitimately appear more than
         * once, so the values go out as a list rather than being joined.
         */
        if ($this->cookies !== []) {
            $headers['Set-Cookie'] = array_map(
                static fn(Cookie $cookie) => $cookie->header(),
                $this->cookies,
            );
        }

        return new ReactResponse($this->status, $headers, $this->body);
    }
}
