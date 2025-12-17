<?php

declare(strict_types=1);

namespace Portal\Core;

final class ToolRegistry
{
    /** @var Tool[] */
    private array $tools;

    public static function fromConfig(string $configPath): self
    {
        $tools = require $configPath;
        if (!is_array($tools)) {
            throw new \RuntimeException('Tools configuration must return an array.');
        }

        return new self($tools);
    }

    /**
     * @param array<int,array<string,mixed>> $tools
     */
    public function __construct(array $tools)
    {
        $this->tools = array_map(static fn (array $tool): Tool => new Tool($tool), $tools);

        usort($this->tools, static fn (Tool $a, Tool $b): int => strcmp($a->name(), $b->name()));
    }

    /**
     * @return Tool[]
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * @return array<string, Tool[]>
     */
    public function groupedByCategory(): array
    {
        $groups = [];

        foreach ($this->tools as $tool) {
            $groups[$tool->category()][] = $tool;
        }

        ksort($groups);

        return $groups;
    }

    /**
     * @return string[]
     */
    public function categories(): array
    {
        $categories = [];

        foreach ($this->tools as $tool) {
            $categories[$tool->category()] = true;
        }

        $names = array_keys($categories);
        sort($names);

        return $names;
    }

    /**
     * @return string[]
     */
    public function tagIndex(): array
    {
        $tags = [];

        foreach ($this->tools as $tool) {
            foreach ($tool->tags() as $tag) {
                $tags[$tag] = true;
            }
        }

        $list = array_keys($tags);
        sort($list);

        return $list;
    }
}
