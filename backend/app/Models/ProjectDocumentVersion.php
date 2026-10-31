<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Une version d'un document de projet : en ajout seul, jamais modifiée ni supprimée. */
class ProjectDocumentVersion extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['project_id', 'type', 'zone_id', 'version', 'file_name', 'size', 'checksum', 'content', 'comment', 'uploaded_by'];

    protected $hidden = ['content'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Une version de document ne se modifie pas : ajoutez une nouvelle version.'));
        static::deleting(fn () => throw new LogicException('Une version de document ne se supprime pas.'));
    }

    protected function casts(): array
    {
        return ['type' => DocumentType::class, 'version' => 'integer', 'size' => 'integer'];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Contenu binaire du fichier. */
    public function bytes(): string
    {
        return (string) base64_decode($this->content, true);
    }
}
