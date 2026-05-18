<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ticket\PurchaseTicketRequest;
use App\Models\TicketType;
use App\Models\UserTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    public function types(): JsonResponse
    {
        $types = TicketType::query()
            ->orderBy('price')
            ->get()
            ->map(fn (TicketType $type) => $this->typePayload($type));

        return response()->json(['data' => $types]);
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = UserTicket::query()
            ->with('ticketType')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('purchase_date')
            ->get()
            ->map(fn (UserTicket $ticket) => $this->ticketPayload($ticket));

        return response()->json(['data' => $tickets]);
    }

    public function purchase(PurchaseTicketRequest $request): JsonResponse
    {
        $data = $request->validated();
        $ticketType = TicketType::query()->findOrFail($data['ticket_type_id']);

        if ($ticketType->is_long_term) {
            $validFrom = Carbon::parse($data['valid_from'])->startOfDay();
            $validUntil = $validFrom->copy()->addMinutes($ticketType->validity_minutes);

            $ticket = UserTicket::create([
                'user_id' => $request->user()->id,
                'ticket_type_id' => $ticketType->id,
                'purchase_date' => now(),
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'is_active' => true,
            ]);
        } else {
            $ticket = UserTicket::create([
                'user_id' => $request->user()->id,
                'ticket_type_id' => $ticketType->id,
                'purchase_date' => now(),
                'valid_from' => null,
                'valid_until' => null,
                'is_active' => false,
            ]);
        }

        $ticket->load('ticketType');

        return response()->json([
            'message' => 'Bilet zakupiony pomyslnie (platnosc zasymulowana).',
            'ticket' => $this->ticketPayload($ticket),
        ], 201);
    }

    public function activate(Request $request, int $ticket): JsonResponse
    {
        if ($ticket < 1) {
            throw ValidationException::withMessages([
                'ticket' => ['Nieprawidlowy identyfikator biletu.'],
            ]);
        }

        $userTicket = UserTicket::query()
            ->with('ticketType')
            ->where('user_id', $request->user()->id)
            ->whereKey($ticket)
            ->firstOrFail();

        if ($userTicket->ticketType->is_long_term) {
            throw ValidationException::withMessages([
                'ticket' => ['Bilety dlugoterminowe sa aktywne od daty zakupu i nie wymagaja aktywacji.'],
            ]);
        }

        if ($userTicket->is_active) {
            throw ValidationException::withMessages([
                'ticket' => ['Ten bilet jest juz aktywny.'],
            ]);
        }

        $validFrom = now();
        $validUntil = $validFrom->copy()->addMinutes($userTicket->ticketType->validity_minutes);

        $userTicket->update([
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'is_active' => true,
        ]);

        $userTicket->refresh()->load('ticketType');

        return response()->json([
            'message' => 'Bilet aktywowany.',
            'ticket' => $this->ticketPayload($userTicket),
        ]);
    }

    private function typePayload(TicketType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'price' => $type->price,
            'validity_minutes' => $type->validity_minutes,
            'is_long_term' => $type->is_long_term,
        ];
    }

    private function ticketPayload(UserTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_type' => $this->typePayload($ticket->ticketType),
            'purchase_date' => $ticket->purchase_date,
            'valid_from' => $ticket->valid_from,
            'valid_until' => $ticket->valid_until,
            'is_active' => $ticket->is_active,
            'status' => $ticket->status(),
            'can_activate' => $ticket->canActivate(),
        ];
    }
}
