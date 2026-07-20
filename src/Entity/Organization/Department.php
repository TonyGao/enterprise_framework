<?php

namespace App\Entity\Organization;

use App\Annotation\Ef;
use App\Entity\Traits\CommonTrait;
use App\Repository\Organization\DepartmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Node as GedmoNode;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * 部门
 * isBusinessEntity
 */
#[Gedmo\Tree(type: 'nested')]
#[ORM\Table(name: 'org_department')]
#[ORM\Entity(repositoryClass: DepartmentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Department implements GedmoNode
{
	use CommonTrait;

	#[Groups(['api'])]
	#[ORM\Id]
	#[ORM\Column(type: 'uuid', unique: true)]
	private $id;

	/**
	 * 部门全称
	 * @Ef(
	 *     group="department_base_info",
	 *     isBF=true
	 * )
	 */
	#[Groups(['api'])]
	#[ORM\Column(type: 'string', length: 180)]
	private $name;

	/**
	 * 部门简称
	 * @Ef(
	 *     group="department_base_info",
	 *     isBF=true
	 * )
	 */
	#[Groups(['api'])]
	#[ORM\Column(type: 'string', length: 80, nullable: true)]
	private $alias;

	/**
	 * 部门显示名称
	 * @Ef(
	 *     group="department_base_info_displayname",
	 *     isBF=true
	 * )
	 */
	#[Groups(['api'])]
	#[ORM\Column(type: 'string', length: 200, nullable: true)]
	private $displayName;

	/**
	 * 形式：集团简称/公司简称/部门全称1/部门全称2
	 * 当是部门类型时，这个字段用于存储部门的全路径名称。
	 * 当做集团名称、公司名称、部门名称调整时，需要更新相应的path字段
	 */
	#[ORM\Column(type: 'string', length: 2000, nullable: true)]
	private $path;

	/**
	 * 所属公司
	 * @Ef(
	 *   group="department_base_info",
	 *   isBF=true
	 * )
	 */
	#[Groups(['api'])]
	#[ORM\ManyToOne(targetEntity: Company::class)]
	#[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id')]
	private $company;

	/**
	 * 在树状中的类型
	 * 类型包括：集团、公司、部门
	 */
	#[Groups(['api'])]
	#[ORM\Column(type: 'string')]
	private $type = 'department';

	/**
	 * 部门负责人
	 * @Ef(
	 *     group="department_management_info",
	 *     isBF=true
	 * )
	 */
	#[ORM\ManyToMany(targetEntity: Employee::class, mappedBy: 'managedDepartments')]
	private $manager;

	/**
	 * 编码
	 * @Ef(
	 *    group="department_base_info",
	 *    isBF=true
	 * )
	 */
	#[Groups(['api'])]
	#[ORM\Column(type: 'string', length: 180, nullable: true)]
	private $code;

	/**
	 * 状态: 启用、停用
	 * @Ef(
	 *     group="department_base_info",
	 *     isBF=true
	 * )
	 */
	#[ORM\Column(type: 'boolean', options: ['default' => 1])]
	private $state = true;

	/**
	 * 上级部门(树状)
	 * @Ef(
	 *     group="department_associated_info",
	 *     isBF=true
	 * )
	 */
	#[Gedmo\TreeParent]
	#[ORM\ManyToOne(targetEntity: Department::class, inversedBy: 'children')]
	#[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
	private $parent;

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
	#[ORM\ManyToOne(targetEntity: Department::class)]
	#[ORM\JoinColumn(name: 'tree_root', referencedColumnName: 'id', onDelete: 'CASCADE')]
	private $root;

	#[ORM\OneToMany(targetEntity: Department::class, mappedBy: 'parent')]
	#[ORM\OrderBy(['lft' => 'ASC'])]
	private $children;

	/** ERP部门编码 */
	#[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
	private $eRPBuMenBianMa;

	/** 部门经理 */
	#[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
	private $buMenJingLi;

	/** 部门总监 */
	#[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
	private $buMenZongJian;

	/** 部门副总 */
	#[ORM\Column(type: 'string', length: 300, nullable: true, unique: true)]
	private $buMenFuZong;

	/** test */
	#[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
	private $tEST;

	#[ORM\Column(type: 'integer', nullable: true)]
	private $sortOrder;

	/** 总监 */
	#[ORM\Column(type: 'json', nullable: false)]
	private $zongJian = [];


	public function __construct()
	{
		// 自动生成 UUID
			$this->id = Uuid::v4();
	}


	#[ORM\PrePersist]
	#[ORM\PreUpdate]
	public function updatePath()
	{
		$pathComponents = [];

		// 如果有上级部门，添加上级部门的路径
		if ($this->parent) {
			$pathComponents[] = $this->parent->getPath();
		}

		// 添加当前部门的名称
		$pathComponents[] = $this->name;

		// 生成完整路径
		$this->path = implode('/', $pathComponents);
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
	 * Get 部门全称
	 */
	public function getName()
	{
		return $this->name;
	}


	/**
	 * Set 部门全称
	 *
	 * @return  self
	 */
	public function setName($name)
	{
		$this->name = $name;

		return $this;
	}


	/**
	 * Get 部门简称
	 */
	public function getAlias()
	{
		return $this->alias;
	}


	/**
	 * Set 部门简称
	 *
	 * @return  self
	 */
	public function setAlias($alias)
	{
		$this->alias = $alias;

		return $this;
	}


	/**
	 * Get 所属公司
	 */
	public function getCompany()
	{
		return $this->company;
	}


	/**
	 * Set 所属公司
	 *
	 * @return  self
	 */
	public function setCompany($company)
	{
		$this->company = $company;

		return $this;
	}


	/**
	 * Get 在树状中的类型
	 */
	public function getType()
	{
		return $this->type;
	}


	/**
	 * Set 在树状中的类型
	 *
	 * @return  self
	 */
	public function setType($type)
	{
		$this->type = $type;

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
	 * Get 上级部门(树状)
	 */
	public function getParent()
	{
		return $this->parent;
	}


	/**
	 * Set 上级部门(树状)
	 *
	 * @return  self
	 */
	public function setParent($parent)
	{
		$this->parent = $parent;

		return $this;
	}


	/**
	 * Get 部门显示名称
	 */
	public function getDisplayName()
	{
		return $this->displayName;
	}


	/**
	 * Set 部门显示名称
	 *
	 * @return  self
	 */
	public function setDisplayName($displayName)
	{
		$this->displayName = $displayName;

		return $this;
	}


	/**
	 * Get 部门负责人
	 */
	public function getManager()
	{
		return $this->manager;
	}


	/**
	 * Set 部门负责人
	 *
	 * @return  self
	 */
	public function setManager($manager)
	{
		$this->manager = $manager;

		return $this;
	}


	/**
	 * ERP部门编码 Setter
	 * @return self
	 */
	public function setERPBuMenBianMa($eRPBuMenBianMa): Department
	{
		$this->eRPBuMenBianMa = $eRPBuMenBianMa;
		return $this;
	}


	/**
	 * ERP部门编码 Getter
	 */
	public function getERPBuMenBianMa(): string
	{
		return $this->eRPBuMenBianMa;
	}


	/**
	 * 部门经理 Setter
	 * @return self
	 */
	public function setBuMenJingLi($buMenJingLi): Department
	{
		$this->buMenJingLi = $buMenJingLi;
		return $this;
	}


	/**
	 * 部门经理 Getter
	 */
	public function getBuMenJingLi(): string
	{
		return $this->buMenJingLi;
	}


	/**
	 * 部门总监 Setter
	 * @return self
	 */
	public function setBuMenZongJian($buMenZongJian): Department
	{
		$this->buMenZongJian = $buMenZongJian;
		return $this;
	}


	/**
	 * 部门总监 Getter
	 */
	public function getBuMenZongJian(): string
	{
		return $this->buMenZongJian;
	}


	/**
	 * 部门副总 Setter
	 * @return self
	 */
	public function setBuMenFuZong($buMenFuZong): Department
	{
		$this->buMenFuZong = $buMenFuZong;
		return $this;
	}


	/**
	 * 部门副总 Getter
	 */
	public function getBuMenFuZong(): string
	{
		return $this->buMenFuZong;
	}


	/**
	 * Get the value of path
	 */
	public function getPath()
	{
		return $this->path;
	}


	/**
	 * Set the value of path
	 *
	 * @return  self
	 */
	public function setPath($path)
	{
		$this->path = $path;

		return $this;
	}


	/**
	 * test Setter
	 * @return self
	 */
	public function setTEST($tEST): Department
	{
		$this->tEST = $tEST;
		return $this;
	}


	/**
	 * test Getter
	 */
	public function getTEST(): ?string
	{
		return $this->tEST;
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


	public function getLft(): ?int
	{
		return $this->lft;
	}


	public function setLft(int $lft): self
	{
		$this->lft = $lft;

		return $this;
	}


	public function getRgt(): ?int
	{
		return $this->rgt;
	}


	public function setRgt(int $rgt): self
	{
		$this->rgt = $rgt;

		return $this;
	}


	public function getLvl(): ?int
	{
		return $this->lvl;
	}


	public function setLvl(int $lvl): self
	{
		$this->lvl = $lvl;

		return $this;
	}


	public function getRoot(): ?self
	{
		return $this->root;
	}


	public function setRoot(?self $root): self
	{
		$this->root = $root;

		return $this;
	}


	/**
	 * Set sibling node
	 */
	public function setSibling(self $node): void
	{
		// This method is required by Gedmo\Tree\Node interface
		// Implementation depends on your specific tree manipulation needs
		// For now, we'll leave it empty as it's typically handled by Gedmo internally
	}


	/**
	 * Get sibling node
	 */
	public function getSibling(): ?self
	{
		// This method is required by Gedmo\Tree\Node interface
		// Implementation depends on your specific tree manipulation needs
		// For now, we'll return null as it's typically handled by Gedmo internally
		return null;
	}


	/**
	 * 总监 Setter
	 * @return self
	 */
	public function setZongJian(array $zongJian): Department
	{
		$this->zongJian = $zongJian;
		return $this;
	}


	/**
	 * 总监 Getter
	 */
	public function getZongJian(): array
	{
		return $this->zongJian;
	}
}
