<?php

namespace App\Entity\Organization;

use App\Repository\Organization\PositionLevelRepository;
use App\Annotation\Ef;
use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;

/**
 * 职务级别
 * isBusinessEntity
 */
#[ORM\Entity(repositoryClass: PositionLevelRepository::class)]
#[ORM\Table(name: 'org_position_level')]
#[ORM\HasLifecycleCallbacks]
class PositionLevel
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private $id;

    /**
     * 级别名称 / Level name
     */
    #[Ef(group: 'position_level_info', isBF: true)]
    #[ORM\Column(type: 'string', length: 100)]
    private $name;

    /**
     * 级别编码 / Level code
     */
    #[Ef(group: 'position_level_info', isBF: true)]
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $code;

    /**
     * 级别序号（数字越小级别越高）
     */
    #[Ef(group: 'position_level_info', isBF: true)]
    #[ORM\Column(type: 'integer')]
    private $levelOrder;

    /**
     * 级别描述 / Level description
     */
    #[Ef(group: 'position_level_info', isBF: true)]
    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    /**
     * 薪资范围下限 / Salary range min
     */
    #[Ef(group: 'position_level_salary', isBF: true)]
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private $salaryMin;

    /**
     * 薪资范围上限 / Salary range max
     */
    #[Ef(group: 'position_level_salary', isBF: true)]
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private $salaryMax;

    /**
     * 状态（启用/停用） / Status (enabled/disabled)
     */
    #[Ef(group: 'position_level_info', isBF: true)]
    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private $state = true;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $sortOrder;

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
     * Get 级别名称
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set 级别名称
     *
     * @return  self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get 级别编码
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set 级别编码
     *
     * @return  self
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get 级别序号（数字越小级别越高）
     */
    public function getLevelOrder()
    {
        return $this->levelOrder;
    }

    /**
     * Set 级别序号（数字越小级别越高）
     *
     * @return  self
     */
    public function setLevelOrder($levelOrder)
    {
        $this->levelOrder = $levelOrder;

        return $this;
    }

    /**
     * Get 级别描述
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set 级别描述
     *
     * @return  self
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get 薪资范围下限
     */
    public function getSalaryMin()
    {
        return $this->salaryMin;
    }

    /**
     * Set 薪资范围下限
     *
     * @return  self
     */
    public function setSalaryMin($salaryMin)
    {
        $this->salaryMin = $salaryMin;

        return $this;
    }

    /**
     * Get 薪资范围上限
     */
    public function getSalaryMax()
    {
        return $this->salaryMax;
    }

    /**
     * Set 薪资范围上限
     *
     * @return  self
     */
    public function setSalaryMax($salaryMax)
    {
        $this->salaryMax = $salaryMax;

        return $this;
    }

    /**
     * Get 状态（启用/停用）
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Set 状态（启用/停用）
     *
     * @return  self
     */
    public function setState($state)
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
}