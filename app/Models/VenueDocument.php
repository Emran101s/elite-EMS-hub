<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One of a venue's own files — a contract, a floor plan, a technical spec,
 * an insurance certificate, a permit. Mirrors EventDocument closely; the
 * one deliberate departure is INLINE_MIMES referencing EventDocument's own
 * constant rather than duplicating it, so a security-relevant allowlist has
 * exactly one source of truth across both document models.
 */
#[Fillable(['tenant_id', 'venue_id', 'category', 'status', 'name', 'original_name',
    'path', 'disk', 'mime', 'size', 'uploaded_by', 'notes'])]
class VenueDocument extends Model
{
    use Auditable;
    use BelongsToTenant;

    public const CATEGORIES = ['contract', 'floor_plan', 'tech_spec', 'insurance', 'permit', 'other'];

    public const CONTRACT_STATUSES = ['draft', 'sent', 'signed', 'expired'];

    /** Mime types we are willing to hand a browser to render in a tab — see EventDocument::INLINE_MIMES for why SVG is excluded. */
    public const INLINE_MIMES = EventDocument::INLINE_MIMES;

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'floor_plan' => 'Floor Plan',
            'tech_spec' => 'Technical Spec',
            default => ucfirst($this->category),
        };
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->original_name, PATHINFO_EXTENSION) ?: 'FILE');
    }

    /** Kept for display decisions (icons, thumbnails); never use it to gate output. */
    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    /** Browsers can show these in a tab; everything else is a download. */
    public function isViewable(): bool
    {
        return in_array((string) $this->mime, self::INLINE_MIMES, true);
    }

    public function sizeForHumans(): string
    {
        $bytes = max(0, $this->size);

        foreach (['B', 'KB', 'MB', 'GB'] as $i => $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return ($i === 0 ? $bytes : round($bytes, 1)).' '.$unit;
            }
            $bytes /= 1024;
        }

        return $bytes.' B';
    }

    /** label + badge class per contract status — shared by the tab list and the header pill. */
    public static function contractStatusMeta(): array
    {
        return [
            'draft' => ['Draft', 'bg-eo-bg text-eo-muted'],
            'sent' => ['Sent', 'bg-eo-warn-soft text-eo-warn-ink'],
            'signed' => ['Signed', 'bg-eo-ok-soft text-eo-ok-ink'],
            'expired' => ['Expired', 'bg-eo-risk-soft text-eo-risk-ink'],
        ];
    }

    /** Removes the stored file when the row goes, so uploads don't orphan. */
    protected static function booted(): void
    {
        static::deleting(function (self $doc) {
            Storage::disk($doc->disk)->delete($doc->path);
        });
    }
}
