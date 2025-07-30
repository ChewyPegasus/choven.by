<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * The application's Kernel.
 *
 * This class serves as the entry point for the Symfony application.
 * It uses the `MicroKernelTrait` to provide a simplified way of configuring
 * the application, making it suitable for microservice architectures or
 * smaller applications.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}