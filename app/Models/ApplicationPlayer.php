<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ApplicationPlayer extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'player_id',
        'level_at_apply',
        'id_document_type',
        'id_document_path',
    ];

    /**
     * The publicly accessible URL for the uploaded ID document, if any.
     */
    protected function idDocumentUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->id_document_path ? Storage::disk('public')->url($this->id_document_path) : null);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(PlayerApplication::class, 'application_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
