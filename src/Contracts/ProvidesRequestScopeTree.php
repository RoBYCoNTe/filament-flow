<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Definition\RequestScope;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;

/**
 * What a host implements so the requester can pick the fields of a request: the fields of the record
 * as the tree a person sees them in, already limited to what the call allows the requester to choose
 * and to what the answering side will see in the state the record goes back to.
 *
 * Optional: when nothing is bound the dialog of a request offers no picker.
 */
interface ProvidesRequestScopeTree
{
    /**
     * The nodes of the tree, outermost first. A container carries every path under it, so
     * choosing it chooses all of them; a field carries only its own.
     *
     * @return list<array{key: string, label: string, kind: string, paths: list<string>, children: list<array<string, mixed>>}>
     */
    public function treeFor(Model $record, WorkflowTransition $transition, RequestScope $scope): array;
}
