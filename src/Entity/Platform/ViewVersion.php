<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * 视图版本记录：一个视图可拥有多个版本，每个版本对应磁盘上一组
 * {path}/{version}/{name}.design.twig / {name}.html.twig 文件。
 * 字段与未来通用资产版本表 platform_asset_version 保持同构（见 documents/conceptions/viewEditor/view-version.md §14）。
 */
#[ORM\Entity]
#[ORM\Table(name: "platform_view_version")]
#[ORM\UniqueConstraint(name: "uniq_view_version", columns: ["view_id", "version"])]
#[ORM\HasLifecycleCallbacks]
class ViewVersion
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: View::class, inversedBy: "versions")]
    #[ORM\JoinColumn(name: "view_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private View $view;

    #[ORM\Column(type: "string", length: 32)]
    private string $version;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(name: "is_current", type: "boolean", options: ["default" => false])]
    private bool $isCurrent = false;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getView(): View
    {
        return $this->view;
    }

    public function setView(View $view): self
    {
        $this->view = $view;
        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;
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

    public function isCurrent(): bool
    {
        return $this->isCurrent;
    }

    public function setIsCurrent(bool $isCurrent): self
    {
        $this->isCurrent = $isCurrent;
        return $this;
    }
}
