<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Olt extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['name', 'notes'];

    public function plaques(): HasMany
    {
        return $this->hasMany(Plaque::class);
    }
}
