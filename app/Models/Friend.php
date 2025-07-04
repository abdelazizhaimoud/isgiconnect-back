<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User\User;

class Friend extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'friend_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function friendUser()
    {
        return $this->belongsTo(User::class, 'friend_id');
    }
} 