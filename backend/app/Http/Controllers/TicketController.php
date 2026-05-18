<?php

namespace App\Http\Controllers;

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

    public function purchase(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket_type_id' => ['required', 'integer', 'exists:ticket_types,id'],
            'valid_from' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $ticketType = TicketType::query()->findOrFail($data['ticket_type_id']);

        if ($ticketType->is_long_term) {
            if (empty($data['valid_from'])) {
                throw ValidationException::withMessages([
                    'valid_from' => ['Dla biletow dlugoterminowych wymagana jest data rozpoczecia waznosci.'],
                ]);
            }

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
            if (! empty($data['valid_from'])) {
                throw ValidationException::withMessages([
                    'valid_from' => ['Bilet 60-minutowy nie wymaga daty rozpoczecia — aktywuj go po zakupie.'],
                ]);
            }

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

    public function activate(Request $request, int $id): JsonResponse
    {
        $ticket = UserTicket::query()
            ->with('ticketType')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($ticket->ticketType->is_long_term) {
            throw ValidationException::withMessages([
                'ticket' => ['Bilety dlugoterminowe sa aktywne od daty zakupu i nie wymagaja aktywacji.'],
            ]);
        }

        if ($ticket->is_active) {
            throw ValidationException::withMessages([
                'ticket' => ['Ten bilet jest juz aktywny.'],
            ]);
        }

        $validFrom = now();
        $validUntil = $validFrom->copy()->addMinutes($ticket->ticketType->validity_minutes);

        $ticket->update([
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'is_active' => true,
        ]);

        $ticket->refresh()->load('ticketType');

        return response()->json([
            'message' => 'Bilet aktywowany.',
            'ticket' => $this->ticketPayload($ticket),
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
