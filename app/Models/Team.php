<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;
    
    protected $fillable = ['slug', 'lowyer_image', 'lawyer_email', 'qr_code_image','number_of_cases','order_number'];
}
