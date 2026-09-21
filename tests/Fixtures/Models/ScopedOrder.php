<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Concerns\HasDatabaseTransitions;

/**
 * Test model with scope-based workflow discrimination.
 * scope_id maps to workflow.tenant_id, mimicking Application→scheme_id.
 *
 * @property int $id
 * @property string $state
 * @property int|null $scope_id
 *
 * @method static create(array $array)
 */
class ScopedOrder extends Model
{
    use HasDatabaseTransitions;

    protected $table = 'test_scoped_orders';

    protected $fillable = ['state', 'scope_id', 'user_id'];

    protected $casts = ['state' => 'string'];

    public function getWorkflowTenantId(): ?int
    {
        return $this->scope_id;
    }
}
