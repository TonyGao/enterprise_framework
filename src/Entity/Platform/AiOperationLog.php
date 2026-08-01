<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: "platform_ai_operation_log")]
#[ORM\Index(name: "ai_log_user_idx", columns: ["created_by"])]
#[ORM\Index(name: "ai_log_context_idx", columns: ["context"])]
#[ORM\HasLifecycleCallbacks]
class AiOperationLog
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    private Uuid $id;

    #[ORM\Column(name: "context", type: "string", length: 64)]
    private string $context;

    #[ORM\Column(name: "role_code", type: "string", length: 64, nullable: true)]
    private ?string $roleCode = null;

    #[ORM\Column(name: "user_message", type: "text")]
    private string $userMessage;

    #[ORM\Column(name: "assistant_reply", type: "text", nullable: true)]
    private ?string $assistantReply = null;

    #[ORM\Column(name: "tool_calls", type: "json", nullable: true)]
    private ?array $toolCalls = null;

    #[ORM\Column(name: "status", type: "string", length: 20)]
    private string $status = 'success';

    #[ORM\Column(name: "error_message", type: "text", nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: "elapsed_ms", type: "integer", nullable: true)]
    private ?int $elapsedMs = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
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

    public function getRoleCode(): ?string
    {
        return $this->roleCode;
    }

    public function setRoleCode(?string $roleCode): self
    {
        $this->roleCode = $roleCode;
        return $this;
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    public function setUserMessage(string $userMessage): self
    {
        $this->userMessage = $userMessage;
        return $this;
    }

    public function getAssistantReply(): ?string
    {
        return $this->assistantReply;
    }

    public function setAssistantReply(?string $assistantReply): self
    {
        $this->assistantReply = $assistantReply;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getElapsedMs(): ?int
    {
        return $this->elapsedMs;
    }

    public function setElapsedMs(?int $elapsedMs): self
    {
        $this->elapsedMs = $elapsedMs;
        return $this;
    }
}