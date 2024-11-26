<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalSecretary extends Model
{
    use HasFactory;
    
    protected $fillable = ['legal_secretary_image', 'legal_secretary_email', 'qr_code_image','order_number'];
}
