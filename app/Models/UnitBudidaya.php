<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitBudidaya extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'unitBudidaya';

    public $incrementing = false;
    protected $keyType = 'string';

    // Model dasar hanya untuk menunjang relasi IotDevice
}
