<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'ad_id',
        'deal_status',
        'first_member_id',
        'second_member_id',
        'deal_waiting_start_time',
        'deal_waiting_end_time',
        'refund_waiting_start_time',
        'refund_waiting_end_time',
        'first_member_evaluation',
        'second_member_evaluation',
        'code',
        'proposed_book',
        'book_name',
        'book_author',
        'type',
        'deadline'
        ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    protected $hidden = ['pivot', 'updated_at', 'created_at'];
}
