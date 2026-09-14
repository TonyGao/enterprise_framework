<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * 数据表格
 */
#[ORM\Entity]
#[ORM\Table(name: "platform_datagrid")]
class DataGrid 
{
  use CommonTrait;

  #[ORM\Id]
  #[ORM\Column(type: "uuid", unique: true)]
  private $id;

  /**
   * DataGrid名称
   */
  #[ORM\Column(type: "string", length: 255)]
  private $name;

  /**
 * 描述 / Description
   */
  #[ORM\Column(type: "text", nullable: true)]
  private $description;

  /**
   * DataSource
   */
  #[ORM\ManyToOne(targetEntity: DataSource::class, inversedBy: "dataGrid")]
  #[ORM\JoinColumn(nullable: false)]
  private DataSource $dataSource;

  /**
   * 默认配置数据
   * 用于存储 datagrid 或表单的默认前端配置项
   *（列顺序、列宽、是否可见、过滤器、排序、分页设置等）
   * 这是整个组件系统的"视图定义"
   */
  #[ORM\Column(type: "json", nullable: true)]
  private ?array $defaultConfigData = null;

  public function __construct()
  {
    $this->id = Uuid::v4();
  }

  public function getId(): ?string
  {
    return $this->id;
  }

  public function getName(): ?string
  {
    return $this->name;
  }

  public function setName(string $name): self
  {
    $this->name = $name;
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

  public function getDataSource(): ?DataSource
  {
    return $this->dataSource;
  }

  public function setDataSource(?DataSource $dataSource): self
  {
    $this->dataSource = $dataSource;
    return $this;
  }

  public function getDefaultConfigData(): ?array
  {
    return $this->defaultConfigData;
  }

  public function setDefaultConfigData(?array $defaultConfigData): self
  {
    $this->defaultConfigData = $defaultConfigData;
    return $this;
  }
}
