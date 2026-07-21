<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menentukan user_id untuk tabel DSS dengan sumber utama `user.id` API.
 */
class SpkDssActorId
{
    public static function resolve(?Request $request = null): ?string
    {
        $request ??= request();
        $sess = session()->get('user');
        if (! is_array($sess)) {
            $sess = [];
        }

        foreach (['id', 'userId'] as $key) {
            if (! array_key_exists($key, $sess)) {
                continue;
            }

            $id = self::stringIdFromMixed($sess[$key]);
            if ($id !== null && self::existsInUserTable($id)) {
                return $id;
            }
        }

        $guardUser = $request->user();
        if ($guardUser !== null && $guardUser->getAuthIdentifier() !== null) {
            $id = self::stringIdFromMixed($guardUser->getAuthIdentifier());
            if ($id !== null && self::existsInUserTable($id)) {
                return $id;
            }
        }

        $email = $sess['email'] ?? null;
        if (is_string($email) && $email !== '' && Schema::hasTable('user')) {
            $rowId = DB::table('user')->where('email', $email)->value('id');
            $id = self::stringIdFromMixed($rowId);
            if ($id !== null && self::existsInUserTable($id)) {
                return $id;
            }
        }

        $defaultId = self::stringIdFromMixed(env('DSS_DEFAULT_USER_ID'));
        if ($defaultId !== null && self::existsInUserTable($defaultId)) {
            return $defaultId;
        }

        if (Schema::hasTable('user')) {
            $firstId = self::stringIdFromMixed(DB::table('user')->orderBy('createdAt')->value('id'));
            if ($firstId !== null) {
                return $firstId;
            }
        }

        // Fallback UUID: cari user di tabel 'user' (singular) by email — skip positiveIntFromMixed
        $email = $sess['email'] ?? null;
        if (is_string($email) && $email !== '' && Schema::hasTable('user')) {
            $uuidRow = DB::table('user')->where('email', $email)->first(['id']);
            if ($uuidRow !== null && is_string($uuidRow->id)) {
                // Gunakan crc32 hash UUID sebagai ID numerik positif untuk session DSS
                return crc32($uuidRow->id) & 0x7FFFFFFF;
            }
        }

        return null;
    }

    private static function stringIdFromMixed(mixed $value): ?string
    {
        if ($value === null || $value === '' || is_bool($value)) {
            return null;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $id = trim((string) $value);

        return $id !== '' ? $id : null;
    }

    private static function existsInUserTable(string $id): bool
    {
        if (! Schema::hasTable('user')) {
            return false;
        }

        return DB::table('user')->where('id', $id)->exists();
    }
}
