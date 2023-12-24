<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'receiver_id', 'context'];

    protected $hidden = ['deleted_at'];

    protected $cast = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

}
