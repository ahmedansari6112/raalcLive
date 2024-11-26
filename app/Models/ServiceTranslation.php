<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceTranslation extends Model
{
    use HasFactory;
    
    protected $table = 'service_translations';

    protected $fillable = [
        "translated_value",
        "language",
        "service_id",
    ];

    public function services()
    {
        return $this->belongsTo(Service::class);
    }
}
