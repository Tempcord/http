<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Http;

/**
 * Something that runs before a handler, and may decide it never runs.
 *
 * Built by the container, so a middleware may take whatever it needs — the
 * configuration holding a shared secret, a clock, a logger.
 *
 * Returning an answer instead of calling $next stops the request there, which
 * is the whole point for anything that checks a caller's right to be asking:
 * the handler is never reached, so it cannot half-do the work it was asked for.
 */
interface Middleware
{
    /**
     * @param callable(Request): Response $next
     */
    public function __invoke(Request $request, callable $next): Response;
}
