<?php

namespace App\Http\Controllers;

use App\Exceptions\HttpExceptionWithErrorCode;
use App\Resources\ActivityLogEntryResource;
use App\Resources\GuestResource;
use App\Models\Guest;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\ActivityLogEntry;

class GuestController extends Controller {
    public function show(Request $request, $id) {
        $guest = Guest::find($id);
        if (!$guest) {
            abort(404);
        }

        return response()->json(new GuestResource($guest));
    }

    public function index() {
        return response()->json(GuestResource::collection(Guest::all()));
    }

    public function checkIn(Request $request) {
        $this->validate($request, [
            'reservation_id' => ['string', 'required'],
            'guest_id' => ['string', 'required']
        ]);

        if (!preg_match('/^[A-Z]{2,3}-[2-578ac-kmnpr-z]{5}$/', $request->guest_id)) {
            throw new HttpExceptionWithErrorCode(400, 'INVALID_WRISTBAND_CODE');
        }

        $reservation = Reservation::find($request->reservation_id);

        if (!$reservation) throw new HttpExceptionWithErrorCode(400, 'RESERVATION_NOT_FOUND');

        $reservation_error_code = $reservation->getErrorCode();

        if ($reservation_error_code !== null) {
            throw new HttpExceptionWithErrorCode(400, $reservation_error_code);
        }

        if (Guest::find($request->guest_id)) {
            throw new HttpExceptionWithErrorCode(400, 'ALREADY_USED_WRISTBAND');
        }

        $term = $reservation->term;

        if (strpos($request->guest_id, config('cappuccino.guest_types')[$term->guest_type]['prefix']) !== 0
        ) {
            throw new HttpExceptionWithErrorCode(400, 'WRONG_WRISTBAND_COLOR');
        }


        $guest = Guest::create(
            [
                'id' => $request->guest_id,
                'term_id' => $term->id,
                'reservation_id' => $request->reservation_id
            ]
        );

        // TODO: 複数人で処理するときの扱いを考える (docsの編集待ち)
        $reservation->update(['guest_id' => $guest->id]);

        return response()->json(new GuestResource($guest));
    }

    public function checkOut($id) {

        $guest = Guest::find($id);
        if (!$guest) {
            throw new HttpExceptionWithErrorCode(400, 'GUEST_NOT_FOUND');
        }

        if ($guest->exited_at !== null) {
            throw new HttpExceptionWithErrorCode(400, 'GUEST_ALREADY_EXITED');
        }

        $guest->update(['exited_at' => Carbon::now()]);

        return response()->json(new GuestResource($guest));
    }

    public function showLog(Request $request, $id) {
        $guest = Guest::find($id);
        if (!$guest) {
            abort(404);
        }
        $logs = ActivityLogEntry::query()->where('guest_id', $id)->get();
        return response()->json(ActivityLogEntryResource::collection($logs));
    }
}