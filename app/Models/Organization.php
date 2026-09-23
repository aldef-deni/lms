<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
