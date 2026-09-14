<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use App\Repository\Platform\OptionsRepository;
use Symfony\Component\Uid\Uuid;

/**
 * 选项，实现动态属性
 */
#[ORM\Entity(repositoryClass: OptionsRepository::class)]
#[ORM\Table(name: 'platform_options')]
#[ORM\HasLifecycleCallbacks]
class Options
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    private $id;

    /**
     * 选项名称
     */
    #[ORM\Column(type: 'string', length: 40)]
    private $name;

    /**
 * 编码 / Code
     */
    #[ORM\Column(type: 'string', length: 80)]
    private $code;

    /**
     * 选项数据类型
     */
    #[ORM\Column(type: 'string', length: 40)]
    private $type;

    /**
     * 是自定义的，如果为系统自带值为 false
     */
    #[ORM\Column(type: 'boolean')]
    private $isCustom = false;

    /**
     * 授权类型
     */
    #[ORM\Column(type: 'json')]
    private $permitType;

    /**
     * 选项值
     */
    #[ORM\OneToMany(targetEntity: 'OptionValue', mappedBy: 'optionValue')]
    private $OptionValue;

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
     * Get 选项数据类型
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set 选项数据类型
     *
     * @return  self
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get 是自定义的，如果为系统自带值为 false
     */
    public function getIsCustom()
    {
        return $this->isCustom;
    }

    /**
     * Set 是自定义的，如果为系统自带值为 false
     *
     * @return  self
     */
    public function setIsCustom($isCustom)
    {
        $this->isCustom = $isCustom;

        return $this;
    }

    /**
     * Get 授权类型
     */
    public function getPermitType()
    {
        return $this->permitType;
    }

    /**
     * Set 授权类型
     *
     * @return  self
     */
    public function setPermitType($permitType)
    {
        $this->permitType = $permitType;

        return $this;
    }

    /**
     * Get 选项值
     */
    public function getOptionValue()
    {
        return $this->OptionValue;
    }

    /**
     * Set 选项值
     *
     * @return  self
     */
    public function setOptionValue($OptionValue)
    {
        $this->OptionValue = $OptionValue;

        return $this;
    }
}
