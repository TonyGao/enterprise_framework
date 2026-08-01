<?php

namespace App\Service\AI\Runtime;

class AiContextRegistry
{
    /** @var array<string, AiContextProviderInterface> */
    private array $providers = [];

    public function __construct(iterable $contextProviders)
    {
        foreach ($contextProviders as $provider) {
            if ($provider instanceof AiContextProviderInterface) {
                $this->providers[$provider->getName()] = $provider;
            }
        }
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    public function get(string $name): AiContextProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new \InvalidArgumentException("AI context provider '$name' not found");
        }
        return $this->providers[$name];
    }

    /** @return string[] */
    public function getNames(): array
    {
        return array_keys($this->providers);
    }
}
