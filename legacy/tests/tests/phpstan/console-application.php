<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$kernel = new Kernel('dev', true);

return new Application($kernel);