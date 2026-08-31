<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PaymentDetail extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'date_of_payment_made',
        'reference_number',
        'payment_status',
        'proof_of_payment',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_payment_made' => 'datetime',
        ];
    }

    /**
     * The publicly accessible URL for the uploaded payment proof.
     */
    protected function proofUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->proof_of_payment ? Storage::disk('public')->url($this->proof_of_payment) : null);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(PlayerApplication::class, 'application_id');
    }
}
