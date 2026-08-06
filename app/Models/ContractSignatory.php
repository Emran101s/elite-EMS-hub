<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A party to a contract who must sign it. Signed once `signed_at` is set, at
 * which point the audit fields (who / when / where / what-hash) are frozen.
 */
#[Fillable(['tenant_id',
    'contract_id', 'role', 'name', 'email', 'order',
    'signed_at', 'signature_data', 'signed_ip', 'signed_hash'])]
class ContractSignatory extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> */
    public const ROLES = [
        'organiser' => 'For Elite Business Hub',
        'client' => 'For the Client',
        'vendor' => 'For the Supplier',
        'speaker' => 'The Speaker',
        'sponsor' => 'For the Sponsor',
        'witness' => 'Witness',
    ];

    protected $casts = ['signed_at' => 'datetime', 'order' => 'integer'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EventContract::class, 'contract_id');
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? 'Signatory';
    }

    /** Two-letter monogram for the deck / ceremony avatars. */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $letters = collect($words)->filter()->take(2)->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)));

        return $letters->implode('') ?: '—';
    }
}
