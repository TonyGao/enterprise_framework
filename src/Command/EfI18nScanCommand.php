<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * 国际化完整性扫描 / Internationalization completeness scan.
 *
 * 扫描并报告：
 * - 生产模板（templates/admin/**，不含 test/）中的硬编码中文
 * - 前端 JS（public/sunui/admin/**）中的硬编码中文
 * - 模板中 trans 使用的 key 在 messages.zh_CN/en 中缺失的情况
 * - 前端 t('key') 使用的 key 在 JS 字典中缺失的情况
 */
#[AsCommand(
    name: 'ef:i18n-scan',
    description: '扫描国际化完整性（硬编码中文、缺失翻译 key）/ Scan i18n completeness',
)]
class EfI18nScanCommand extends Command
{
    private string $projectDir;

    public function __construct(
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%kernel.project_dir%')] string $projectDir,
    ) {
        parent::__construct();
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, '输出 JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $asJson = (bool) $input->getOption('json');

        $report = [
            'twig_hardcoded_cjk' => $this->scanTwigCjk(),
            'js_hardcoded_cjk' => $this->scanJsCjk(),
            'php_hardcoded_cjk' => $this->scanPhpCjk(),
            'missing_twig_trans_keys' => $this->scanMissingTwigKeys(),
            'missing_js_t_keys' => $this->scanMissingJsKeys(),
        ];

        if ($asJson) {
            $output->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        $io->title('国际化完整性扫描 / i18n Completeness Scan');

        $io->section('生产模板硬编码中文 / Hardcoded CJK in production templates');
        foreach ($report['twig_hardcoded_cjk'] as $item) {
            $io->writeln(sprintf('  %-70s %3d', $item['file'], $item['count']));
        }

        $io->section('前端 JS 硬编码中文 / Hardcoded CJK in JS');
        foreach ($report['js_hardcoded_cjk'] as $item) {
            $io->writeln(sprintf('  %-70s %3d', $item['file'], $item['count']));
        }

        $io->section('PHP 硬编码中文(字符数) / Hardcoded CJK in PHP');
        foreach ($report['php_hardcoded_cjk'] as $item) {
            $io->writeln(sprintf('  %-70s %3d', $item['file'], $item['count']));
        }

        $io->section('模板缺失翻译 key / Missing trans keys (used but not in both catalogs)');
        foreach ($report['missing_twig_trans_keys'] as $k => $missingIn) {
            $io->writeln(sprintf('  %-60s 缺: %s', $k, implode(',', $missingIn)));
        }
        $io->writeln(sprintf('  缺失 key 总数: %d', count($report['missing_twig_trans_keys'])));

        $io->section('JS t() 缺失 key / Missing JS t() keys');
        foreach ($report['missing_js_t_keys'] as $k => $missingIn) {
            $io->writeln(sprintf('  %-60s 缺: %s', $k, implode(',', $missingIn)));
        }
        $io->writeln(sprintf('  缺失 JS key 总数: %d', count($report['missing_js_t_keys'])));

        return Command::SUCCESS;
    }

    /** 扫描生产模板中的硬编码中文 / Scan production templates for hardcoded CJK. */
    private function scanTwigCjk(): array
    {
        $result = [];
        $base = $this->projectDir . '/templates/admin';
        foreach ($this->files($base, 'twig') as $file) {
            $rel = str_replace($this->projectDir . '/', '', $file);
            $content = (string) file_get_contents($file);
            // 去除被 trans 包裹的行：{{ '...'|trans }} 或 |trans(...) —— 已国际化的不计
            $cleaned = preg_replace('/\{\{.*?\|trans(?::.*?)?(?:\(.*?\))?.*?\}\}/s', '', $content);
            // 去除 {% verbatim %} 块（原样输出内容，如邮件示例模板，由 JS 按 locale 切换）/
            // strip {% verbatim %} blocks (raw literal content, e.g. email sample templates switched by JS locale)
            $cleaned = preg_replace('/\{%\s*verbatim\s*%\}.*?\{%\s*endverbatim\s*%\}/s', '', $cleaned);
            // 去除注释（HTML {# #}、<!-- -->、JS // 与 /* */）——注释不算用户文案
            $cleaned = preg_replace('/\{#.*?#\}/s', '', $cleaned);
            // ai_chat 中解析旧消息前缀的正则里的中文是历史数据格式标记，非 UI 文案 /
            // Chinese inside ai_chat legacy-message regexes are history-format markers, not UI text
            if (str_ends_with($file, '/admin/ai_chat.html.twig')) {
                $cleaned = preg_replace('/\^\\\\\[当前选中元素[^\n]*/', '', $cleaned);
            }
            $cleaned = preg_replace('/<!--.*?-->/s', '', $cleaned);
            $cleaned = preg_replace('/\/\/[^\n]*|\/\*.*?\*\//s', '', $cleaned);
            $count = preg_match_all('/[\x{4e00}-\x{9fff}]+/u', $cleaned, $m);
            if ($count > 0) {
                $result[] = ['file' => $rel, 'count' => $count];
            }
        }
        usort($result, fn ($a, $b) => $b['count'] <=> $a['count']);
        return $result;
    }

    /** 扫描 JS 硬编码中文 / Scan JS for hardcoded CJK. */
    private function scanJsCjk(): array
    {
        $result = [];
        $base = $this->projectDir . '/public/sunui/admin';
        foreach ($this->files($base, 'js') as $file) {
            $rel = str_replace($this->projectDir . '/', '', $file);
            $content = (string) file_get_contents($file);
            $cleaned = preg_replace('/t\(\s*[\'"][^\'"]+[\'"]/', '', $content);
            // 排除 JS 注释（// 与 /* */）以及模板字符串中的 HTML 注释（<!-- -->）/
            // strip JS comments and HTML comments embedded in template strings
            $cleaned = preg_replace('/\/\/[^\n]*|\/\*.*?\*\//s', '', $cleaned);
            $cleaned = preg_replace('/<!--.*?-->/s', '', $cleaned);
            // 多语种字体预览样例（font_selector.js）为刻意内容，非硬编码中文 /
            // multilingual font preview samples in font_selector.js are intentional, not hardcoded CJK
            if (str_ends_with($file, '/platform/font_selector.js')) {
                $cleaned = preg_replace('/^\s*(chineseText|englishText)\s*=\s*\'.{2,40}\'.*$/m', '', $cleaned);
            }
            $count = preg_match_all('/[\x{4e00}-\x{9fff}]+/u', $cleaned, $m);
            if ($count > 0) {
                $result[] = ['file' => $rel, 'count' => $count];
            }
        }
        usort($result, fn ($a, $b) => $b['count'] <=> $a['count']);
        return $result;
    }

    /** 扫描 PHP 硬编码中文（字符数） / Scan PHP for hardcoded CJK. */
    private function scanPhpCjk(): array
    {
        $result = [];
        foreach (['src'] as $dir) {
            $base = $this->projectDir . '/' . $dir;
            foreach ($this->files($base, 'php') as $file) {
                $rel = str_replace($this->projectDir . '/', '', $file);
                $content = (string) file_get_contents($file);
                $count = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $content);
                if ($count > 0) {
                    $result[] = ['file' => $rel, 'count' => $count];
                }
            }
        }
        usort($result, fn ($a, $b) => $b['count'] <=> $a['count']);
        return array_slice($result, 0, 40);
    }

