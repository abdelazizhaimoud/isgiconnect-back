<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User\User;

class JobPosting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'requirements',
        'location',
        'type',
        'application_deadline',
        'status',
    ];

    public function company()
    {
        return $this->belongsTo(User::class, 'company_id');
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }
}
