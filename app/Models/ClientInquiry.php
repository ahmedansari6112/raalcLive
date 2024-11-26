<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientInquiry extends Model
{
    use HasFactory;
    
    protected $table = 'client_inquiries';
    
    protected $fillable = ['client_id', 'message', 'user_type'];
    
    protected $casts = [
        'client_id' => 'integer'
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id'); // Ensure the foreign key is correctly specified
    }
}
