<?php

namespace App\Service\Platform\View;

/**
 * 版本号工具：统一解析/校验/递增 {major}_{minor} 版本号。
 * 供视图及未来其他资产（模型/审批流/报表）复用。
 */
final class VersionNumber
{
    public static function isValid(string $version): bool
    {
        return preg_match('/^\d+_\d+$/', $version) === 1;
    }

    /**
     * @return array{int, int} [major, minor]
     */
    public static function parse(string $version): array
    {
        $parts = array_pad(explode('_', $version, 2), 2, '0');
        return [(int) $parts[0], (int) $parts[1]];
    }

    public static function incrementMinor(string $version): string
    {
        [$major, $minor] = self::parse($version);
        return $major . '_' . ($minor + 1);
    }

    public static function incrementMajor(string $version): string
    {
        [$major] = self::parse($version);
        return ($major + 1) . '_0';
    }
}
