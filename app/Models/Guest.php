<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model {

    use HasFactory;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    protected $guarded = [];

    public $incrementing = false;

    public $timestamps = true;

    const CREATED_AT = 'registered_at';

    const UPDATED_AT = null;

    const VALID_CHARACTER = '0123456789ABCDEFabcdef';
    const ID_LENGTH = 5;
    const VALID_FORMAT = '/\A[A-Za-z]{2}-[0-9A-Fa-f]{5}\Z/';

    public static function calculateParity(string $id_sub): string {
        $digits = [];
        foreach (str_split($id_sub) as $char) {
            $digits[] = hexdec($char);
        }
        $parity_digits = ($digits[0] + $digits[1] * 3 + $digits[2] + $digits[3] * 3) % 0x10;
        return strtoupper(dechex($parity_digits));
    }

    public static function validate(string $guest_id): bool {
        if (!preg_match(self::VALID_FORMAT, $guest_id)) return false;
        $guest_id = strtoupper($guest_id);
        return self::calculateParity(substr($guest_id, 3, 4)) === $guest_id[-1];
    }


    public function reservation() {
        return $this->belongsTo(Reservation::class);
    }

    public function logs() {
        return $this->hasMany(ActivityLogEntry::class);
    }

    public function term() {
        return $this->belongsTo(Term::class);
    }
}