    /** 扫描模板 trans key 是否在双语文件都存在 / Scan trans keys missing from catalogs. */
    private function scanMissingTwigKeys(): array
    {
        $used = [];
        $base = $this->projectDir . '/templates';
        foreach ($this->files($base, 'twig') as $file) {
            $content = (string) file_get_contents($file);
            // {{ 'key'|trans }} / {{ "key"|trans(...) }}
            preg_match_all("/['\"]([^'\"]{1,80})['\"]\s*\|trans\b/", $content, $m);
            foreach ($m[1] as $key) {
                $used[$key] = true;
            }
        }

        $zh = $this->flattenKeys(Yaml::parseFile($this->projectDir . '/translations/messages.zh_CN.yaml'));
        $en = $this->flattenKeys(Yaml::parseFile($this->projectDir . '/translations/messages.en.yaml'));

        $missing = [];
        foreach (array_keys($used) as $key) {
            $miss = [];
            if (!isset($zh[$key])) {
                $miss[] = 'zh_CN';
            }
            if (!isset($en[$key])) {
                $miss[] = 'en';
            }
            if ($miss) {
                $missing[$key] = $miss;
            }
        }
        ksort($missing);
        return $missing;
    }

    /** 扫描 JS t('key') 是否在 JS 字典存在 / Scan JS t() keys missing. */
    private function scanMissingJsKeys(): array
    {
        $used = [];
        // 前端 JS 文件 / frontend JS files
        $base = $this->projectDir . '/public/sunui/admin';
        foreach ($this->files($base, 'js') as $file) {
            $content = (string) file_get_contents($file);
            preg_match_all('/\bt\(\s*[\'"]([^\'"]+)[\'"]/', $content, $m);
            foreach ($m[1] as $key) {
                $used[$key] = true;
            }
        }
        // Twig 模板内联 <script> 中的 t('key')（此前为扫描盲区）/
        // t('key') inside Twig inline <script> blocks (previously a scan blind spot)
        foreach ($this->files($this->projectDir . '/templates', 'twig') as $file) {
            $content = (string) file_get_contents($file);
            preg_match_all('/\bt\(\s*[\'"]([^\'"]+)[\'"]/', $content, $m);
            foreach ($m[1] as $key) {
                $used[$key] = true;
            }
        }

        $zh = $this->flattenKeys(Yaml::parseFile($this->projectDir . '/translations/messages.zh_CN.yaml'));
        $en = $this->flattenKeys(Yaml::parseFile($this->projectDir . '/translations/messages.en.yaml'));

        $missing = [];
        foreach (array_keys($used) as $key) {
            $miss = [];
            if (!isset($zh[$key])) {
                $miss[] = 'zh_CN';
            }
            if (!isset($en[$key])) {
                $miss[] = 'en';
            }
            if ($miss) {
                $missing[$key] = $miss;
            }
        }
        ksort($missing);
        return $missing;
    }

    /** 递归收集文件 / Recursively collect files. */
    private function files(string $dir, string $ext): array
    {
        $out = [];
        if (!is_dir($dir)) {
            return $out;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === $ext) {
                // 跳过 test/ 与 views/ 演示模板 / skip test & demo view templates
                $p = $file->getPathname();
                if ($ext === 'twig' && (str_contains($p, '/test/') || str_contains($p, '/views/'))) {
                    continue;
                }
                $out[] = $p;
            }
        }
        return $out;
    }

    /** 扁平化 YAML 翻译键 / Flatten YAML translation keys. */
    private function flattenKeys(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
            if (is_array($v)) {
                $out += $this->flattenKeys($v, $key);
            } else {
                $out[$key] = true;
            }
        }
        return $out;
    }
}
