<?php

use App\Kernel;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

echo "Bootstrapping test environment...\n";

// Create the kernel in the test environment
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

// Get the EntityManager
$entityManager = $kernel->getContainer()->get('doctrine')->getManager();

// Get all metadata (description of all your Entities)
$metadata = $entityManager->getMetadataFactory()->getAllMetadata();

// Create SchemaTool
$schemaTool = new SchemaTool($entityManager);

echo "Dropping existing schema (if any)...\n";
// First, drop the old schema to avoid errors
$schemaTool->dropSchema($metadata);

echo "Creating new schema for test database...\n";
// Create a new, clean schema
$schemaTool->createSchema($metadata);

echo "Test environment ready.\n";

// Shutdown the kernel so tests can create their own instances
$kernel->shutdown();