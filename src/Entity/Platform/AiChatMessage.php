<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: "platform_ai_chat_message")]
#[ORM\Index(name: "ai_chat_msg_session_idx", columns: ["session_id"])]
#[ORM\HasLifecycleCallbacks]
class AiChatMessage
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: AiChatSession::class, inversedBy: "messages")]
    #[ORM\JoinColumn(name: "session_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private ?AiChatSession $session = null;

    #[ORM\Column(name: "role", type: "string", length: 20)]
    private string $role;

    #[ORM\Column(name: "content", type: "text")]
    private string $content;

    #[ORM\Column(name: "tool_calls", type: "json", nullable: true)]
    private ?array $toolCalls = null;

    /**
     * 结构化附加信息：{type?: 'clarification', question?, options?, elapsedMs?, toolCount?, redesignApplied?}
     * 用于历史消息重载时重建澄清选项/执行效率/重构结果等特殊 UI。
     */
    #[ORM\Column(name: "meta", type: "json", nullable: true)]
    private ?array $meta = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSession(): ?AiChatSession
    {
        return $this->session;
    }

    public function setSession(?AiChatSession $session): self
    {
        $this->session = $session;
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getToolCalls(): ?array
    {
        return $this->toolCalls;
    }

    public function setToolCalls(?array $toolCalls): self
    {
        $this->toolCalls = $toolCalls;
        return $this;
    }

    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function setMeta(?array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }
}