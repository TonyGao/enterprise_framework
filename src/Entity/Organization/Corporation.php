<?php

namespace App\Entity\Organization;

use App\Repository\Organization\CorporationRepository;
use App\Annotation\Ef;
use App\Entity\CommonTrait;
use App\Entity\Platform\OptionValue;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;
use App\Entity\Traits\CommonTraitWithoutOrg;

/**
 * isBusinessEntity
 */
#[ORM\Entity(repositoryClass: CorporationRepository::class)]
#[ORM\Table(name: 'org_corporation')]
#[ORM\HasLifecycleCallbacks]
class Corporation
{
	use CommonTraitWithoutOrg;

	#[ORM\Id]
	#[ORM\Column(type: "uuid", unique: true)]
	private $id;

	/**
	 * 集团名称 / Group name
	 */
	#[Ef(group: 'corporation_base_info', isBF: true)]
	#[ORM\Column(type: 'string', length: 180)]
	private $name;

	/**
	 * 简称 / Short name / alias
	 */
	#[Ef(group: 'corporation_base_info', isBF: true)]
	#[ORM\Column(type: "string", length: 80, nullable: true)]
	private $alias;

	/**
	 * 编码 / Code
	 */
	#[Ef(group: 'corporation_base_info', isBF: true)]
	#[ORM\Column(type: "string", length: 180, nullable: true)]
	private $code;

	/**
	 * 描述 / Description
	 */
	#[Ef(group: 'corporation_base_info', isBF: true)]
	#[ORM\Column(type: 'text', nullable: true)]
	private $remark;

	/**
	 * 单位类型 国有企业、国有控股企业、外资企业、合资企业、私营企业
	 */
	#[Ef(group: 'corporation_base_info', isBF: true)]
	#[ORM\OneToOne(targetEntity: 'App\Entity\Platform\OptionValue')]
	#[ORM\JoinColumn(name: "type_id", referencedColumnName: "id", nullable: true)]
	private $type = null;

	/**
	 * 负责人 / Head
	 */
	#[Ef(group: 'corporation_base_info', isBF: true)]
	#[ORM\Column(type: 'string', length: 80, nullable: true)]
	private $president;

	/**
	 * 地址 / Address
	 */
	#[Ef(group: 'corporation_base_info', isBF: true)]
	#[ORM\Column(type: 'string', length: 180, nullable: true)]
	private $address;

	/**
	 * 电话 / Phone
	 */
	#[Ef(group: 'corporation_base_info', isBF: true)]
	#[ORM\Column(type: 'string', length: 40, nullable: true)]
	private $phone;

	/**
	 * 网址 / Website
	 */
	#[Ef(group: 'corporation_base_info', isBF: true)]
	#[ORM\Column(type: "string", length: 180, nullable: true)]
	private $website;

	/**
	 * 邮件地址 / Email
	 */
	#[Ef(group: 'corporation_base_info', isBF: true)]
	#[ORM\Column(type: 'string', length: 180, nullable: true)]
	private $email;

    /**
     * 状态 / Status
     */
    #[Ef(group: 'corporation_base_info', isBF: true)]
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private $state = true;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $sortOrder;

    /**
     * 国家代码（ISO 3166-1）/ Country code (ISO 3166-1)
     */
    #[ORM\Column(type: 'string', length: 2, nullable: true)]
    private $countryCode;

    /**
     * 默认语言 / Default locale
     */
    #[ORM\Column(type: 'string', length: 16, nullable: true)]
    private $defaultLocale;

    /**
     * 默认币种（ISO 4217）/ Default currency (ISO 4217)
     */
    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    private $defaultCurrency;

	public function __construct()
	{
			// 自动生成 UUID
			$this->id = Uuid::v4();
	}

    public function isState(): ?bool
    {
        return $this->state;
    }

