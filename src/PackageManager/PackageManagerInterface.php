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

namespace MonsieurBiz\SyliusThemeCompanionPlugin\PackageManager;

use MonsieurBiz\SyliusThemeCompanionPlugin\Process\ProcessOutputHandlerAware;

interface PackageManagerInterface extends ProcessOutputHandlerAware
{
    public static function getIdentifier(): string;

    /**
     * @param array<string, mixed> $config
     *
     * @return string[]
     */
    public function install(string $dependenciesFile, ?array $config = null): array;
}
