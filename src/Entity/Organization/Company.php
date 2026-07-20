<?php

namespace App\Entity\Organization;

use App\Annotation\Ef;
use App\Entity\Traits\CommonTrait;
use App\Repository\Organization\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Gedmo\Tree\Node as GedmoNode;
use Symfony\Component\Uid\Uuid;

/**
 * 公司, 分组: 公司基本信息, 公司关联信息, 公司管理信息, 公司说明信息
 * isBusinessEntity
 */
#[Gedmo\Tree(type: 'nested')]
#[ORM\Table(name: 'org_company')]
#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Company implements GedmoNode
{
	use CommonTrait;

	#[ORM\Id]
	#[ORM\Column(type: 'uuid', unique: true)]
	private $id;

	/**
	 * 公司名称
	 * @Ef(
	 *     group="company_base_info",
	 *     isBF=true
	 * )
	 */
	#[ORM\Column(type: 'string', length: 180, unique: true)]
	private $name;

	/**
	 * 简称
	 * @Ef(
	 *     group="company_base_info",
	 *     isBF=true
	 * )
	 */
	#[ORM\Column(type: 'string', length: 80, nullable: true)]
	private $alias;

	/**
	 * 编码
	 * @Ef(
	 *    group="company_base_info",
	 *    isBF=true
	 * )
	 */
	#[ORM\Column(type: 'string', length: 180, nullable: true)]
	private $code;

	/**
	 * 描述
	 * @Ef(
	 *     group="company_base_info",
	 *     isBF=true
	 * )
	 */
	#[ORM\Column(type: 'text', nullable: true)]
	private $remark;

	/**
	 * 重复排序号处理: 插入、重复
	 * @Ef(
	 *     group="company_base_info",
	 *     isBF=true
	 * )
	 */
	#[ORM\Column(type: 'string', nullable: true)]
	private $repetitionNumHandling;

	/**
	 * 状态: 启用、停用
	 * @Ef(
	 *     group="company_base_info",
	 *     isBF=true
	 * )
	 */
	#[ORM\Column(type: 'boolean', options: ['default' => 1])]
	private $state = true;

	/**
	 * 独立登录页
	 * @Ef(
	 *     group="company_base_info",
	 *     isBF=true
	 * )
	 */
	#[ORM\Column(type: 'boolean', nullable: true)]
	private $loginIndependent;

	#[ORM\Column(type: 'string', length: 180, nullable: true)]
	private $address;

	#[ORM\Column(type: 'string', length: 40, nullable: true)]
	private $phone;

	#[ORM\Column(type: 'string', length: 180, nullable: true)]
	private $email;

	#[ORM\Column(type: 'string', length: 180, nullable: true)]
	private $website;

	#[ORM\Column(type: 'text', nullable: true)]
	private $description;

	#[ORM\Column(type: 'integer', nullable: true)]
	private $sortOrder;

	/**
	 * 上级公司
	 * @Ef(
	 *     group="company_associated_info",
	 *     isBF=true
	 * )
	 */
	#[Gedmo\TreeParent]
	#[ORM\ManyToOne(targetEntity: 'Company', inversedBy: 'children')]
	#[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
	private $parent;

	/** 访问范围，一个公司有可见多个公司 */
	#[ORM\OneToMany(targetEntity: 'Company', mappedBy: 'accessSourcing')]
	private $accessScope;

	/** 被什么公司访问 */
	#[ORM\ManyToOne(targetEntity: 'Company', inversedBy: 'accessScope')]
	#[ORM\JoinColumn(name: 'access_sourcing_company_id', referencedColumnName: 'id')]
	private $accessSourcing = null;

	#[Gedmo\TreeLeft]
	#[ORM\Column(name: 'lft', type: 'integer')]
	private $lft;

	#[Gedmo\TreeLevel]
	#[ORM\Column(name: 'lvl', type: 'integer')]
	private $lvl;

	#[Gedmo\TreeRight]
	#[ORM\Column(name: 'rgt', type: 'integer')]
	private $rgt;

	#[Gedmo\TreeRoot]
	#[ORM\ManyToOne(targetEntity: 'Company')]
	#[ORM\JoinColumn(name: 'tree_root', referencedColumnName: 'id', onDelete: 'CASCADE')]
	private $root;

	#[ORM\OneToMany(targetEntity: 'Company', mappedBy: 'parent')]
	#[ORM\OrderBy(['lft' => 'ASC'])]
	private $children;

	/** 公司法人 */
	#[ORM\Column(type: 'string', length: 255, nullable: true)]
	private $gongSiFaRen;


	public function __construct()
	{
		$this->accessScope = new ArrayCollection();
		$this->id = Uuid::v4();
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
	 * Get 公司名称
	 */
	public function getName()
	{
		return $this->name;
	}


	/**
	 * Set 公司名称
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
	 * Get 重复排序号处理: 插入、重复
	 */
	public function getRepetitionNumHandling()
	{
		return $this->repetitionNumHandling;
	}


	/**
	 * Set 重复排序号处理: 插入、重复
	 *
	 * @return  self
	 */
	public function setRepetitionNumHandling($repetitionNumHandling)
	{
		$this->repetitionNumHandling = $repetitionNumHandling;

		return $this;
	}


	/**
	 * Get 状态: 启用、停用
	 */
	public function getState()
	{
		return $this->state;
	}


	/**
	 * Set 状态: 启用、停用
	 *
	 * @return  self
	 */
	public function setState($state)
	{
		$this->state = $state;

		return $this;
	}


	/**
	 * Get 独立登录页
	 */
	public function getLoginIndependent()
	{
		return $this->loginIndependent;
	}


	/**
	 * Set 独立登录页
	 *
	 * @return  self
	 */
	public function setLoginIndependent($loginIndependent)
	{
		$this->loginIndependent = $loginIndependent;

		return $this;
	}


	public function getAddress(): ?string
	{
		return $this->address;
	}


	public function setAddress(?string $address): self
	{
		$this->address = $address;

		return $this;
	}


	public function getPhone(): ?string
	{
		return $this->phone;
	}


	public function setPhone(?string $phone): self
	{
		$this->phone = $phone;

		return $this;
	}


	public function getEmail(): ?string
	{
		return $this->email;
	}


	public function setEmail(?string $email): self
	{
		$this->email = $email;

		return $this;
	}


	public function getWebsite(): ?string
	{
		return $this->website;
	}


	public function setWebsite(?string $website): self
	{
		$this->website = $website;

		return $this;
	}


	public function getDescription(): ?string
	{
		return $this->description;
	}


	public function setDescription(?string $description): self
	{
		$this->description = $description;

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
	 * Get 上级公司
	 */
	public function getParent()
	{
		return $this->parent;
	}


	/**
	 * Set 上级公司
	 *
	 * @return  self
	 */
	public function setParent($parent)
	{
		$this->parent = $parent;

		return $this;
	}


	/**
	 * 用魔法方法打印公司对象的名称
	 */
	public function __toString()
	{
		return $this->name;
	}


	/**
	 * 公司法人 Setter
	 * @return self
	 */
	public function setGongSiFaRen($gongSiFaRen): Company
	{
		$this->gongSiFaRen = $gongSiFaRen;
		return $this;
	}


	/**
	 * 公司法人 Getter
	 */
	public function getGongSiFaRen(): string
	{
		return $this->gongSiFaRen;
	}
}
