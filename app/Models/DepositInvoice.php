<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Een voorschotfactuur in Billit, aangemaakt na een getekende offerte in
 * GoHighLevel. Eén rij per getekend document (`source_id`).
 */
class DepositInvoice extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_CREATED = 'created';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'deal_amount_excl' => 'decimal:2',
            'amount_excl' => 'decimal:2',
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
