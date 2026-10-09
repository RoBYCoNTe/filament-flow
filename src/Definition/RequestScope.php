<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

use InvalidArgumentException;

/**
 * What the office may add to a request, declared once on the transition that opens it: the
 * documents it can attach and the fields it can open to the answering side.
 *
 * It is the universe of the choice, not the choice: each request records what the office picked
 * inside it (see `ResolvesRequestScope`). A declaration travels in the metadata of the
 * transition, so the sync, the plan and the export carry it with no table of its own.
 *
 *     RequestScope::make()
 *         ->editableFields(only: ['applicant', 'documents'], except: ['applicant.fiscal_code'])
 *         ->attachments(accepts: ['pdf'], max: 5, maxSizeMb: 10)
 *         ->requireSelection()
 *         ->requireChange()
 *         ->answeredBy('@owner');
 */
final class RequestScope
{
    public const MODE_EXCLUSIVE = 'exclusive';

    public const MODE_ADDITIVE = 'additive';

    /** @var list<string>|null */
    private ?array $only = null;

    /** @var list<string> */
    private array $except = [];

    private bool $attachmentsEnabled = false;

    /** @var list<string> */
    private array $accepts = ['pdf'];

    private int $maxFiles = 5;

    private int $maxSizeMb = 10;

    private bool $requireSelection = false;

    private bool $requireChange = false;

    private string $answeredBy = '@owner';

    private string $mode = self::MODE_EXCLUSIVE;

    public static function make(): self
    {
        return new self;
    }

    /**
     * The fields the office can choose from: `only` is the whitelist (everything when omitted),
     * `except` takes paths out of it, together with whatever lies beneath them.
     *
     * @param  list<string>|null  $only
     * @param  list<string>  $except
     */
    public function editableFields(?array $only = null, array $except = []): self
    {
        $this->only = $only === null ? null : self::paths($only);
        $this->except = self::paths($except);

        return $this;
    }

    /**
     * The documents the office may attach to the request.
     *
     * A `$max` of one makes it a single document: the dialog asks for one file instead of a list.
     *
     * @param  list<string>  $accepts  extensions, without the dot
     */
    public function attachments(array $accepts = ['pdf'], int $max = 5, int $maxSizeMb = 10): self
    {
        if ($max < 1 || $maxSizeMb < 1) {
            throw new InvalidArgumentException('A request attachment limit must be at least 1.');
        }

        $accepts = array_values(array_unique(array_filter(array_map(
            static fn (string $extension): string => strtolower(ltrim(trim($extension), '.')),
            $accepts,
        ), static fn (string $extension): bool => $extension !== '')));

        if ($accepts === []) {
            throw new InvalidArgumentException('A request that takes attachments must accept at least one type.');
        }

        $this->attachmentsEnabled = true;
        $this->accepts = $accepts;
        $this->maxFiles = $max;
        $this->maxSizeMb = $maxSizeMb;

        return $this;
    }

    /** The office has to pick at least one field. */
    public function requireSelection(bool $require = true): self
    {
        $this->requireSelection = $require;

        return $this;
    }

    /** The answering side has to change at least one requested field before it can answer. */
    public function requireChange(bool $require = true): self
    {
        $this->requireChange = $require;

        return $this;
    }

    /** The role that receives the opened fields. */
    public function answeredBy(string $role): self
    {
        $role = trim($role);

        if ($role === '') {
            throw new InvalidArgumentException('A request scope needs the role that answers.');
        }

        $this->answeredBy = $role;

        return $this;
    }

    /** Only the chosen fields can be changed (the default). */
    public function exclusive(): self
    {
        $this->mode = self::MODE_EXCLUSIVE;

        return $this;
    }

    /** The chosen fields are opened on top of what the state already allows. */
    public function additive(): self
    {
        $this->mode = self::MODE_ADDITIVE;

        return $this;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function isExclusive(): bool
    {
        return $this->mode === self::MODE_EXCLUSIVE;
    }

    public function answeredByRole(): string
    {
        return $this->answeredBy;
    }

    public function takesAttachments(): bool
    {
        return $this->attachmentsEnabled;
    }

    /**
     * The extensions the request takes, without the dot.
     *
     * @return list<string>
     */
    public function attachmentExtensions(): array
    {
        return $this->accepts;
    }

    /** The most files a request takes. */
    public function attachmentLimit(): int
    {
        return $this->maxFiles;
    }

    /** The largest a file can be, in megabytes. */
    public function attachmentMaxSizeMb(): int
    {
        return $this->maxSizeMb;
    }

    public function requiresSelection(): bool
    {
        return $this->requireSelection;
    }

    public function requiresChange(): bool
    {
        return $this->requireChange;
    }

    /**
     * Whether the office may open a path: inside the whitelist and not under an exception.
     */
    public function allows(string $path): bool
    {
        foreach ($this->except as $excluded) {
            if (self::within($path, $excluded)) {
                return false;
            }
        }

        if ($this->only === null) {
            return true;
        }

        foreach ($this->only as $allowed) {
            if (self::within($path, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The whitelist, or null when every field may be chosen.
     *
     * @return list<string>|null
     */
    public function whitelist(): ?array
    {
        return $this->only;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'only' => $this->only,
            'except' => $this->except,
            'attachments' => $this->attachmentsEnabled
                ? ['accepts' => $this->accepts, 'max' => $this->maxFiles, 'max_size_mb' => $this->maxSizeMb]
                : null,
            'require_selection' => $this->requireSelection,
            'require_change' => $this->requireChange,
            'answered_by' => $this->answeredBy,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $scope = new self;

        $mode = $data['mode'] ?? self::MODE_EXCLUSIVE;

        if (! in_array($mode, [self::MODE_EXCLUSIVE, self::MODE_ADDITIVE], true)) {
            throw new InvalidArgumentException("Unknown request scope mode [{$mode}].");
        }

        $scope->mode = $mode;
        $scope->editableFields(
            is_array($data['only'] ?? null) ? $data['only'] : null,
            is_array($data['except'] ?? null) ? $data['except'] : [],
        );

        if (is_array($data['attachments'] ?? null)) {
            $scope->attachments(
                (array) ($data['attachments']['accepts'] ?? ['pdf']),
                (int) ($data['attachments']['max'] ?? 5),
                (int) ($data['attachments']['max_size_mb'] ?? 10),
            );
        }

        $scope->requireSelection((bool) ($data['require_selection'] ?? false));
        $scope->requireChange((bool) ($data['require_change'] ?? false));
        $scope->answeredBy((string) ($data['answered_by'] ?? '@owner'));

        return $scope;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private static function paths(array $paths): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (string $path): string => trim($path, ". \t"), $paths),
            static fn (string $path): bool => $path !== '',
        )));
    }

    private static function within(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, $root.'.');
    }
}
