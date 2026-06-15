<?php

namespace App\Service\Platform;

use App\Service\BaseService;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CodeFormatterService extends BaseService
{
    private array $formatters;

    public function __construct(array $formatters = [])
    {
        $this->formatters = $formatters;
    }

    public function formatFile(string $filePath): void
    {
        // 获取文件扩展名
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        // 根据扩展名选择格式化工具
        $formatterName = $this->getFormatterByExtension($extension);
        
        if (!$formatterName) {
            throw new \InvalidArgumentException(sprintf('No formatter configured for files with extension "%s".', $extension));
        }

        $command = $this->formatters[$formatterName];

        $process = new Process(array_merge(explode(' ', $command), [$filePath]));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // $process->getOutput() 仅用于调试，不应在请求中 echo
    }

    private function getFormatterByExtension(string $extension): ?string
    {
        $extensionMap = [
            'php' => 'php-cs-fixer',
            'js' => 'prettier',
            'css' => 'prettier',
            'html' => 'prettier',
            'twig' => 'prettier',
        ];

        return $extensionMap[$extension] ?? null;
    }
}
