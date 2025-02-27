<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;
    
    protected $table = 'services';

    protected $fillable = [
        "slug",
        "sec_one_image",
        "service_category_id"
    ];

    public function translations()
    {
        return $this->hasMany(ServiceTranslation::class);
    }
}
