<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * 币种 / Currency
 *
 * 基于 ISO 4217 的参考数据，用于多币种金额的展示与换算基础。
 * Reference data based on ISO 4217, the foundation for multi-currency
 * amounts display and conversion.
 */
#[ORM\Table(name: 'platform_currency')]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Currency
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private $id;

    /**
     * ISO 4217 货币代码（如 CNY / USD）/
     * ISO 4217 currency code (e.g. CNY / USD)
     */
    #[ORM\Column(type: 'string', length: 3, unique: true)]
    private $code;

    /**
     * 货币符号（如 ¥ / $）/ Currency symbol (e.g. ¥ / $)
     */
    #[ORM\Column(type: 'string', length: 8)]
    private $symbol;

    /**
     * 小数位数（如 2）/ Number of decimal places (e.g. 2)
     */
    #[ORM\Column(type: 'integer', options: ['default' => 2])]
    private $decimals = 2;

    /**
     * 是否启用 / Whether enabled
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private $enabled = true;

    /**
     * 初始化 UUID 主键 / Initialize the UUID primary key.
     */
    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return (string) $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtoupper($code);
        return $this;
    }

    public function getSymbol(): string
    {
        return (string) $this->symbol;
    }

    public function setSymbol(string $symbol): self
    {
        $this->symbol = $symbol;
        return $this;
    }

    public function getDecimals(): int
    {
        return (int) $this->decimals;
    }

    public function setDecimals(int $decimals): self
    {
        $this->decimals = $decimals;
        return $this;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }
}
