<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ef:chrome:open',
    description: '打开 Chrome 浏览器并启用远程调试和 CDP 代理服务',
)]
class EfChromeOpenCommand extends Command
{
    private const CHROME_PATH = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
    private const PROXY_SCRIPT = __DIR__ . '/../../bin/chrome-cdp-server.mjs';
    private const PROXY_PORT = 9223;

    protected function configure(): void
    {
        $this
            ->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'Chrome 远程调试端口', '9222')
            ->addOption('proxy-port', null, InputOption::VALUE_REQUIRED, 'CDP 代理端口', '9223')
            ->addOption('url', 'u', InputOption::VALUE_OPTIONAL, '启动后导航到此 URL（默认自动检测）');
    }

    private function jsonGet(string $url): mixed
    {
        $ctx = stream_context_create(['http' => ['timeout' => 1, 'method' => 'GET']]);
        $result = @file_get_contents($url, false, $ctx);
        return $result !== false ? json_decode($result, true) : null;
    }

    private function detectAppUrl(): ?string
    {
        $ctx = stream_context_create(['http' => ['timeout' => 1, 'method' => 'GET', 'ignore_errors' => true]]);
        foreach ([8000, 8080, 80] as $p) {
            $test = 'http://localhost:' . $p;
            if (@file_get_contents($test, false, $ctx) !== false) {
                return $test . '/login';
            }
        }
        return null;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $port = $input->getOption('port');

        if (PHP_OS_FAMILY !== 'Darwin') {
            $io->warning('当前系统: ' . PHP_OS_FAMILY . '，此命令仅支持 macOS');
            $io->text('请手动启动: google-chrome --remote-debugging-port=' . $port);
            return Command::FAILURE;
        }

        if (!file_exists(self::CHROME_PATH)) {
            $io->error('未找到 Google Chrome');
            return Command::FAILURE;
        }

        // Check if already running with debugging port
        $version = $this->jsonGet("http://127.0.0.1:$port/json/version");
        if ($version !== null) {
            $io->success("Chrome 远程调试端口 $port 已在运行");
            return Command::SUCCESS;
        }

        $profileDir = '/tmp/chrome-debug-profile-' . $port;

        // Don't pass URL via CLI (unreliable on macOS), launch without it
        $args = [
            self::CHROME_PATH,
            '--remote-debugging-port=' . $port,
            '--new-instance',
            '--no-first-run',
            '--user-data-dir=' . $profileDir,
            '--no-default-browser-check',
        ];

        $cmd = 'nohup ' . implode(' ', array_map('escapeshellarg', $args)) . ' > /dev/null 2>&1 &';
        $io->note('启动 Chrome 远程调试...');
        exec($cmd);

        // Wait for port
        for ($i = 0; $i < 30; $i++) {
            usleep(500000);
            if ($this->jsonGet("http://127.0.0.1:$port/json/version") !== null) {
                break;
            }
        }

        // Wait for a page target, then navigate via CDP
        $navigateUrl = null;
        for ($i = 0; $i < 10; $i++) {
            usleep(500000);
            $targets = $this->jsonGet("http://127.0.0.1:$port/json");
            if (!is_array($targets)) continue;

            foreach ($targets as $t) {
                if (($t['type'] ?? '') !== 'page') continue;
                $wsUrl = $t['webSocketDebuggerUrl'] ?? '';
                if (!$wsUrl) continue;

                // Determine target URL
                $navigateUrl = $input->getOption('url') ?: $this->detectAppUrl();

                if ($navigateUrl) {
                    // Navigate via Node.js one-shot CDP WebSocket
                    $tmpNav = tempnam(sys_get_temp_dir(), 'cdpnav_');
                    file_put_contents($tmpNav, json_encode([
                        'pageWsUrl' => $wsUrl,
                        'method' => 'Page.navigate',
                        'params' => ['url' => $navigateUrl],
                        'timeout' => 5000,
                    ]));
                    $navScript = escapeshellarg(__DIR__ . '/../../bin/chrome-cdp-proxy.mjs');
                    exec("node {$navScript} < " . escapeshellarg($tmpNav) . ' 2>/dev/null &');
                    usleep(800000);
                    @unlink($tmpNav);
                }

                break 2;
            }
        }

        // Start CDP proxy server
        $proxyPort = $input->getOption('proxy-port');
        $proxyScript = realpath(self::PROXY_SCRIPT);

        // Kill any existing proxy on this port
        exec("lsof -ti:{$proxyPort} 2>/dev/null | xargs kill -9 2>/dev/null");
        usleep(200000);

        if ($proxyScript && $navigateUrl) {
            // Find page WS URL for proxy
            $targets = $this->jsonGet("http://127.0.0.1:$port/json");
            $pageWsUrl = '';
            if (is_array($targets)) {
                foreach ($targets as $t) {
                    if (($t['type'] ?? '') === 'page') {
                        $pageWsUrl = $t['webSocketDebuggerUrl'] ?? '';
                        if ($pageWsUrl) break;
                    }
                }
            }

            if ($pageWsUrl) {
                $config = json_encode(['port' => $proxyPort, 'chromeWsUrl' => $pageWsUrl]);
                $script = escapeshellarg($proxyScript);
                $proxyCmd = "echo " . escapeshellarg($config) . " | nohup node {$script} > /dev/null 2>&1 &";
                exec($proxyCmd);
                usleep(500000);

                // Verify proxy started
                $proxyCheck = @fsockopen('127.0.0.1', $proxyPort, $e, $s, 1);
                if ($proxyCheck) {
                    fclose($proxyCheck);
                    $io->success("CDP 代理已启动 (端口 {$proxyPort})");
                } else {
                    $io->warning('CDP 代理启动可能失败，可稍后手动启动');
                }
            }
        }

        if ($navigateUrl) {
            $io->success('Chrome 已启动');
            $io->text(["已打开应用登录页: $navigateUrl", '请在 Chrome 中登录后再使用 AI 助手']);
        } else {
            $io->success('Chrome 已启动');
            $io->text(['Chrome 已就绪，请在地址栏输入应用地址并登录后再使用 AI 助手']);
        }
        return Command::SUCCESS;
    }
}
