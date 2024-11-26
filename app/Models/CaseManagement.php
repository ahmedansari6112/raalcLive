<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseManagement extends Model
{
    use HasFactory;
    
    protected $table = 'case_management';
    
    protected $fillable = ['client_id', 'client_name', 'client_email', 'case_number', 'case_title', 'team_member_ids', 'legal_secretaries_ids'];
    
    protected $casts = [
        'client_id' => 'integer',
        'team_member_ids' => 'array',
        'legal_secretaries_ids' => 'array',
    ];

    public function teams()
    {
        return $this->hasMany(Team::class, 'id', 'team_member_ids');
    }
    
    public function legalSecretaries()
    {
        return $this->hasMany(LegalSecretary::class, 'id', 'legal_secretaries_ids');
    }
    
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id'); // Ensure the foreign key is correctly specified
    }
    
    
    public function updates()
    {
        return $this->hasMany(CaseUpdate::class, 'case_id')->orderBy('created_at', 'DESC');
    }
}
