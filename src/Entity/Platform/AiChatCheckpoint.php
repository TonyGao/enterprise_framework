<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * AI 对话消息级视图检查点：记录某条 AI 回复之后视图某版本的文件快照。
 * 前端在对应消息上提供「回退到此」，恢复该消息时的视图状态。
 */
#[ORM\Entity]
#[ORM\Table(name: "platform_ai_chat_checkpoint")]
#[ORM\Index(name: "ai_chat_checkpoint_view_idx", columns: ["view_id", "version"])]
#[ORM\HasLifecycleCallbacks]
class AiChatCheckpoint
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    private Uuid $id;

    #[ORM\Column(name: "view_id", type: "uuid")]
    private Uuid $viewId;

    #[ORM\Column(type: "string", length: 32)]
    private string $version;

    #[ORM\Column(name: "message_id", type: "uuid", nullable: true)]
    private ?Uuid $messageId = null;

    /**
     * 快照目录相对路径（相对版本目录，如 .checkpoints/xxx）
     */
    #[ORM\Column(name: "snapshot_dir", type: "string", length: 255)]
    private string $snapshotDir;

    #[ORM\Column(name: "content_hash", type: "string", length: 64, nullable: true)]
    private ?string $contentHash = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getViewId(): Uuid
    {
        return $this->viewId;
    }

    public function setViewId(Uuid $viewId): self
    {
        $this->viewId = $viewId;
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

    public function getMessageId(): ?Uuid
    {
        return $this->messageId;
    }

    public function setMessageId(?Uuid $messageId): self
    {
        $this->messageId = $messageId;
        return $this;
    }

    public function getSnapshotDir(): string
    {
        return $this->snapshotDir;
    }

    public function setSnapshotDir(string $snapshotDir): self
    {
        $this->snapshotDir = $snapshotDir;
        return $this;
    }

    public function getContentHash(): ?string
    {
        return $this->contentHash;
    }

    public function setContentHash(?string $contentHash): self
    {
        $this->contentHash = $contentHash;
        return $this;
    }
}
