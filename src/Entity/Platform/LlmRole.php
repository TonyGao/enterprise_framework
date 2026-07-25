<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * LLM 角色/用途定义，分离配置与用途
 */
#[ORM\Entity(repositoryClass: \App\Repository\Platform\LlmRoleRepository::class)]
#[ORM\Table(name: 'platform_llm_role')]
#[ORM\HasLifecycleCallbacks]
class LlmRole
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(length: 50)]
    private string $code;   // vision, reasoning, general, lightweight, embedding

    #[ORM\Column(length: 100)]
    private string $label = '';

    #[ORM\ManyToOne(targetEntity: LlmProvider::class)]
    private ?LlmProvider $provider = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $systemPrompt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $options = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isEnabled = true;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getProvider(): ?LlmProvider
    {
        return $this->provider;
    }

    public function setProvider(?LlmProvider $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    public function setSystemPrompt(?string $systemPrompt): self
    {
        $this->systemPrompt = $systemPrompt;
        return $this;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;
        return $this;
    }
}
