<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LoginHistory extends Model
{
    use HasUuids;

    /**
     * Kolom DB menggunakan camelCase (dari Sequelize/node-api),
     * jadi matikan konversi otomatis Laravel ke snake_case.
     */
    public static $snakeAttributes = false;

    /**
     * Tabel menggunakan UUID sebagai primary key.
     */
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Nonaktifkan timestamps otomatis Laravel.
     * Kolom createdAt/updatedAt di-handle oleh DEFAULT CURRENT_TIMESTAMP di DB.
     */
    public $timestamps = false;

    protected $fillable = [
        'userId',
        'email',
        'name',
        'role',
        'ipAddress',
        'userAgent',
        'createdAt',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
    ];

    /**
     * Relasi ke tabel user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
