<?php

namespace App\Http\Controllers;

use App\Models\MessageRecipient;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Nabiralnik v portalu: sporočila, ki jih je osebje poslalo uporabniku. */
class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $paginator = $user->inbox()
            ->with('message:id,subject,body,created_at')
            ->latest('id')
            ->paginate(20);

        $items = collect($paginator->items())->map(fn (MessageRecipient $r) => [
            'id' => $r->id,
            'subject' => $r->message->subject,
            'body' => $r->message->body,
            'created_at' => $r->message->created_at->format('Y-m-d\TH:i'),
            'unread' => $r->read_at === null,
        ]);

        // Ob ogledu so prikazana sporočila prebrana (oznaka "novo" ostane do naslednjega ogleda)
        $user->inbox()->whereKey($items->where('unread', true)->pluck('id'))->update(['read_at' => now()]);

        return Inertia::render('Inbox', [
            'items' => ['data' => $items, 'links' => $paginator->linkCollection()],
            'from' => Setting::businessName(),
        ]);
    }
}
