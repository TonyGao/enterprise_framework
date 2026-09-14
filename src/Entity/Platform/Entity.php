<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use App\Repository\Platform\EntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;
/**
 * 实体模型
 */
#[ORM\Entity(repositoryClass: EntityRepository::class)]
#[ORM\Table(name: "platform_entity")]
#[ORM\Index(name: "entity_idx", columns: ["token"])]
#[ORM\HasLifecycleCallbacks]
class Entity
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    private $id;

    /**
     * 实体名称
     */
    #[ORM\Column(type: "string", length: 40)]
    private $name;

    /**
 * 编码 / Code
     */
    #[ORM\Column(type: "string", length: 80, nullable: true)]
    private $code = null;

    /**
     * 实体令牌
     */
    #[ORM\Column(type: "string", length: 40, unique: true)]
    private $token;

    /**
     * entity 的命名空间
     */
    #[ORM\Column(type: "string", length: 40, nullable: true)]
    private $fqn = null;

    /**
     * 是自定义的
     */
    #[ORM\Column(type: "boolean")]
    private $isCustomized;

    /**
     * 实体的类名称
     */
    #[ORM\Column(type: "string", length: 80)]
    private $className;

    /**
     * 描述，将用于动态模型的类注释
     *
     * @var [type]
     */
    #[ORM\Column(type: "text", nullable: true)]
    private $description;

    /**
     * 数据库表名称
     */
    #[ORM\Column(type: "string", length: 80)]
    private $dataTableName;

    /**
     * 表格配置信息（列显示、排序、宽度等）
     */
    #[ORM\Column(type: "json", nullable: true)]
    private $gridConfig = null;

    #[ORM\OneToMany(
        targetEntity: EntityProperty::class,
        mappedBy: "entity",
        orphanRemoval: true,
        cascade: ["persist"]
    )]
    private $properties;

    public function __construct() {
        $this->properties = new ArrayCollection();
        $this->id = Uuid::v4();
    }

    public function __toString()
    {
        return $this->getName();
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
     * Get 实体名称
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set 实体名称
     *
     * @return  self
     */
    public function setName($name)
    {
        $this->name = $name;

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
     * Get 实体令牌
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Set 实体令牌
     *
     * @return  self
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Get 实体的类名称
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * Set 实体的类名称
     *
     * @return  self
     */
    public function setClassName($className)
    {
        $this->className = $className;

        return $this;
    }

    /**
     * Get 数据库表名称
     */
    public function getDataTableName()
    {
        return $this->dataTableName;
    }

    /**
     * Set 数据库表名称
     *
     * @return  self
     */
    public function setDataTableName($dataTableName)
    {
        $this->dataTableName = $dataTableName;

        return $this;
    }

    /**
     * Get the value of properties
     */
    public function getProperties()
    {
        return $this->properties;
    }

    public function addProperty(EntityProperty $property): self
    {
        if (!$this->properties->contains($property)) {
            $this->properties[] = $property;
            $property->setEntity($this);
        }

        return $this;
    }

    /**
     * Get 是自定义的
     */
    public function getIsCustomized()
    {
        return $this->isCustomized;
    }

    /**
     * Set 是自定义的
     *
     * @return  self
     */
    public function setIsCustomized($isCustomized)
    {
        $this->isCustomized = $isCustomized;

        return $this;
    }

    /**
     * Get entity 的命名空间
     */
    public function getFqn()
    {
        return $this->fqn;
    }

    /**
     * Set entity 的命名空间
     *
     * @return  self
     */
    public function setFqn($fqn)
    {
        $this->fqn = $fqn;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get 表格配置信息
     */
    public function getGridConfig()
    {
        return $this->gridConfig;
    }

    /**
     * Set 表格配置信息
     *
     * @return  self
     */
    public function setGridConfig($gridConfig)
    {
        $this->gridConfig = $gridConfig;

        return $this;
    }
}
