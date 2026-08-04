<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: "platform_ai_chat_session")]
#[ORM\Index(name: "ai_chat_session_user_idx", columns: ["created_by"])]
#[ORM\Index(name: "ai_chat_session_context_idx", columns: ["context"])]
#[ORM\HasLifecycleCallbacks]
class AiChatSession
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    private Uuid $id;

    #[ORM\Column(name: "context", type: "string", length: 64)]
    private string $context;

    #[ORM\Column(name: "context_id", type: "string", length: 255, nullable: true)]
    private ?string $contextId = null;

    #[ORM\Column(name: "title", type: "string", length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(name: "intent", type: "string", length: 64, nullable: true)]
    private ?string $intent = null;

    #[ORM\Column(name: "mode", type: "string", length: 16, nullable: true)]
    private ?string $mode = null;

    #[ORM\Column(name: "is_active", type: "boolean")]
    private bool $isActive = true;

    /**
     * @var Collection<int, AiChatMessage>
     */
    #[ORM\OneToMany(targetEntity: AiChatMessage::class, mappedBy: "session", orphanRemoval: true, cascade: ["persist", "remove"])]
    #[ORM\OrderBy(["createdAt" => "ASC"])]
    private Collection $messages;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->messages = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function setContext(string $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function getContextId(): ?string
    {
        return $this->contextId;
    }

    public function setContextId(?string $contextId): self
    {
        $this->contextId = $contextId;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getIntent(): ?string
    {
        return $this->intent;
    }

    public function setIntent(?string $intent): self
    {
        $this->intent = $intent;
        return $this;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(?string $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * @return Collection<int, AiChatMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(AiChatMessage $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setSession($this);
        }
        return $this;
    }

    public function removeMessage(AiChatMessage $message): self
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getSession() === $this) {
                $message->setSession(null);
            }
        }
        return $this;
    }
}