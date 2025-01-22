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

namespace MonsieurBiz\SyliusThemeCompanionPlugin\TaskRunner;

use MonsieurBiz\SyliusThemeCompanionPlugin\Process\ProcessOutputAwareTrait;
use MonsieurBiz\SyliusThemeCompanionPlugin\TaskRunner\SassCompilerRunner\SassCompiler;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

#[AutoconfigureTag('monsieurbiz_theme_companion.task_runner')]
class SassCompilerRunner implements TaskRunnerInterface
{
    use ProcessOutputAwareTrait;

    public const string IDENTIFIER = 'sass';

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        readonly private string $projectDir,
    ) {
    }

    public static function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function process(string $input, string $output, ?array $config = null): bool
    {
        $sassCompiler = new SassCompiler(
            [$input],
            $output,
            $this->projectDir,
            null,
            $config
        );

        $sassCompiler->setFileInput($input);
        $sassCompiler->setFileOutput($output);

        /** @var Process $process */
        $process = $sassCompiler->runBuild(false);

        foreach ($process as $data) {
            $this->getProcessOutputHandler()?->handle($data);
        }

        if (!$process->isSuccessful()) {
            return false;
        }

        return true;
    }
}
