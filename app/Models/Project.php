<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['name'];

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }
}
