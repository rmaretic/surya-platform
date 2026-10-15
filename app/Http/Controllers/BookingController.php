<?php

namespace App\Http\Controllers;

use App\Actions\CreateBooking;
use App\Actions\ManageBooking;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function cancellation(Request $request, Booking $booking, ManageBooking $manage): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return response()->json(['cancellation' => $manage->preview($actor, $booking)]);
    }

    public function cancel(Request $request, Booking $booking, ManageBooking $manage): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $result = $manage->handle($actor, $booking, $request->all());

        return response()->json(['booking' => $result->only(['id', 'class_session_id', 'status'])]);
    }

    public function move(Request $request, Booking $booking, ManageBooking $manage): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $result = $manage->handle($actor, $booking, $request->all(), true);

        return response()->json(['booking' => $result->only(['id', 'class_session_id', 'status'])]);
    }

    public function store(Request $request, CreateBooking $create): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $booking = $create->handle($actor, $request->all());

        return response()->json(['booking' => $booking->only(['id', 'class_session_id', 'status'])]);
    }
}
