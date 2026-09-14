<?php

namespace App\Entity\Platform;

use App\Annotation\Ef;
use App\Entity\Traits\CommonTrait;
use App\Repository\Platform\MenuRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Node as GedmoNode;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Symfony\Component\Uid\Uuid;

/**
 * 菜单
 * isBusinessEntity
 */
#[Gedmo\Tree(type: 'nested')]
#[ORM\Table(name: 'admin_menu')]
#[ORM\Entity(repositoryClass: MenuRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Menu implements GedmoNode
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: "uuid", unique: true)]
    private $id;

    /**
     * 菜单名称
     */
    #[Ef(group: 'menu_base_info', isBF: true)]
    #[ORM\Column("menu_label", type: "string", length: 64)]
    private $label;

    /**
     * uri
     * type: system or custom
     */
    #[Ef(group: 'menu_base_info', isBF: true)]
    #[ORM\Column('menu_uri', type: 'string', length: 250)]
    private $uri;

    /**
     * Symfony route name
     * type: system or custom
     */
    #[Ef(group: 'menu_base_info', isBF: true)]
    #[ORM\Column('menu_routeName', type: 'string', length: 250, nullable: true)]
    private $routeName;

    /**
     * 外部链接 url
     * @EF(
     *     group="menu_base_info",
     *     isBF=true
     * )
     */
    #[ORM\Column('menu_url', type: 'string', length: 500, nullable: true)]
    private $url;

    /**
     * 菜单图标
     */
    #[Ef(group: 'menu_base_info', isBF: true)]
    #[ORM\Column('icon', length: 255, nullable: true)]
    private $icon;

    /**
     * 菜单类型
     * 分为 system 系统菜单 custom 自定义菜单 outside 外部链接
     */
    #[Ef(group: 'menu_base_info', isBF: true)]
    #[ORM\Column("menu_type", type: "string", length: 64, nullable: true)]
    private $type = 'system';

    /**
     * 菜单描述
     */
    #[Ef(group: 'menu_base_info', isBF: true)]
    #[ORM\Column("menu_description", type: "string", length: 200, nullable: true)]
    private $description;

    /**
     * 是否启用
     */
    #[Ef(group: 'menu_base_info', isBF: true)]
    #[ORM\Column("enabled", type: "boolean", options: ["default" => true])]
    private $enabled = true;

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
    #[ORM\ManyToOne(targetEntity: Menu::class)]
    #[ORM\JoinColumn(name: 'tree_root', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private $root;

    #[Gedmo\TreeParent]
    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private $parent;

    #[ORM\OneToMany(targetEntity: Menu::class, mappedBy: "parent")]
    #[ORM\OrderBy(["lft" => "ASC"])]
    private $children;

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
     * Get the value of label
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Set the value of label
     *
     * @return self
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get the value of icon
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * Set the value of icon
     *
     * @return self
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Get the value of uri
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Set the value of uri
     *
     * @return self
     */
    public function setUri($uri)
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * Get the value of lft
     */
    public function getLft()
    {
        return $this->lft;
    }

    /**
     * Set the value of lft
     *
     * @return  self
     */
    public function setLft($lft)
    {
        $this->lft = $lft;

        return $this;
    }

    /**
     * Get the value of lvl
     */
    public function getLvl()
    {
        return $this->lvl;
    }

    /**
     * Set the value of lvl
     *
     * @return  self
     */
    public function setLvl($lvl)
    {
        $this->lvl = $lvl;

        return $this;
    }

    /**
     * Get the value of rgt
     */
    public function getRgt()
    {
        return $this->rgt;
    }

    /**
     * Set the value of rgt
     *
     * @return  self
     */
    public function setRgt($rgt)
    {
        $this->rgt = $rgt;

        return $this;
    }

    /**
     * Get the value of root
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * Set the value of root
     *
     * @return  self
     */
    public function setRoot($root)
    {
        $this->root = $root;

        return $this;
    }

    /**
     * Get the value of parent
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Set the value of parent
     *
     * @return  self
     */
    public function setParent($parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get the value of children
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Set the value of children
     *
     * @return  self
     */
    public function setChildren($children)
    {
        $this->children = $children;

        return $this;
    }

    /**
     * Get the value of routePath
     */ 
    public function getRoutePath()
    {
        return $this->routePath;
    }

    /**
     * Set the value of routePath
     *
     * @return  self
     */ 
    public function setRoutePath($routePath)
    {
        $this->routePath = $routePath;

        return $this;
    }

    /**
     * Get the value of url
     */ 
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set the value of url
     *
     * @return  self
     */ 
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get the value of type
     */ 
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set the value of type
     *
     * @return  self
     */ 
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the value of routeName
     */ 
    public function getRouteName()
    {
        return $this->routeName;
    }

    /**
     * Set the value of routeName
     *
     * @return  self
     */ 
    public function setRouteName($routeName)
    {
        $this->routeName = $routeName;

        return $this;
    }

    /**
     * Get the value of description
     */ 
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set the value of description
     *
     * @return  self
     */ 
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the value of enabled
     */
    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set the value of enabled
     *
     * @return  self
     */
    public function setEnabled(bool $enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }
}
