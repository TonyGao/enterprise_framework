<?php

namespace Mate;

use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\Component\Process\Process;

/**
 * 运行项目 i18n 扫描并汇总结果 / Runs the project i18n scan and summarizes the result.
 */
final class EfI18nStatusTool
{
    /**
     * 扫描 i18n 完整性 / Scan i18n completeness.
     */
    #[MateTool(
        name: 'ef-i18n-status',
        title: 'i18n Status',
        description: 'Run the project i18n scan (php bin/console ef:i18n-scan --json) and summarize missing translation keys and hardcoded CJK in Twig and JS.',
    )]
    public function status(): string
    {
        $root = \dirname(__DIR__, 2);
        $process = new Process([\PHP_BINARY, 'bin/console', 'ef:i18n-scan', '--json'], $root, null, null, 120.0);
        $process->run();

        if (!$process->isSuccessful()) {
            return sprintf(
                "ef:i18n-scan failed (exit %s):\n%s",
                $process->getExitCode(),
                trim($process->getErrorOutput() !== '' ? $process->getErrorOutput() : $process->getOutput())
            );
        }

        $data = json_decode($process->getOutput(), true);
        if (!\is_array($data)) {
            return "Could not decode ef:i18n-scan output:\n".trim($process->getOutput());
        }

        $count = static fn (string $key): int => \is_array($data[$key] ?? null) ? \count($data[$key]) : 0;

        $lines = [
            'i18n status (php bin/console ef:i18n-scan):',
            sprintf('  Missing Twig |trans keys : %d', $count('missing_twig_trans_keys')),
            sprintf('  Missing JS t() keys      : %d', $count('missing_js_t_keys')),
            sprintf('  Hardcoded CJK in Twig    : %d', $count('twig_hardcoded_cjk')),
            sprintf('  Hardcoded CJK in JS      : %d', $count('js_hardcoded_cjk')),
            sprintf('  Hardcoded CJK in PHP     : %d (advisory, comments included)', $count('php_hardcoded_cjk')),
        ];

        foreach (['missing_twig_trans_keys', 'missing_js_t_keys'] as $section) {
            if ($count($section) === 0) {
                continue;
            }
            $lines[] = '';
            $lines[] = $section.':';
            foreach (\array_slice($data[$section], 0, 30, true) as $key => $missingIn) {
                $lines[] = sprintf('  - %s (缺: %s)', $key, \is_array($missingIn) ? implode(', ', $missingIn) : (string) $missingIn);
            }
            if ($count($section) > 30) {
                $lines[] = sprintf('  ... and %d more', $count($section) - 30);
            }
        }

        foreach (['twig_hardcoded_cjk', 'js_hardcoded_cjk'] as $section) {
            if ($count($section) === 0) {
                continue;
            }
            $lines[] = '';
            $lines[] = $section.':';
            foreach (\array_slice($data[$section], 0, 20) as $item) {
                $lines[] = '  - '.(string) ($item['file'] ?? json_encode($item, JSON_UNESCAPED_UNICODE)).' ('.($item['count'] ?? '?').')';
            }
        }

        return implode("\n", $lines);
    }
}
