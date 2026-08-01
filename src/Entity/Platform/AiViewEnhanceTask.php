<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * 视图 AI 二次加工任务（创建视图时触发，由 Messenger 异步执行）。
 * status: pending | running | success | error
 */
#[ORM\Entity]
#[ORM\Table(name: "platform_ai_view_task")]
#[ORM\Index(name: "ai_view_task_view_idx", columns: ["view_id"])]
#[ORM\Index(name: "ai_view_task_status_idx", columns: ["status"])]
#[ORM\HasLifecycleCallbacks]
class AiViewEnhanceTask
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: View::class)]
    #[ORM\JoinColumn(name: "view_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private ?View $view = null;

    #[ORM\Column(name: "requirement", type: "text")]
    private string $requirement;

    #[ORM\Column(name: "status", type: "string", length: 20, options: ['default' => 'pending'])]
    private string $status = 'pending';

    #[ORM\Column(name: "progress_text", type: "text", nullable: true)]
    private ?string $progressText = null;

    #[ORM\Column(name: "result", type: "text", nullable: true)]
    private ?string $result = null;

    #[ORM\Column(name: "error_message", type: "text", nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: "started_at", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(name: "finished_at", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $finishedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getView(): ?View
    {
        return $this->view;
    }

    public function setView(?View $view): self
    {
        $this->view = $view;
        return $this;
    }

    public function getRequirement(): string
    {
        return $this->requirement;
    }

    public function setRequirement(string $requirement): self
    {
        $this->requirement = $requirement;
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

    public function getProgressText(): ?string
    {
        return $this->progressText;
    }

    public function setProgressText(?string $progressText): self
    {
        $this->progressText = $progressText;
        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(?string $result): self
    {
        $this->result = $result;
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

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeInterface $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getFinishedAt(): ?\DateTimeInterface
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeInterface $finishedAt): self
    {
        $this->finishedAt = $finishedAt;
        return $this;
    }
}
