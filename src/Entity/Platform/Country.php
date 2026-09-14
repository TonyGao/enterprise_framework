<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * 国家/地区 / Country
 *
 * 基于 ISO 3166-1 的参考数据，用于多国家/多地区业务的本地化
 * （默认语言、默认币种、电话区号、时区）。
 * Reference data based on ISO 3166-1 for multi-country/region localization
 * (default locale, default currency, phone code, timezone).
 */
#[ORM\Table(name: 'platform_country')]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Country
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private $id;

    /**
     * ISO 3166-1 alpha-2 国家代码（如 CN / US）/
     * ISO 3166-1 alpha-2 country code (e.g. CN / US)
     */
    #[ORM\Column(type: 'string', length: 2, unique: true)]
    private $code;

    /**
     * 英文名称（如 China）/ English name (e.g. China)
     */
    #[ORM\Column(type: 'string', length: 100)]
    private $name;

    /**
     * 默认语言（如 zh_CN）/ Default locale (e.g. zh_CN)
     */
    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'zh_CN'])]
    private $locale = 'zh_CN';

    /**
     * 默认币种代码（如 CNY）/ Default currency code (e.g. CNY)
     */
    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    private $currencyCode;

    /**
     * 国际电话区号（如 +86）/ International phone code (e.g. +86)
     */
    #[ORM\Column(type: 'string', length: 16, nullable: true)]
    private $phoneCode;

    /**
     * 默认时区（如 Asia/Shanghai）/ Default timezone (e.g. Asia/Shanghai)
     */
    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private $timezone;

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

    public function getName(): string
    {
        return (string) $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getLocale(): string
    {
        return (string) $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(?string $currencyCode): self
    {
        $this->currencyCode = $currencyCode ? strtoupper($currencyCode) : null;
        return $this;
    }

    public function getPhoneCode(): ?string
    {
        return $this->phoneCode;
    }

    public function setPhoneCode(?string $phoneCode): self
    {
        $this->phoneCode = $phoneCode;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;
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
