<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menentukan integer user_id untuk tabel DSS (foreign key → users.id).
 *
 * Login API bisa mengisi session user dengan id "0", userId lain, atau hanya email.
 */
class SpkDssActorId
{
    public static function resolve(?Request $request = null): ?int
    {
        $request ??= request();
        $sess = session()->get('user');
        if (! is_array($sess)) {
            $sess = [];
        }

        foreach (['id', 'userId'] as $key) {
            if (!array_key_exists($key, $sess)) {
                continue;
            }

            $id = self::positiveIntFromMixed($sess[$key]);
            if ($id !== null && self::existsInUsersTable($id)) {
                return $id;
            }
        }

        $guardUser = $request->user();
        if ($guardUser !== null && $guardUser->getAuthIdentifier() !== null) {
            $id = self::positiveIntFromMixed($guardUser->getAuthIdentifier());
            if ($id !== null && self::existsInUsersTable($id)) {
                return $id;
            }
        }

        $email = $sess['email'] ?? null;
        if (is_string($email) && $email !== '' && Schema::hasTable('users')) {
            $rowId = DB::table('users')->where('email', $email)->value('id');
            $id = self::positiveIntFromMixed($rowId);
            if ($id !== null && self::existsInUsersTable($id)) {
                return $id;
            }
        }

        /** Coba pemetaan via tabel `user` jika ada (ID numerik sama dengan users). */
        if (is_string($email) && $email !== '' && Schema::hasTable('user')) {
            $rowId = DB::table('user')->where('email', $email)->value('id');
            $id = self::positiveIntFromMixed($rowId);
            if ($id !== null && self::existsInUsersTable($id)) {
                return $id;
            }
        }

        // Fallback aman: gunakan user DSS default agar modul tetap operasional.
        $defaultId = self::positiveIntFromMixed(env('DSS_DEFAULT_USER_ID'));
        if ($defaultId !== null && self::existsInUsersTable($defaultId)) {
            return $defaultId;
        }

        // Fallback terakhir: ambil user pertama pada tabel users.
        if (Schema::hasTable('users')) {
            $firstId = self::positiveIntFromMixed(DB::table('users')->orderBy('id')->value('id'));
            if ($firstId !== null) {
                return $firstId;
            }
        }

        return null;
    }

    private static function positiveIntFromMixed(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_float($value)) {
            $i = (int) $value;

            return $i > 0 && abs($value - $i) < PHP_FLOAT_EPSILON ? $i : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            $i = (int) $value;

            return $i > 0 ? $i : null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($filtered !== false) {
            return $filtered;
        }

        return null;
    }

    private static function existsInUsersTable(int $id): bool
    {
        if (! Schema::hasTable('users')) {
            return false;
        }

        return DB::table('users')->where('id', $id)->exists();
    }
}
