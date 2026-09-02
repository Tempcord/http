<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Discoveries;

use Tempcord\Plugins\Http\Attributes\Route;
use Tempcord\Plugins\Http\Compiler\RouteCompiler;
use Tempcord\Plugins\Http\Router;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

final class RoutesDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly Router $router,
        private readonly RouteCompiler $compiler = new RouteCompiler(),
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        foreach ($class->getAttributes(Route::class) as $route) {
            $this->discoveryItems->add($location, $this->compiler->compile($class, $route));
        }
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as $route) {
            $this->router->add($route);
        }
    }
}
