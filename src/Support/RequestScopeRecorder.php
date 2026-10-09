<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Support\Arr;
use RoBYCoNTe\FilamentFlow\Definition\RequestScope;

/**
 * What the requester picked for a request, from the payload of the transition that opens it to the
 * entry the history keeps.
 *
 * The pick travels in the payload under a key of its own, so it never lands among the values of
 * the record. It is checked against what the call declared — the same check wherever the
 * transition runs from, because a form can be bypassed and a payload cannot — and recorded with
 * the values the fields held at that moment: that is how a field is later known to have changed.
 */
final class RequestScopeRecorder
{
    /** Where the pick sits in the payload of the transition. */
    public const PAYLOAD_KEY = '_request_scope';

    /**
     * The payload without the pick: what is left is what the transition writes.
     *
     * @param  array<array-key,mixed>  $payload
     * @return array<array-key,mixed>
     */
    public static function withoutPick(array $payload): array
    {
        unset($payload[self::PAYLOAD_KEY]);

        return $payload;
    }

    /**
     * The pick a payload carries, normalised.
     *
     * @param  array<array-key,mixed>  $payload
     * @return array{paths: list<string>, attachments: list<int|string>}
     */
    public static function pick(array $payload): array
    {
        $pick = is_array($payload[self::PAYLOAD_KEY] ?? null) ? $payload[self::PAYLOAD_KEY] : [];

        $paths = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $path): string => is_string($path) ? trim($path, ". \t") : '',
                (array) ($pick['paths'] ?? []),
            ),
            static fn (string $path): bool => $path !== '',
        )));

        $attachments = array_values(array_unique(array_filter(
            (array) ($pick['attachments'] ?? []),
            static fn (mixed $id): bool => is_int($id) || (is_string($id) && $id !== ''),
        )));

        return ['paths' => $paths, 'attachments' => $attachments];
    }

    /**
     * What is wrong with the pick, against what the transition declared.
     *
     * @param  array<array-key,mixed>  $payload
     * @return array<string,list<string>> messages keyed by the payload key, empty when it holds
     */
    public static function errors(RequestScope $scope, array $payload): array
    {
        $pick = self::pick($payload);
        $messages = [];

        if ($scope->requiresSelection() && $pick['paths'] === []) {
            $messages[] = 'Choose at least one field that can be changed.';
        }

        foreach ($pick['paths'] as $path) {
            if (! $scope->allows($path)) {
                $messages[] = "The field [{$path}] cannot be opened by this request.";
            }
        }

        if ($pick['attachments'] !== [] && ! $scope->takesAttachments()) {
            $messages[] = 'This request does not take attachments.';
        }

        $max = $scope->toArray()['attachments']['max'] ?? null;

        if ($max !== null && count($pick['attachments']) > $max) {
            $messages[] = "A request takes at most {$max} attachments.";
        }

        return $messages === [] ? [] : [self::PAYLOAD_KEY => $messages];
    }

    /**
     * The entry the history keeps, or null when the pick opens nothing and attaches nothing.
     *
     * @param  array<array-key,mixed>  $payload
     * @param  array<array-key,mixed>  $values  the record's values, by path, as they stand now
     * @return array<string,mixed>|null
     */
    public static function entry(RequestScope $scope, array $payload, array $values): ?array
    {
        $pick = self::pick($payload);

        if ($pick['paths'] === [] && $pick['attachments'] === []) {
            return null;
        }

        $snapshot = [];

        foreach ($pick['paths'] as $path) {
            $snapshot[$path] = Arr::get($values, $path);
        }

        return [
            'mode' => $scope->mode(),
            'paths' => $pick['paths'],
            'snapshot' => $snapshot,
            'attachments' => $pick['attachments'],
            'require_change' => $scope->requiresChange(),
            'answered_by' => $scope->answeredByRole(),
        ];
    }
}
