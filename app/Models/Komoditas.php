<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Komoditas extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'komoditas';

    public $incrementing = false;
    protected $keyType = 'string';

    // Model dasar hanya untuk menunjang relasi CommodityParameter
}
