<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Concerns\HasDatabaseTransitions;

/**
 * A record whose key is an ULID, not a number: what a host with string keys has. The engine must not
 * assume a number anywhere it reads, compares or logs a key.
 *
 * @property string $id
 * @property string $title
 * @property float|null $amount
 * @property string|null $state
 * @property int|null $tenant_id
 */
class Document extends Model
{
    use HasDatabaseTransitions;
    use HasUlids;

    protected $table = 'test_documents';

    protected $fillable = ['title', 'amount', 'state', 'tenant_id'];

    /** The tenant whose workflow the document follows (a call of a platform, say). */
    public function getWorkflowTenantId(): ?int
    {
        return $this->tenant_id;
    }
}
