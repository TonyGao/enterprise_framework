<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Node as GedmoNode;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Symfony\Component\Uid\Uuid;

/**
 * 视图树状模型，此模型用于方便管理用户自定义视图
 * 树状中包含文件夹和视图文件，这里的视图将统一放到框架 /templates/views 目录中
 * 即物理路径都将以此为基础加上用户定义的相对路径。
 */
#[Gedmo\Tree(type: 'nested')]
#[ORM\Table(name: "platform_view")]
#[ORM\Index(name: 'platform_view_idx', columns: ["type"])]
#[ORM\Entity(repositoryClass: NestedTreeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class View implements GedmoNode
{
  use commonTrait;

  #[ORM\Id]
  #[ORM\Column(name: 'id', type: "uuid", unique: true)]
  private $id;

  /**
   * 视图名称
   */
  #[ORM\Column('view_name', type: 'string', length: 64, nullable: true)]
  private $name = null;

  /**
   * 视图标签
   */
  #[ORM\Column('view_label', type: 'string', length: 64, nullable: true)]
  private $label = null;

  /**
   * 类型: root, folder, view
   */
  #[ORM\Column(type: 'string', length: 20, nullable: true)]
  private $type;

  #[ORM\Column(type: 'string', length: 200, nullable: true)]
  private $path;

  /**
   * 是否系统内置 (true=系统预置, false=用户自定义)
   */
  #[ORM\Column(name: 'is_built_in', type: 'boolean', options: ['default' => false])]
  private bool $builtIn = false;

  /**
   * 系统内置视图使用的模板路径
   */
  #[ORM\Column(name: 'template', type: 'string', length: 255, nullable: true)]
  private ?string $template = null;

  /**
   * 关联的实体模型（表单视图使用）
   */
  #[ORM\ManyToOne(targetEntity: Entity::class)]
  #[ORM\JoinColumn(name: 'entity_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
  private ?Entity $formEntity = null;

  /**
   * Section 配置 (JSON)
   * {contentWidth: "boxed"|"full-width", width: number, unit: "px"|"%"|"em"|"rem"|"vw"}
   */
  #[ORM\Column(name: 'section_config', type: 'json', nullable: true)]
  private ?array $sectionConfig = null;

  #[Gedmo\TreeLeft]
  #[ORM\Column(name: "lft", type: "integer")]
  private $lft;

  #[Gedmo\TreeLevel]
  #[ORM\Column(name: "lvl", type: "integer")]
  private $lvl;

  #[Gedmo\TreeRight]
  #[ORM\Column(name: "rgt", type: "integer")]
  private $rgt;

  #[Gedmo\TreeRoot]
  #[ORM\ManyToOne(targetEntity: "View")]
  #[ORM\JoinColumn(name: "tree_root", referencedColumnName: "id", onDelete: "CASCADE")]
  private $root;

  #[Gedmo\TreeParent]
  #[ORM\ManyToOne(targetEntity: "View", inversedBy: "children")]
  #[ORM\JoinColumn(name: "parent_id", referencedColumnName: "id", onDelete: "CASCADE")]
  private $parent;

  #[ORM\OneToMany(targetEntity: "View", mappedBy: "parent")]
  #[ORM\OrderBy(["lft" => "ASC"])]
  private $children;

  public function __toString()
  {
    return $this->label ? $this->label : $this->name;
  }

  public function __construct()
  {
    // 自动生成 UUID
    $this->id = Uuid::v4();
  }

    // Getters and Setters

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function isBuiltIn(): bool
    {
        return $this->builtIn;
    }

    public function setBuiltIn(bool $builtIn): self
    {
        $this->builtIn = $builtIn;
        return $this;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): self
    {
        $this->template = $template;
        return $this;
    }

    public function getFormEntity(): ?Entity
    {
        return $this->formEntity;
    }

    public function setFormEntity(?Entity $formEntity): self
    {
        $this->formEntity = $formEntity;
        return $this;
    }

    public function getSectionConfig(): ?array
    {
        return $this->sectionConfig;
    }

    public function setSectionConfig(?array $sectionConfig): self
    {
        $this->sectionConfig = $sectionConfig;
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

    public function getLvl(): ?int
    {
        return $this->lvl;
    }

    public function setLvl(int $lvl): self
    {
        $this->lvl = $lvl;
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

    public function getRoot(): ?View
    {
        return $this->root;
    }

    public function setRoot(?View $root): self
    {
        $this->root = $root;
        return $this;
    }

    public function getParent(): ?View
    {
        return $this->parent;
    }

    public function setParent(?View $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return View[]|null
     */
    public function getChildren(): ?array
    {
        return $this->children;
    }

    public function addChild(View $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(View $child): self
    {
        if ($this->children->contains($child)) {
            $this->children->removeElement($child);
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
        return $this;
    }
}
