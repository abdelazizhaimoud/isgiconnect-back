<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User\User;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_posting_id',
        'student_id',
        'status',
        'resume_path',
        'cover_letter_path',
        'applied_at',
    ];

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
