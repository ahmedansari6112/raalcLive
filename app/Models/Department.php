<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    
    protected $fillable = ['department_image', 'department_team_ids'];
    
    protected $casts = [
        'department_team_ids' => 'array',
    ];

    public function teams()
    {
        return $this->hasMany(Team::class, 'id', 'department_team_ids');
    }
}