    public function setState(bool $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(?int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

	/**
	 * Get the value of id
	 */
	public function getId()
	{
		return $this->id;
	}


	/**
	 * Set the value of id
	 *
	 * @return  self
	 */
	public function setId($id)
	{
		$this->id = $id;

		return $this;
	}


	/**
	 * Get 集团名称
	 */
	public function getName()
	{
		return $this->name;
	}


	/**
	 * Set 集团名称
	 *
	 * @return  self
	 */
	public function setName($name)
	{
		$this->name = $name;

		return $this;
	}


	/**
	 * Get 简称
	 */
	public function getAlias()
	{
		return $this->alias;
	}


	/**
	 * Set 简称
	 *
	 * @return  self
	 */
	public function setAlias($alias)
	{
		$this->alias = $alias;

		return $this;
	}


	/**
	 * Get 编码
	 */
	public function getCode()
	{
		return $this->code;
	}


	/**
	 * Set 编码
	 *
	 * @return  self
	 */
	public function setCode($code)
	{
		$this->code = $code;

		return $this;
	}


	/**
	 * Get 描述
	 */
	public function getRemark()
	{
		return $this->remark;
	}


	/**
	 * Set 描述
	 *
	 * @return  self
	 */
	public function setRemark($remark)
	{
		$this->remark = $remark;

		return $this;
	}


	/**
	 * Get 单位类型 国有企业、国有控股企业、外资企业、合资企业、私营企业
	 */
	public function getType()
	{
		return $this->type;
	}

	public function getTypeId(): ?int
	{
			return $this->type ? $this->type->getId() : null;
	}


	/**
	 * Set 单位类型 国有企业、国有控股企业、外资企业、合资企业、私营企业
	 *
	 * @return  self
	 */
	public function setType($type)
	{
		$this->type = $type;

		return $this;
	}


	/**
	 * Get 负责人
	 */
	public function getPresident()
	{
		return $this->president;
	}


	/**
	 * Set 负责人
	 *
	 * @return  self
	 */
	public function setPresident($president)
	{
		$this->president = $president;

		return $this;
	}


	/**
	 * Get 地址
	 */
	public function getAddress()
	{
		return $this->address;
	}


	/**
	 * Set 地址
	 *
	 * @return  self
	 */
	public function setAddress($address)
	{
		$this->address = $address;

		return $this;
	}


	/**
	 * Get 电话
	 */
	public function getPhone()
	{
		return $this->phone;
	}


	/**
	 * Set 电话
	 *
	 * @return  self
	 */
	public function setPhone($phone)
	{
		$this->phone = $phone;

		return $this;
	}


	/**
	 * Get 网址
	 */
	public function getWebsite()
	{
		return $this->website;
	}


	/**
	 * Set 网址
	 *
	 * @return  self
	 */
	public function setWebsite($website)
	{
		$this->website = $website;

		return $this;
	}


	/**
	 * Get 邮件地址
	 */
	public function getEmail()
	{
		return $this->email;
	}


	/**
	 * Set 邮件地址
	 *
	 * @return  self
	 */
	public function setEmail($email)
	{
		$this->email = $email;

		return $this;
	}

	/**
	 * 国家代码 Getter / Country code getter
	 */
	public function getCountryCode(): ?string
	{
		return $this->countryCode;
	}

	/**
	 * 国家代码 Setter / Country code setter
	 */
	public function setCountryCode(?string $countryCode)
	{
		$this->countryCode = $countryCode ? strtoupper($countryCode) : null;
		return $this;
	}

	/**
	 * 默认语言 Getter / Default locale getter
	 */
	public function getDefaultLocale(): ?string
	{
		return $this->defaultLocale;
	}

	/**
	 * 默认语言 Setter / Default locale setter
	 */
	public function setDefaultLocale(?string $defaultLocale)
	{
		$this->defaultLocale = $defaultLocale;
		return $this;
	}

	/**
	 * 默认币种 Getter / Default currency getter
	 */
	public function getDefaultCurrency(): ?string
	{
		return $this->defaultCurrency;
	}

	/**
	 * 默认币种 Setter / Default currency setter
	 */
	public function setDefaultCurrency(?string $defaultCurrency)
	{
		$this->defaultCurrency = $defaultCurrency ? strtoupper($defaultCurrency) : null;
		return $this;
	}
}
