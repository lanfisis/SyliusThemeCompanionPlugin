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

namespace MonsieurBiz\SyliusThemeCompanionPlugin\DependencyInjection;

use Composer\InstalledVersions as ComposerRuntime;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class MonsieurBizSyliusThemeCompanionExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function prepend(ContainerBuilder $container): void
    {
        $paths = $this->getThemePackagesPaths();
        if (0 === \count($paths)) {
            return;
        }

        $bundles = $container->getParameter('kernel.bundles');
        if (false === \is_array($bundles) || !isset($bundles['SyliusThemeBundle'])) {
            return;
        }

        $container->prependExtensionConfig('sylius_theme', ['legacy_mode' => true]);
        foreach ($paths as $path) {
            $container->prependExtensionConfig('sylius_theme', ['sources' => ['filesystem' => ['directories' => [$path]]]]);
        }
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function getThemePackagesPaths(): array
    {
        $packages = [];
        foreach ($this->getSyliusPluginPackages() as $packageName) {
            $path = ComposerRuntime::getInstallPath($packageName);
            if (false === file_exists($path . '/composer.json')) {
                continue;
            }
            $composerJson = json_decode(file_get_contents($path . '/composer.json') ?: '', true);
            if (isset($composerJson['extra']['sylius-theme'])) {
                $packages[] = $path;
            }
        }

        return $packages;
    }

    private function getSyliusPluginPackages(): array
    {
        return array_unique(ComposerRuntime::getInstalledPackagesByType('sylius-plugin'));
    }
}
