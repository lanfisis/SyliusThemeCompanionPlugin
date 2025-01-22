<?php

/*
 * This file is part of Monsieur Biz' Theme Companion plugin for Sylius.
 *
 * (c) Monsieur Biz <sylius@monsieurbiz.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace app;

use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\fs;
use function Castor\io;
use function Castor\run;
use function Castor\yaml_parse;

const CONFIG_FILENAME = '.apps.yaml';
const TWIG_CONFIG_PATH = 'config/packages/twig.yaml';

const DEFAULT_ROOT_DIR = 'apps';
const DEFAULT_DEFAULT_PROJECT_NAME = 'sylius-plugin';
const DEFAULT_NODE_VERSION = '16';
const DEFAULT_FIXTURE_SUITE = 'default';

const SYMFONY_BINARY = ['/opt/homebrew/bin/symfony'];

const CONSOLE_BINARY = [...SYMFONY_BINARY, 'console'];
const COMPOSER_BINARY = [...SYMFONY_BINARY, 'composer'];
const DOCKER_COMPOSE_BINARY = ['docker', 'compose'];
const NVM_BINARY = ['/opt/homebrew/bin/n'];

const YARN_BINARY = ['yarn'];

#[AsTask()]
function install(): void
{
    io()->title('Installing test apps');

    $config = getConfig();
    $rootDir = $config['apps_root_dir'] ?? DEFAULT_ROOT_DIR;
    $packages = $config['local_packages'] ?? [];
    $apps = $config['apps'] ?? [];
    initPackages($packages);
    initApps($rootDir, $packages, $apps);
}

#[AsTask()]
function up(): void
{
    io()->title('Up test apps');

    $config = getConfig();
    $dockerProjectName = $config['docker_project_name'] ?? DEFAULT_DEFAULT_PROJECT_NAME;
    $rootDir = $config['apps_root_dir'] ?? DEFAULT_ROOT_DIR;
    $symlinks = $config['symlinks'] ?? [];
    $apps = $config['apps'] ?? [];

    linkFiles($symlinks);
    dockerUp($dockerProjectName);
    foreach ($apps['instances'] as $name => $app) {
        serverStart($name, $app, $rootDir);
    }
}

#[AsTask()]
function stop(): void
{
    io()->title('Stop test apps');

    $config = getConfig();
    $dockerProjectName = $config['docker_project_name'] ?? DEFAULT_DEFAULT_PROJECT_NAME;
    $rootDir = $config['apps_root_dir'] ?? DEFAULT_ROOT_DIR;
    $apps = $config['apps'] ?? [];

    dockerStop($dockerProjectName);
    foreach ($apps['instances'] as $name => $app) {
        serverStop($name, $app, $rootDir);
    }
}

#[AsTask()]
function down(): void
{
    stop();
    io()->title('Down test apps');

    $config = getConfig();
    $dockerProjectName = $config['docker_project_name'] ?? DEFAULT_DEFAULT_PROJECT_NAME;

    dockerDown($dockerProjectName);
}

#[AsTask()]
function cleanCache(): void
{
    io()->title('Clean test apps cache');

    $config = getConfig();
    $rootDir = $config['apps_root_dir'] ?? DEFAULT_ROOT_DIR;
    $apps = $config['apps'] ?? [];

    foreach ($apps['instances'] as $name => $app) {
        cacheClean($name, $app, $rootDir);
    }
}

function initApps(string $rootDir, array $packages, array $apps): void
{
    io()->section('Initializing local apps');
    if (!fs()->exists($rootDir)) {
        fs()->mkdir($rootDir);
        io()->success(\sprintf('Apps root dir "./%s" has been created', $rootDir));
    } else {
        io()->info(\sprintf('Apps root dir "./%s" exists', $rootDir));
    }

    $removes = $apps['removes'] ?? [];
    $symlinks = $apps['symlinks'] ?? [];
    foreach ($apps['instances'] as $name => $app) {
        createApp($name, $app, $rootDir);
        removesAppFiles($name, $app, $rootDir, $removes);
        linkAppFiles($name, $app, $rootDir, $symlinks);
        addLocalEnv($name, $app, $rootDir);
        setupApp($name, $app, $packages, $rootDir);
        cleanTwigIntl($name, $app, $rootDir);
        installApp($name, $app, $packages, $rootDir);
        attachDomain($name, $app, $rootDir);
    }
    up();
    foreach ($apps['instances'] as $name => $app) {
        initDatabase($name, $app, $rootDir);
        buildAssets($name, $app, $rootDir);
        runFixtures($name, $app, $rootDir);
        setupMessenger($name, $app, $rootDir);
        installThemes($name, $app, $rootDir);
        buildThemes($name, $app, $rootDir);
    }
}

function addLocalEnv(string $name, mixed $app, string $rootDir): void
{
    io()->section(\sprintf('Add local .env values for app %s', $name));

    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    fs()->appendToFile($appRootDir . '/.env.local', 'DATABASE_URL=mysql://root@127.0.0.1:56511/sylius_' . $name . '?serverVersion=8&charset=utf8mb4');

    io()->success(\sprintf('Local .env values set for app %s', $name));
}

function setupMessenger(string $name, mixed $app, string $rootDir): void
{
    io()->section(\sprintf('Setup messenger for app %s', $name));

    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $context = (new Context())->withWorkingDirectory($appRootDir);
    runConsole(['messenger:setup-transports'], $context);

    io()->success(\sprintf('Messenger have been setup for app %s', $name));
}

function installThemes(string $name, mixed $app, string $rootDir): void
{
    io()->section(\sprintf('Install themes for app %s', $name));

    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $context = (new Context())->withWorkingDirectory($appRootDir);
    runConsole(['theme:install', '--all'], $context);

    io()->success(\sprintf('Themes have been installed for app %s', $name));
}

function buildThemes(string $name, mixed $app, string $rootDir): void
{
    io()->section(\sprintf('Build themes for app %s', $name));

    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $context = (new Context())->withWorkingDirectory($appRootDir);
    runConsole(['theme:build', '--all'], $context);

    io()->success(\sprintf('Themes have been build for app %s', $name));
}

function runFixtures(string $name, mixed $app, string $rootDir): void
{
    io()->section(\sprintf('Run fixtures for app %s', $name));

    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $context = (new Context())->withWorkingDirectory($appRootDir);
    runConsole(['sylius:fixtures:load', '-n', $app['fixture_suite'] ?? DEFAULT_FIXTURE_SUITE], $context);

    io()->success(\sprintf('Fixture have been run for app %s', $name));
}

function buildAssets(string $name, mixed $app, string $rootDir): void
{
    io()->section(\sprintf('Build assets for app %s', $name));

    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $context = (new Context())->withWorkingDirectory($appRootDir);
    $nodeVersion = $app['node_version'] ?? DEFAULT_NODE_VERSION;
    io()->info(\sprintf('Node %s will be use', $nodeVersion));
    runNode(['exec', $nodeVersion, 'yarn', 'install'], $context);
    runNode(['exec', $nodeVersion, 'yarn', 'encore', 'dev'], $context);
    runConsole(['assets:install', '--symlink'], $context);
    runConsole(['sylius:install:assets'], $context);
    runConsole(['sylius:theme:assets:install', '--symlink'], $context);

    io()->success(\sprintf('Assets have been built for app %s', $name));
}

function initDatabase(string $name, mixed $app, string $rootDir): void
{
    io()->section(\sprintf('Init database for app %s', $name));

    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $context = (new Context())->withWorkingDirectory($appRootDir);
    runConsole(['doctrine:database:drop', '--if-exists', '--force'], $context);
    runConsole(['doctrine:database:create', '--if-not-exists'], $context);
    runConsole(['doctrine:migration:migrate', '-n'], $context);

    io()->success(\sprintf('Databas have been init for app %s', $name));
}

function attachDomain(string $name, mixed $app, string $rootDir): void
{
    io()->section(\sprintf('Attach domain for app %s', $name));

    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $context = (new Context())->withWorkingDirectory($appRootDir);
    $domain = $app['domain'] ?? $name;
    runSymfony(['local:proxy:domain:attach', $domain], $context);

    io()->success(\sprintf('Domain %s.wip has been attached to app %s', $domain, $name));
}

function dockerUp(string $projectName): void
{
    runDockerCompose(['-p', $projectName, 'up', '-d']);

    io()->success(\sprintf('Docker is up for project %s', $projectName));
}

function dockerStop(string $projectName): void
{
    runDockerCompose(['-p', $projectName, 'stop']);

    io()->success(\sprintf('Docker is stopped for project %s', $projectName));
}

function dockerDown(string $projectName): void
{
    runDockerCompose(['-p', $projectName, 'down']);

    io()->success(\sprintf('Docker is down for project %s', $projectName));
}

function serverStart(string $name, mixed $app, string $rootDir): void
{
    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $context = (new Context())->withWorkingDirectory($appRootDir);
    runSymfony(['local:server:start', '-d'], $context);

    io()->success(\sprintf('Symfony server is started for app %s', $name));
}
function serverStop(string $name, mixed $app, string $rootDir): void
{
    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $context = (new Context())->withWorkingDirectory($appRootDir);
    runSymfony(['local:server:stop'], $context);

    io()->success(\sprintf('Symfony server is stopped for app %s', $name));
}

function cacheClean(string $name, mixed $app, string $rootDir): void
{
    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    fs()->remove($appRootDir . '/var/cache');

    io()->success(\sprintf('Cache have been cleaned for app %s', $name));
}

function linkAppFiles(string $name, array $app, string $rootDir, array $symlinks): void
{
    io()->section(\sprintf('Links files for app %s', $name));
    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $symlinks = [...$symlinks, ...($app['symlinks'] ?? [])];

    foreach ($symlinks as $symlink) {
        $source = __DIR__ . '/' . $symlink['source'];
        $target = __DIR__ . '/' . $appRootDir . '/' . $symlink['target'];
        if (null !== fs()->readlink($target)) {
            fs()->remove($target);
        }
        fs()->symlink($source, $target, true);
        io()->info(\sprintf('Symlink has been created from %s to %s in app %s', $symlink['target'], $symlink['source'], $name));
    }

    io()->success(\sprintf('Files have been linked for app %s', $name));
}
function linkFiles(array $symlinks): void
{
    foreach ($symlinks as $symlink) {
        $source = $symlink['source'];
        $target = $symlink['target'];
        if (null !== fs()->readlink($target)) {
            fs()->remove($target);
        }
        fs()->symlink($source, $target, true);
        io()->info(\sprintf('Symlink has been created from %s to %s', $symlink['target'], $symlink['source']));
    }

    io()->success('Files have been linked');
}

function removesAppFiles(string $name, array $app, string $rootDir, array $removes): void
{
    io()->section(\sprintf('Remove files for app %s', $name));
    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $removes = [...$removes, ...($app['removes'] ?? [])];
    foreach ($removes as $remove) {
        $path = $appRootDir . '/' . $remove;
        if (!fs()->exists($path)) {
            continue;
        }
        fs()->remove($path);
        io()->info(\sprintf('File %s has been removed from app %s', $path, $name));
    }

    io()->success(\sprintf('Files have been removed for app %s', $name));
}

function cleanTwigIntl(string $name, mixed $app, string $rootDir): void
{
    io()->section(\sprintf('Clean Twig Intl for app %s', $name));
    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $twigConfigPath = \sprintf('%s/%s', $appRootDir, TWIG_CONFIG_PATH);

    if (false === fs()->exists($twigConfigPath)) {
        return;
    }
    $content = file_get_contents($twigConfigPath);

    if (false === str_contains($content, 'Twig\\Extra\\Intl\\IntlExtension')) {
        return;
    }

    $newContent = str_replace(
        'Twig\\Extra\\Intl\\IntlExtension',
        '#Twig\\Extra\\Intl\\IntlExtension',
        $content
    );

    file_put_contents($twigConfigPath, $newContent);

    io()->success(\sprintf('Twig Intl config has been disabled for app %s', $name));
}

function setupApp(string $name, array $app, array $packages, string $rootDir): void
{
    io()->section(\sprintf('Setup app %s', $name));
    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $context = (new Context())->withWorkingDirectory($appRootDir);

    foreach ($app['packages'] ?? $packages as $name => $package) {
        $name = \is_string($package) ? $package : $name;
        $package = \is_string($package) ? $packages[$package] : $package;
        $label = explode('/', $name)[1];
        $url = fs()->makePathRelative(__DIR__ . '/' . $package['path'], __DIR__ . '/' . $appRootDir);
        runComposer(['config', \sprintf('repositories.%s', $label), \sprintf('{"type": "path", "url": "%s"}', $url)], $context);
    }
    io()->success(\sprintf('Local packages for app %s has been initialized', $name));

    runComposer(['config', 'extra.symfony.allow-contrib', 'true'], $context);
    runComposer(['config', 'minimum-stability', 'dev'], $context);
    runComposer(['config', '--no-plugins', 'allow-plugins', 'true'], $context);
    runComposer(['config', '--no-plugins', '--json', 'extra.symfony.endpoint', '["https://api.github.com/repos/monsieurbiz/symfony-recipes/contents/index.json?ref=flex/master","flex://defaults"]'], $context);

    io()->success(\sprintf('Composer configuration for app %s has been added to composer.json file', $name));
}

function installApp(string $name, array $app, array $packages, string $rootDir): void
{
    io()->section(\sprintf('Install Sylius app %s', $name));
    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    $context = (new Context())->withWorkingDirectory($appRootDir);

    runComposer(['require', '--no-scripts', '--no-progress', \sprintf('sylius/sylius=~%s', $app['sylius_version'])], $context);

    foreach ($app['packages'] ?? $packages as $name => $package) {
        $name = \is_string($package) ? $package : $name;
        runComposer(['require', '--no-progress', \sprintf('%s=*@dev', $name)], $context);
    }

    io()->success(\sprintf('App %s has been setup', $name));
}

function createApp(string $name, array $app, string $rootDir): void
{
    io()->section(\sprintf('Create Sylius app %s', $name));
    $appRootDir = $rootDir . '/' . ($app['root_dir'] ?? $name);
    if (fs()->exists($appRootDir)) {
        $erase = io()->confirm(\sprintf('App %s exists. Would you delete it and create it again?', $name));
        if (!$erase) {
            io()->warning(\sprintf('App %s has not been recreate', $name));

            return;
        }
        fs()->remove($appRootDir);
    }

    runComposer([
        'create-project',
        '--no-interaction',
        '--prefer-dist',
        '--no-scripts',
        '--no-progress',
        '--no-install',
        'sylius/sylius-standard=~' . $app['sylius_version'],
        $appRootDir,
    ]);

    io()->success(\sprintf('Sylius app %s has been created', $name));
}

function initPackages(array $packages): void
{
    io()->section('Initializing local packages');
    foreach ($packages as $name => $package) {
        $name = $package['name'] ?? $name;
        $context = (new Context())->withWorkingDirectory($package['path']);
        if (($package['with_composer_install'] ?? false) === true) {
            runComposer(['install', '--no-interaction', '--no-plugins', '--no-scripts'], $context);
        }

        // io()->success(\sprintf('Local package %s has been initialize', $name));
    }
}

function runComposer(array $command, ?Context $context = null): void
{
    run([...COMPOSER_BINARY, ...$command], context: $context);
}

function runDockerCompose(array $command, ?Context $context = null): void
{
    run([...DOCKER_COMPOSE_BINARY, ...$command], context: $context);
}

function runSymfony(array $command, ?Context $context = null): void
{
    run([...SYMFONY_BINARY, ...$command], context: $context);
}

function runConsole(array $command, ?Context $context = null): void
{
    run([...CONSOLE_BINARY, ...$command], context: $context);
}

function runNode(array $command, ?Context $context = null): void
{
    run([...NVM_BINARY, ...$command], context: $context);
}

function getConfig(): array
{
    if (!fs()->exists(CONFIG_FILENAME)) {
        io()->error('No configuration file found');
    }

    return yaml_parse(file_get_contents(CONFIG_FILENAME));
}
