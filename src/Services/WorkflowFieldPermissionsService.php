<?php

namespace RoBYCoNTe\FilamentFlow\Services;

class WorkflowFieldPermissionsService
{
    use ReadsCreationAndColumnPermissions;
    use ReadsFieldPermissions;
    use ResolvesFieldPermissionContext;
}
