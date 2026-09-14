<?php

namespace Mate;

use PDO;
use Symfony\AI\Mate\Attribute\MateTool;

/**
 * 暴露本项目的 LLM 角色/服务商绑定运行时状态 / Exposes the project's LLM role/provider bindings.
 */
final class EfLlmRolesTool
{
    /**
     * 列出每个 LLM 角色绑定的服务商与模型 / List the provider and model bound to each LLM role.
     */
    #[MateTool(
        name: 'ef-llm-roles',
        title: 'LLM Role Bindings',
        description: 'List each LLM role (vision, reasoning, general, lightweight, embedding) with the provider/model bound to it, read live from the platform_llm_role and platform_llm_provider tables.',
    )]
    public function listRoles(): string
    {
        try {
            $pdo = $this->connect();
        } catch (\Throwable $e) {
            return 'Unable to connect to the application database: '.$e->getMessage();
        }

        $sql = <<<'SQL'
            SELECT r.code, r.label, r.is_enabled,
                   p.name AS provider_name, p.provider AS provider_type, p.model,
                   p.api_endpoint, p.is_enabled AS provider_enabled
            FROM platform_llm_role r
            LEFT JOIN platform_llm_provider p ON p.id = r.provider_id
            ORDER BY r.code
        SQL;

        try {
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return 'Query failed: '.$e->getMessage();
        }

        if ($rows === []) {
            return 'No LLM roles configured (platform_llm_role is empty).';
        }

        $lines = ['LLM role bindings (live DB):', ''];
        foreach ($rows as $row) {
            $lines[] = sprintf(
                '%-12s %s [%s]',
                $row['code'],
                ($row['label'] ?? '') !== '' ? $row['label'] : '-',
                $this->boolish($row['is_enabled'] ?? null) ? 'enabled' : 'disabled'
            );

            if (($row['provider_name'] ?? null) === null) {
                $lines[] = '             -> (no provider bound)';
            } else {
                $lines[] = sprintf(
                    '             -> %s (%s) model=%s%s%s',
                    $row['provider_name'],
                    $row['provider_type'],
                    $row['model'],
                    $this->boolish($row['provider_enabled'] ?? null) ? '' : ' [provider DISABLED]',
                    ($row['api_endpoint'] ?? '') !== '' ? ' @ '.$row['api_endpoint'] : ''
                );
            }
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    private function boolish(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }

    private function connect(): PDO
    {
        $url = $this->databaseUrl();
        if ($url === null) {
            throw new \RuntimeException('DATABASE_URL not found in environment, .env.local or .env');
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \RuntimeException('Cannot parse DATABASE_URL.');
        }

        $scheme = strtolower($parts['scheme']);
        $db = ltrim($parts['path'] ?? '', '/');

        if (in_array($scheme, ['postgres', 'postgresql', 'pgsql'], true)) {
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $parts['host'], $parts['port'] ?? 5432, $db);
        } elseif (in_array($scheme, ['mysql', 'mysqli'], true)) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s', $parts['host'], $parts['port'] ?? 3306, $db);
        } else {
            throw new \RuntimeException('Unsupported database scheme: '.$scheme);
        }

        return new PDO($dsn, $parts['user'] ?? null, $parts['pass'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private function databaseUrl(): ?string
    {
        foreach ([
            $_ENV['DATABASE_URL'] ?? null,
            $_SERVER['DATABASE_URL'] ?? null,
            getenv('DATABASE_URL') ?: null,
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        $root = \dirname(__DIR__, 2);
        foreach (['.env.local', '.env'] as $file) {
            $path = $root.'/'.$file;
            if (!is_file($path)) {
                continue;
            }
            if (preg_match('/^\s*DATABASE_URL\s*=\s*(.+?)\s*$/m', (string) file_get_contents($path), $m) === 1) {
                return trim($m[1], "\"'");
            }
        }

        return null;
    }
}
