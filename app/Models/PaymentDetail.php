<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function application(): BelongsTo
    {
        return $this->belongsTo(PlayerApplication::class, 'application_id');
    }
}
