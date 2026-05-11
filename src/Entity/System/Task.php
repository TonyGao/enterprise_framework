<?php

namespace App\Entity\System;

use App\Repository\System\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'sys_task')]
#[ORM\Index(columns: ['next_run_at'], name: 'idx_task_next_run_at')]
#[ORM\Index(columns: ['enabled'], name: 'idx_task_enabled')]
#[ORM\Index(columns: ['enabled', 'next_run_at'], name: 'idx_task_due')]
class Task
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** 任务名称 */
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    /** 任务描述 */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Cron 表达式，如 "* * * * *" */
    #[ORM\Column(type: 'string', length: 100)]
    private string $cronExpression;

    /**
     * Handler 服务名（通过 app.task_handler tag 注册）
     * 示例: App\Task\SendEmailTask
     */
    #[ORM\Column(type: 'string', length: 255)]
    private string $handler;

    /** 传递给 Handler 的参数（JSON） */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    /** 是否启用 */
    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    /** 上次执行时间 */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastRunAt = null;

    /** 下次计划执行时间 */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $nextRunAt;

    /** 最大重试次数 */
    #[ORM\Column(type: 'smallint')]
    private int $maxRetries = 3;

    /** 执行超时（秒），0 表示不限制 */
    #[ORM\Column(type: 'integer')]
    private int $timeout = 0;

    /** 显示排序权重 */
    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    /** 任务分类标签，用于日历视图颜色区分 */
    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, TaskLog> */
    #[ORM\OneToMany(targetEntity: TaskLog::class, mappedBy: 'task', orphanRemoval: true)]
    #[ORM\OrderBy(['startedAt' => 'DESC'])]
    private Collection $logs;

    public function __construct()
    {
        $this->nextRunAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->logs = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): string
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

    public function getCronExpression(): string
    {
        return $this->cronExpression;
    }

    public function setCronExpression(string $cronExpression): self
    {
        $this->cronExpression = $cronExpression;
        return $this;
    }

    public function getHandler(): string
    {
        return $this->handler;
    }

    public function setHandler(string $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getLastRunAt(): ?\DateTimeImmutable
    {
        return $this->lastRunAt;
    }

    public function setLastRunAt(?\DateTimeImmutable $lastRunAt): self
    {
        $this->lastRunAt = $lastRunAt;
        return $this;
    }

    public function getNextRunAt(): \DateTimeImmutable
    {
        return $this->nextRunAt;
    }

    public function setNextRunAt(\DateTimeImmutable $nextRunAt): self
    {
        $this->nextRunAt = $nextRunAt;
        return $this;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLogs(): Collection
    {
        return $this->logs;
    }

    /** 获取最近一次执行日志 */
    public function getLatestLog(): ?TaskLog
    {
        return $this->logs->first() ?: null;
    }
}
