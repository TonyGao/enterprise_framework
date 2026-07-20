<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;

/**
 * 视图字段实体
 * 用于存储视图中绑定的模型字段信息，并标记哪些字段已经插入到视图中
 */
#[ORM\Entity]
#[ORM\Table(name: "platform_view_field")]
#[ORM\Index(name: "view_field_idx", columns: ["view_id", "entity_id"])]
#[ORM\HasLifecycleCallbacks]
class ViewField
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    private $id;

    /**
     * 关联的视图
     */
    #[ORM\ManyToOne(targetEntity: View::class)]
    #[ORM\JoinColumn(name: "view_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private $view;

    /**
     * 关联的实体模型
     */
    #[ORM\ManyToOne(targetEntity: Entity::class)]
    #[ORM\JoinColumn(name: "entity_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private $entity;

    /**
     * 字段名称
     */
    #[ORM\Column(type: "string", length: 80)]
    private $fieldName;

    /**
     * 字段标签
     */
    #[ORM\Column(type: "string", length: 100)]
    private $fieldLabel;

    /**
     * 字段类型
     */
    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private $fieldType;

    /**
     * 是否已插入标签到视图中
     */
    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private $labelInserted = false;

    /**
     * 是否已插入字段值到视图中
     */
    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private $valueInserted = false;

    /**
     * 标签插入位置（可选，用于记录插入的DOM位置）
     */
    #[ORM\Column(type: "text", nullable: true)]
    private $labelPosition;

    /**
     * 值插入位置（可选，用于记录插入的DOM位置）
     */
    #[ORM\Column(type: "text", nullable: true)]
    private $valuePosition;

  /**
   * 字段配置（JSONB）
   * 存储验证规则、列宽、占位符、选项值等
   */
  #[ORM\Column(type: "json", nullable: true)]
  private ?array $config = null;

  /**
   * 排序号
   */
  #[ORM\Column(type: "integer", options: ["default" => 0])]
  private $sortOrder = 0;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function __toString()
    {
        return $this->fieldLabel ?: $this->fieldName;
    }

    // Getters and Setters

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getView(): ?View
    {
        return $this->view;
    }

    public function setView(?View $view): self
    {
        $this->view = $view;
        return $this;
    }

    public function getEntity(): ?Entity
    {
        return $this->entity;
    }

    public function setEntity(?Entity $entity): self
    {
        $this->entity = $entity;
        return $this;
    }

    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    public function setFieldName(string $fieldName): self
    {
        $this->fieldName = $fieldName;
        return $this;
    }

    public function getFieldLabel(): ?string
    {
        return $this->fieldLabel;
    }

    public function setFieldLabel(string $fieldLabel): self
    {
        $this->fieldLabel = $fieldLabel;
        return $this;
    }

    public function getFieldType(): ?string
    {
        return $this->fieldType;
    }

    public function setFieldType(?string $fieldType): self
    {
        $this->fieldType = $fieldType;
        return $this;
    }

    public function isLabelInserted(): bool
    {
        return $this->labelInserted;
    }

    public function setLabelInserted(bool $labelInserted): self
    {
        $this->labelInserted = $labelInserted;
        return $this;
    }

    public function isValueInserted(): bool
    {
        return $this->valueInserted;
    }

    public function setValueInserted(bool $valueInserted): self
    {
        $this->valueInserted = $valueInserted;
        return $this;
    }

    public function getLabelPosition(): ?string
    {
        return $this->labelPosition;
    }

    public function setLabelPosition(?string $labelPosition): self
    {
        $this->labelPosition = $labelPosition;
        return $this;
    }

    public function getValuePosition(): ?string
    {
        return $this->valuePosition;
    }

    public function setValuePosition(?string $valuePosition): self
    {
        $this->valuePosition = $valuePosition;
        return $this;
    }
    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): self
    {
        $this->config = $config;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }
}