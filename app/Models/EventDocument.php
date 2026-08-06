<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['event_id', 'module', 'folder_id', 'name', 'original_name', 'path', 'disk', 'mime',
    'size', 'uploaded_by', 'notes'])]
class EventDocument extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeForModule(Builder $q, ?string $module): Builder
    {
        return $module ? $q->where('module', $module) : $q->whereNull('module');
    }

    /** "Accommodation", or "Event-wide" when it belongs to no single module. */
    public function moduleLabel(): string
    {
        return $this->module
            ? (Event::HUB_MODULES[$this->module][0] ?? ucfirst($this->module))
            : 'Event-wide';
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->original_name, PATHINFO_EXTENSION) ?: 'FILE');
    }

    /**
     * Mime types we are willing to hand a browser to render in a tab.
     *
     * An explicit allowlist, deliberately not `image/*`. An SVG is a document
     * that can carry <script>, so an uploaded .svg served inline from our own
     * origin runs with the viewer's session — and the app's CSP still allows
     * inline script. Uploading one is fine; rendering it is not. Anything off
     * this list is still downloadable, it just never opens in a tab.
     */
    public const INLINE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'application/pdf',
    ];

    /**
     * Whether the file *is* an image — a different question from whether it is
     * safe to render, which is what conflating the two got wrong. Kept for
     * display decisions (icons, thumbnails); never use it to gate output.
     */
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

    /** Removes the stored file when the row goes, so uploads don't orphan. */
    protected static function booted(): void
    {
        static::deleting(function (self $doc) {
            Storage::disk($doc->disk)->delete($doc->path);
        });
    }
}
