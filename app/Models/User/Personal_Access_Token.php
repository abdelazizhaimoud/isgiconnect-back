<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
 
class Personal_Access_Token extends SanctumPersonalAccessToken
{
    use HasFactory;
}
