<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseUpdate extends Model
{
    use HasFactory;
    
    protected $table = 'case_updates';
    
    protected $fillable = ['case_id', 'message', 'user_type'];
    
    protected $casts = [
        'case_id' => 'integer'
    ];

    public function clientCase()
    {
        return $this->belongsTo(CaseManagement::class, 'case_id'); // Ensure the foreign key is correctly specified
    }
}
