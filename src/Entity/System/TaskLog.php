<?php

namespace App\Entity\System;

use App\Repository\System\TaskLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TaskLogRepository::class)]
#[ORM\Table(name: 'sys_task_log')]
#[ORM\Index(columns: ['task_id'], name: 'idx_task_log_task_id')]
#[ORM\Index(columns: ['status'], name: 'idx_task_log_status')]
#[ORM\Index(columns: ['started_at'], name: 'idx_task_log_started_at')]
class TaskLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Task::class, inversedBy: 'logs')]
    #[ORM\JoinColumn(name: 'task_id', nullable: false, onDelete: 'CASCADE')]
    private Task $task;

    /** 状态：running / success / error */
    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'running';

    /** 执行输出或错误信息 */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $output = null;

    /** 执行耗时（毫秒） */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $executionMs = null;

    /** 内存峰值使用（字节） */
    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $memoryPeak = null;

    /** 执行节点主机名 */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $hostName = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function setTask(Task $task): self
    {
        $this->task = $task;
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

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function setOutput(?string $output): self
    {
        $this->output = $output;
        return $this;
    }

    public function getExecutionMs(): ?int
    {
        return $this->executionMs;
    }

    public function setExecutionMs(?int $executionMs): self
    {
        $this->executionMs = $executionMs;
        return $this;
    }

    public function getMemoryPeak(): ?int
    {
        return $this->memoryPeak;
    }

    public function setMemoryPeak(?int $memoryPeak): self
    {
        $this->memoryPeak = $memoryPeak;
        return $this;
    }

    public function getHostName(): ?string
    {
        return $this->hostName;
    }

    public function setHostName(?string $hostName): self
    {
        $this->hostName = $hostName;
        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): self
    {
        $this->finishedAt = $finishedAt;
        return $this;
    }

    /** 格式化内存峰值为可读字符串 */
    public function getMemoryPeakFormatted(): string
    {
        if ($this->memoryPeak === null) {
            return '-';
        }
        if ($this->memoryPeak >= 1024 * 1024) {
            return round($this->memoryPeak / 1024 / 1024, 2) . ' MB';
        }
        return round($this->memoryPeak / 1024, 1) . ' KB';
    }
}
