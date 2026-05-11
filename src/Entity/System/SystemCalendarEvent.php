<?php

namespace App\Entity\System;

use App\Repository\System\SystemCalendarEventRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SystemCalendarEventRepository::class)]
#[ORM\Table(name: 'sys_calendar_event')]
#[ORM\HasLifecycleCallbacks]
class SystemCalendarEvent
{
    public const TYPE_HOLIDAY    = 'holiday';     // 法定假日
    public const TYPE_WORKDAY    = 'workday';     // 工作日补班（调休上班）
    public const TYPE_MAKEUP_DAY = 'makeup_day';  // 调休（对应节假日补偿）
    public const TYPE_CUSTOM     = 'custom';      // 自定义标注

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $title = '';

    /** holiday | workday | makeup_day | custom */
    #[ORM\Column(length: 32)]
    private string $type = self::TYPE_CUSTOM;

    /** 具体日期（固定日期事件）*/
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $date = null;

    /** 是否每年重复（如：每年法定节假日） */
    #[ORM\Column]
    private bool $recurring = false;

    /**
     * 重复规则（JSON），仅 recurring=true 时有效
     * 示例：{"type":"annual","month":1,"day":1}
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $recurringRule = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    /** CSS 颜色或预设色号 */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id        = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getDate(): ?\DateTimeImmutable { return $this->date; }
    public function setDate(?\DateTimeImmutable $date): static { $this->date = $date; return $this; }

    public function isRecurring(): bool { return $this->recurring; }
    public function setRecurring(bool $recurring): static { $this->recurring = $recurring; return $this; }

    public function getRecurringRule(): ?array { return $this->recurringRule; }
    public function setRecurringRule(?array $recurringRule): static { $this->recurringRule = $recurringRule; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): static { $this->note = $note; return $this; }

    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): static { $this->color = $color; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
