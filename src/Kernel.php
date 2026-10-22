<?php

declare(strict_types=1);

namespace App;

use App\HttpCache\AppHttpCache;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    /**
     * The full-page cache skips signed-in users (see App\HttpCache\AppHttpCache).
     */
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('http_cache')) {
            $container->getDefinition('http_cache')->setClass(AppHttpCache::class);
        }
    }
}
