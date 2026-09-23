<?php

namespace RoBYCoNTe\FilamentFlow\Services;

/**
 * What a state allows on each field: whether it is visible, editable, locked, read-only or
 * required for this user in this state.
 *
 * It is a composition on purpose, and it holds no behaviour of its own: the rules are read by
 * several callers — the renderer, the panel, an API — and every one of them asks the same
 * question through the same door.
 */
class WorkflowFieldPermissionsService
{
    use ReadsCreationAndColumnPermissions;
    use ReadsFieldPermissions;
    use ResolvesFieldPermissionContext;
}
