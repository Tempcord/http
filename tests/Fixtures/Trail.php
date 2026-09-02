<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Fixtures;

/**
 * Where the middleware fixtures write down that they ran.
 */
final class Trail
{
    /** @var list<string> */
    public static array $steps = [];

    public static function reset(): void
    {
        self::$steps = [];
    }
}
