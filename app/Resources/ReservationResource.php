<?php

namespace App\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource {

    /**
     * リソースを配列へ変換する
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request) {
        return [
            'id' => $this->id,
            'term' => $this->term,
            'member_all' => $this->people_count,
            'member_checked_in' => $this->guest->count()
        ];
    }
}
