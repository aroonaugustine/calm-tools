<?php

declare(strict_types=1);

namespace Portal\Core;

final class Tool
{
    private string $slug;
    private string $name;
    private string $description;
    private string $entry;
    private string $category;
    private array $tags;
    private string $type;
    private string $status;
    private bool $requiresAuth;
    private ?string $notes;
    private ?string $docs;

    public function __construct(array $config)
    {
        $this->slug = $config['slug'] ?? '';
        $this->name = $config['name'] ?? $this->slug;
        $this->description = $config['description'] ?? '';
        $this->entry = $config['entry'] ?? '';
        $this->category = $config['category'] ?? 'Misc';
        $this->tags = $config['tags'] ?? [];
        $this->type = $config['type'] ?? 'web';
        $this->status = $config['status'] ?? 'stable';
        $this->requiresAuth = (bool)($config['requires_auth'] ?? false);
        $this->notes = $config['notes'] ?? null;
        $this->docs = $config['docs'] ?? null;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function entry(): string
    {
        return $this->entry;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function tags(): array
    {
        return $this->tags;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function requiresAuth(): bool
    {
        return $this->requiresAuth;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function docs(): ?string
    {
        return $this->docs;
    }

    public function isCli(): bool
    {
        return $this->type === 'cli';
    }

    public function launchUrl(): ?string
    {
        if ($this->isCli()) {
            return null;
        }

        return $this->entry !== '' ? $this->entry : null;
    }

    public function tagString(): string
    {
        return implode(',', $this->tags);
    }
}
