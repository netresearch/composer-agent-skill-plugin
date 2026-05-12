<?php

declare(strict_types=1);

namespace Netresearch\ComposerAgentSkillPlugin\DirectSkills;

/**
 * One entry from extra.ai-agent-skills.sources[].
 *
 * @phpstan-type TShape array{name: string, type: string, url?: string, ref?: string|null, path?: string|null, skills: list<string>, install-mode?: string, trust?: mixed}
 */
final class SourceEntry
{
    /**
     * @param list<string> $skills
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $url,
        public readonly ?string $ref,
        public readonly ?string $path,
        public readonly array $skills,
        public readonly string $installMode,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): ?self
    {
        $name = $row['name'] ?? null;
        $type = $row['type'] ?? null;
        $skills = $row['skills'] ?? null;
        if (!is_string($name) || $name === '' || !is_string($type) || $type === '') {
            return null;
        }
        if (!is_array($skills)) {
            return null;
        }
        $list = [];
        foreach ($skills as $s) {
            if (is_string($s)) {
                $list[] = $s;
            }
        }
        if ($list === []) {
            return null;
        }
        $mode = isset($row['install-mode']) && is_string($row['install-mode']) ? $row['install-mode'] : 'copy';

        if ($type === 'path') {
            $pathOnly = isset($row['path']) && is_string($row['path']) ? $row['path'] : null;
            if ($pathOnly === null || $pathOnly === '') {
                return null;
            }

            return new self($name, 'path', null, null, $pathOnly, $list, $mode);
        }

        $url = isset($row['url']) && is_string($row['url']) ? $row['url'] : null;
        $ref = array_key_exists('ref', $row) ? (is_string($row['ref']) || $row['ref'] === null ? $row['ref'] : null) : null;
        $path = isset($row['path']) && is_string($row['path']) ? $row['path'] : null;

        return new self($name, $type, $url, $ref, $path, $list, $mode);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $a = [
            'name' => $this->name,
            'type' => $this->type,
            'skills' => $this->skills,
            'install-mode' => $this->installMode,
        ];
        if ($this->type === 'path') {
            if ($this->path !== null) {
                $a['path'] = $this->path;
            }

            return $a;
        }
        if ($this->url !== null) {
            $a['url'] = $this->url;
        }
        if ($this->ref !== null) {
            $a['ref'] = $this->ref;
        }
        if ($this->path !== null) {
            $a['path'] = $this->path;
        }

        return $a;
    }
}
