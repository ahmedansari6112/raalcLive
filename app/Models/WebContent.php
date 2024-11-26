<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebContent extends Model
{
    use HasFactory;
    
    protected $table = 'web_content';

    protected $fillable = [
        "slug",
        "header_image",
        "sec_two_image",
        "sec_three_image",
        "sec_four_image",
        "sec_five_image",
        "gallery_images"
    ];


    public function translations()
    {
        return $this->hasMany(WebContainTranslation::class);
    }
}
