<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'body',
        'correct_answer',
        'category',
        'asked_at',
    ];

    protected $casts = [
        'asked_at' => 'datetime',
    ];
}
