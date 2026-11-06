<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Values of is_published. They match the PublishStatus enum in the app,
     * which sends its ordinal.
     */
    public const STATUS_NOT_PUBLISHED = 0;
    public const STATUS_PUBLISHED = 1;
    public const STATUS_TRANSACTION_PENDING = 2;
    public const STATUS_TRANSACTION_COMPLETED = 3;
    public const STATUS_NEEDS_REVIEW = 4;
    public const STATUS_CLOSED = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $fillable = [
        'title',
        'content',
        'price',
        'user_id',
        'peer_id',
        'status',
        'is_premium',
        'latitude',
        'longitude',
        'city',
        'type',
        'can_trade_in',
        'is_published'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
