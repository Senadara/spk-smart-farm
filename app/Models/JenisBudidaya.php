<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class JenisBudidaya extends Model
{
    use HasUuids;

    protected $table = 'jenisBudidaya';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'nama',
        'latin',
        'tipe',
        'deskripsi',
        'isDeleted',
        'status',
    ];
}
