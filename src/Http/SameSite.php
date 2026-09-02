<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Http;

enum SameSite: string
{
    case Strict = 'Strict';
    case Lax = 'Lax';

    /**
     * Sent with every cross-site request, and refused by browsers unless the
     * cookie is also Secure.
     */
    case None = 'None';
}
