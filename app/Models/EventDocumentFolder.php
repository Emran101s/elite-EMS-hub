<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'module', 'parent_id', 'name', 'position'])]
class EventDocumentFolder extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('name');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EventDocument::class, 'folder_id');
    }

    /** A subfolder wears its module's colour so the drawer stays readable. */
    public function color(): string
    {
        return Event::moduleColor($this->module);
    }

    /** Everything inside, including nested subfolders. */
    public function totalDocuments(): int
    {
        return $this->documents()->count()
            + $this->children->sum(fn (self $child) => $child->totalDocuments());
    }

    /** Ancestors, outermost first — used to build the breadcrumb trail. */
    public function trail(): array
    {
        $trail = [];
        for ($node = $this; $node; $node = $node->parent) {
            array_unshift($trail, $node);
        }

        return $trail;
    }
}
