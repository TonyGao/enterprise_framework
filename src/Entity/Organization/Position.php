<?php

namespace App\Entity\Organization;

use App\Repository\Organization\PositionRepository;
use App\Annotation\Ef;
use App\Entity\Platform\OptionValue;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;
use App\Entity\Traits\CommonTrait;

/**
 * 岗位
 * isBusinessEntity
 */
#[ORM\Entity(repositoryClass: PositionRepository::class)]
#[ORM\Table(name: 'org_position')]
#[ORM\HasLifecycleCallbacks]
class Position
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    private $id;

    /**
     * 岗位名称 / Position name
     */
    #[Ef(group: 'position_base_info', isBF: true)]
    #[ORM\Column(type: 'string', length: 180)]
    private $name;

    /**
     * 岗位编码 / Position code
     */
    #[Ef(group: 'position_base_info', isBF: true)]
    #[ORM\Column(type: "string", length: 80, nullable: true)]
    private $code;

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private $alias;

    /**
     * 所属部门 / Department
     */
    #[Ef(group: 'position_base_info', isBF: true)]
    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id')]
    private $department;

    /**
     * 岗位类型 (如：管理岗、技术岗、业务岗等)
     */
    #[Ef(group: 'position_base_info', isBF: true)]
    #[ORM\OneToOne(targetEntity: 'App\Entity\Platform\OptionValue')]
    #[ORM\JoinColumn(name: "type_id", referencedColumnName: "id", nullable: true)]
    private $type = null;

    /**
     * 岗位级别 / Position level
     */
    #[Ef(group: 'position_base_info', isBF: true)]
    #[ORM\ManyToOne(targetEntity: PositionLevel::class)]
    #[ORM\JoinColumn(name: 'level_id', referencedColumnName: 'id')]
    private $level;

    /**
     * 上级岗位 / Parent position
     */
    #[Ef(group: 'position_base_info', isBF: true)]
    #[ORM\ManyToOne(targetEntity: Position::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true)]
    private $parent;

    /**
     * 岗位职责 / Responsibilities
     */
    #[Ef(group: 'position_detail_info', isBF: true)]
    #[ORM\Column(type: 'text', nullable: true)]
    private $responsibility;

    /**
     * 任职要求 / Requirements
     */
    #[Ef(group: 'position_detail_info', isBF: true)]
    #[ORM\Column(type: 'text', nullable: true)]
    private $requirement;

    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    /**
     * 编制人数 / Headcount
     */
    #[Ef(group: 'position_detail_info', isBF: true)]
    #[ORM\Column(type: 'integer', nullable: true)]
    private $headcount;

    /**
     * 状态 (启用/停用) / Status (enabled/disabled)
     */
    #[Ef(group: 'position_base_info', isBF: true)]
    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private $state = true;

    /**
     * 排序号 / Sort order
     */
    #[Ef(group: 'position_base_info', isBF: true)]
    #[ORM\Column(type: 'integer', nullable: true)]
    private $sortOrder;

    /**
     * 备注 / Remark
     */
    #[Ef(group: 'position_detail_info', isBF: true)]
    #[ORM\Column(type: 'text', nullable: true)]
    private $remark;

    public function __construct()
    {
        // 自动生成 UUID
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
     * Get 岗位名称
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set 岗位名称
     *
     * @return  self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get 岗位编码
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set 岗位编码
     *
     * @return  self
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function setAlias(?string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Get 所属部门
     */
    public function getDepartment()
    {
        return $this->department;
    }

    /**
     * Set 所属部门
     *
     * @return  self
     */
    public function setDepartment($department)
    {
        $this->department = $department;

        return $this;
    }

    /**
     * Get 岗位类型
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set 岗位类型
     *
     * @return  self
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get 岗位级别
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Set 岗位级别
     *
     * @return  self
     */
    public function setLevel($level)
    {
        $this->level = $level;

        return $this;
    }

    /**
     * Get 上级岗位
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Set 上级岗位
     *
     * @return  self
     */
    public function setParent($parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get 岗位职责
     */
    public function getResponsibility()
    {
        return $this->responsibility;
    }

    /**
     * Set 岗位职责
     *
     * @return  self
     */
    public function setResponsibility($responsibility)
    {
        $this->responsibility = $responsibility;

        return $this;
    }

    /**
     * Get 任职要求
     */
    public function getRequirement()
    {
        return $this->requirement;
    }

    /**
     * Set 任职要求
     *
     * @return  self
     */
    public function setRequirement($requirement)
    {
        $this->requirement = $requirement;

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

    /**
     * Get 编制人数
     */
    public function getHeadcount()
    {
        return $this->headcount;
    }

    /**
     * Set 编制人数
     *
     * @return  self
     */
    public function setHeadcount($headcount)
    {
        $this->headcount = $headcount;

        return $this;
    }

    /**
     * Get 状态
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Set 状态
     *
     * @return  self
     */
    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * Get 排序号
     */
    public function getSortOrder()
    {
        return $this->sortOrder;
    }

    /**
     * Set 排序号
     *
     * @return  self
     */
    public function setSortOrder($sortOrder)
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    /**
     * Get 备注
     */
    public function getRemark()
    {
        return $this->remark;
    }

    /**
     * Set 备注
     *
     * @return  self
     */
    public function setRemark($remark)
    {
        $this->remark = $remark;

        return $this;
    }
}