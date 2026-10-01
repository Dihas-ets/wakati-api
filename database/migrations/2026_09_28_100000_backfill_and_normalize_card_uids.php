<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 1. Donne un card_uid aux comptes qui n'en ont pas (rh, comptable créés avant
 *    l'ajout des badges) : sans cela ils n'apparaissent jamais dans
 *    valid_badges et leur badge est "inconnu" hors-ligne.
 * 2. Ramène les card_uid existants à la forme canonique (voir
 *    User::normalizeCardUid) pour qu'un badge apparié avec un lecteur soit
 *    reconnu avec un autre.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('users')->select('id', 'card_uid')->orderBy('id')->get();

        foreach ($rows as $row) {
            $target = $row->card_uid
                ? User::normalizeCardUid($row->card_uid)
                : User::newCardUid();

            if ($target === $row->card_uid) {
                continue;
            }

            $taken = DB::table('users')
                ->where('card_uid', $target)
                ->where('id', '!=', $row->id)
                ->exists();

            if (! $taken) {
                DB::table('users')->where('id', $row->id)->update(['card_uid' => $target]);
            }
        }
    }

    public function down(): void
    {
        // Irréversible volontairement : on ne ré-introduit pas de badges non normalisés.
    }
};
