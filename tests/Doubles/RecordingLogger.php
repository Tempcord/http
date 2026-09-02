<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Tests\Doubles;

use Psr\Log\AbstractLogger;
use Stringable;
use Tempest\Log\Logger;

final class RecordingLogger extends AbstractLogger implements Logger
{
    /** @var list<string> */
    public array $messages = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }

    public function has(string $needle): bool
    {
        foreach ($this->messages as $message) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
