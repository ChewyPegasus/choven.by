<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0o000);
}

// Fresh test database, built by the real migrations (so they are tested too). Set SKIP_DB_RESET=1 to reuse it.
if (!($_SERVER['SKIP_DB_RESET'] ?? $_ENV['SKIP_DB_RESET'] ?? false)) {
    $console = [\PHP_BINARY, dirname(__DIR__).'/bin/console', '--env=test', '--no-interaction', '-q'];
    foreach ([
        ['doctrine:database:drop', '--force', '--if-exists'],
        ['doctrine:database:create'],
        ['doctrine:migrations:migrate', '--allow-no-migration'],
    ] as $command) {
        $process = new Process([...$console, ...$command]);
        $process->setTimeout(300);
        $process->mustRun();
    }
}
