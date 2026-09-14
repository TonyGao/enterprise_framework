<?php

namespace App\Entity\Platform;

use App\Annotation\Ef;
use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Tree\Node as GedmoNode;
use App\Repository\Platform\EntityPropertyGroupRepository;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Symfony\Component\Uid\Uuid;

/**
 * isBusinessEntity
 * 
 * 实体属性分组，基本概念就是将整个entity和property建立一套树状的结构，如下示意
 * 通过type类型，将这个树状结构分为 root, Entity, Group, Property 几个类型
 * root为唯一的根，Entity为根下的第二层，Group为Entity下的属性分组，分组本身是
 * 树状结构，Entity的下方可以是Property属性，任何层的分组下方都可以有Property属性。
 *
 *
 *                                        /PropertyOne
 *                                 /Group1-
 *       / --namespaceA -- EntityA-       \PropertyTwo
 *      /                          \Group2    /Group5
 * root-                                   /--
 *      \                         /Group3-    \Group6
 *       \--namespaceA -- EntityB-                  \--
 *                                                     \PropertyThree
 */
#[Gedmo\Tree(type: 'nested')]
#[ORM\Table(name: "platform_entity_property_group")]
#[ORM\Index(name: 'entity_property_group_idx', columns: ["type"])]
#[ORM\Entity(repositoryClass: NestedTreeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class EntityPropertyGroup implements GedmoNode
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: "uuid", unique: true)]
    private $id;
 
    /**
     * 分组名称
     */
    #[Ef(group: 'epg_base_info', isBF: true)]
    #[ORM\Column('group_name', type: 'string', length: 64, nullable: true)]
    private $name = null;

    /**
     * 是否为默认分组，此属性只在 type 为 group 时有意义
     */
    #[Ef(group: 'epg_base_info', isBF: true)]
    #[ORM\Column('is_default', type: 'boolean', nullable: true)]
    private $isDefault = false;

    /**
     * 分组标签
     */
    #[Ef(group: 'epg_base_info', isBF: true)]
    #[ORM\Column('group_label', type: 'string', length: 64, nullable: true)]
    private $label = null;

    /**
     * 类型: root, namespace, entity, group, property
     */
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private $type;

    /**
     * EntityProperty的token
     */
    #[ORM\Column(type: 'string', length: 40, nullable: true, unique: true)]
    private $token = null;

    /**
     * Entity的token
     *
     * @var [type]
     */
    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private $entityToken = null;

    /**
     * entity namespace + class name
     */
    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private $fqn = null;

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
    #[ORM\ManyToOne(targetEntity: "EntityPropertyGroup")]
    #[ORM\JoinColumn(name: "tree_root", referencedColumnName: "id", onDelete: "CASCADE")]
    private $root;

    #[Gedmo\TreeParent]
    #[ORM\ManyToOne(targetEntity: "EntityPropertyGroup", inversedBy: "children")]
    #[ORM\JoinColumn(name: "parent_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private $parent;

    #[ORM\OneToMany(targetEntity: "EntityPropertyGroup", mappedBy: "parent")]
    #[ORM\OrderBy(["lft" => "ASC"])]
    private $children;

    public function __toString() {
        return $this->label ? $this->label : $this->name;
    }

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
     * Get 分组名称
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set 分组名称
     *
     * @return  self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get 分组标签
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Set 分组标签
     *
     * @return  self
     */
    public function setLabel($label)
    {
        $this->label = $label;

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
     * Get 类型: root, entity, group, property
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set 类型: root, entity, group, property
     *
     * @return  self
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get entity, property的token
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Set entity, property的token
     *
     * @return  self
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Get entity namespace + class name
     */
    public function getFqn()
    {
        return $this->fqn;
    }

    /**
     * Set entity namespace + class name
     *
     * @return  self
     */
    public function setFqn($fqn)
    {
        $this->fqn = $fqn;

        return $this;
    }

    /**
     * Get the value of isDefault
     */ 
    public function getIsDefault()
    {
        return $this->isDefault;
    }

    /**
     * Set the value of isDefault
     *
     * @return  self
     */ 
    public function setIsDefault($isDefault)
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    /**
     * Get the value of entityToken
     */ 
    public function getEntityToken()
    {
        return $this->entityToken;
    }

    /**
     * Set the value of entityToken
     *
     * @return  self
     */ 
    public function setEntityToken($entityToken)
    {
        $this->entityToken = $entityToken;

        return $this;
    }
}
