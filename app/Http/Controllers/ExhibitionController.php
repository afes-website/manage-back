<?php

namespace App\Http\Controllers;

use App\Exceptions\HttpExceptionWithErrorCode;
use App\Resources\ActivityLogEntryResource;
use App\Resources\ExhibitionResource;
use App\Resources\GuestResource;
use App\Models\Exhibition;
use App\Models\Guest;
use App\Models\Term;
use App\Models\ActivityLogEntry;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ExhibitionController extends Controller {
    public function index() {
        $exh_status = [];
        $all_limit = 0;
        foreach (Exhibition::all() as $exh) {
            $all_limit += $exh->capacity;
            $exh_status[$exh->id] = new ExhibitionResource($exh);
        }

        $all_counts = [];
        foreach (Term::all() as $term) {
            $cnt = Guest::query()->whereNull('exited_at')->where('term_id', $term->id)->count();
            if ($cnt !== 0) $all_counts[$term->id] = $cnt;
        }
        return response()->json([
            'exh' => $exh_status,
            'all' => [
                'count' => $all_counts,
                'limit' => $all_limit
            ]
        ]);
    }

    public function show(Request $request, $id) {
        $exhibition = Exhibition::find($id);
        if (!$exhibition) {
            abort(404);
        }

        return response()->json(new ExhibitionResource($exhibition));
    }

    public function enter(Request $request) {
        $this->validate($request, [
            'guest_id' => ['string', 'required']
        ]);

        $user_id = $request->user()->id;
        $guest = Guest::find($request->guest_id);
        $exh = Exhibition::find($user_id);
        $current = Carbon::now();

        if (!$exh) throw new HttpExceptionWithErrorCode(400, 'EXHIBITION_NOT_FOUND');
        if (!$guest) throw new HttpExceptionWithErrorCode(400, 'GUEST_NOT_FOUND');

        if ($guest->exh_id === $user_id)
            throw new HttpExceptionWithErrorCode(400, 'GUEST_ALREADY_ENTERED');

        if ($exh->capacity === $exh->guest_count)
            throw new HttpExceptionWithErrorCode(400, 'PEOPLE_LIMIT_EXCEEDED');

        if ($guest->exited_at !== null)
            throw new HttpExceptionWithErrorCode(400, 'GUEST_ALREADY_EXITED');

        if (new Carbon($guest->term->exit_scheduled_time) < $current)
            throw new HttpExceptionWithErrorCode(400, 'EXIT_TIME_EXCEEDED');


        $guest->update(['exh_id' => $exh->id]);

        ActivityLogEntry::create([
            'exh_id' => $exh->id,
            'log_type' => 'enter',
            'guest_id' => $guest->id
        ]);

        return response()->json(new GuestResource($guest));
    }

    public function exit(Request $request) {
        $this->validate($request, [
            'guest_id' => ['string', 'required']
        ]);

        $user_id = $request->user()->id;
        $guest = Guest::find($request->guest_id);
        $exh = Exhibition::find($user_id);

        if (!$exh) throw new HttpExceptionWithErrorCode(400, 'EXHIBITION_NOT_FOUND');
        if (!$guest) throw new HttpExceptionWithErrorCode(400, 'GUEST_NOT_FOUND');

        if ($guest->exited_at !== null)
            throw new HttpExceptionWithErrorCode(400, 'GUEST_ALREADY_EXITED');

        $guest->update(['exh_id' => null]);

        ActivityLogEntry::create([
            'exh_id' => $exh->id,
            'log_type' => 'exit',
            'guest_id' => $guest->id
        ]);

        return response()->json(new GuestResource($guest));
    }

    public function showLog(Request $request) {
        $id = $request->user()->id;
        $guest = Exhibition::find($id);
        if (!$guest) {
            abort(500, 'ExhibitionRoom Not found');
        }
        $logs = ActivityLogEntry::query()->where('exh_id', $id)->get();
        return response()->json(ActivityLogEntryResource::collection($logs));
    }
}