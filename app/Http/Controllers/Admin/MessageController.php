<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\User;
use App\Services\Notifications\Audience;
use App\Services\Notifications\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Ročna sporočila strankam: pregled poslanih, novo sporočilo s predogledom prejemnikov. */
class MessageController extends Controller
{
    public function __construct(private Audience $audience, private MessageService $messages) {}

    public function index(): Response
    {
        $paginator = Message::with('sender:id,name')
            ->withCount([
                'recipients as sent_count' => fn ($q) => $q->where('mail_status', MessageRecipient::SENT),
                'recipients as failed_count' => fn ($q) => $q->where('mail_status', MessageRecipient::FAILED),
                'recipients as pending_count' => fn ($q) => $q->where('mail_status', MessageRecipient::PENDING),
                'recipients as read_count' => fn ($q) => $q->whereNotNull('read_at'),
            ])
            ->latest('id')
            ->paginate(30);

        return Inertia::render('Admin/Messages/Index', [
            'messages' => [
                'data' => collect($paginator->items())->map(fn (Message $m) => $this->summary($m)),
                'links' => $paginator->linkCollection(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $allowedTypes = $user->allowedResourceTypeIds();

        return Inertia::render('Admin/Messages/Create', [
            'groups' => CustomerGroup::ordered()->get(['id', 'name']),
            'types' => ResourceType::ordered()->when($allowedTypes, fn ($q, $ids) => $q->whereIn('id', $ids))->get(['id', 'name']),
            'resources' => Resource::active()->when($allowedTypes, fn ($q, $ids) => $q->whereIn('resource_type_id', $ids))
                ->orderBy('name')->get(['id', 'name', 'resource_type_id']),
            'canUseBookings' => $user->hasPermission(Permission::BookingsView),
            // Predizpolnjeno iz povezave, npr. iz koledarja ali zaprtja
            'prefill' => $request->only(['mode', 'from', 'to', 'resource_type_id', 'resource_id', 'subject']),
            'today' => today()->toDateString(),
        ]);
    }

    /** Število in predogled prejemnikov za izbrane filtre. */
    public function preview(Request $request): JsonResponse
    {
        $recipients = $this->audience->resolve($request->validate(Audience::rules()), $request->user());

        return response()->json([
            'count' => $recipients->count(),
            'with_account' => $recipients->whereNotNull('user_id')->count(),
            'sample' => $recipients->take(12)->map(fn ($r) => ['name' => $r['name'], 'email' => $r['email'], 'guest' => $r['user_id'] === null])->values(),
        ]);
    }

    /** Iskanje uporabnikov za izbiro posameznih prejemnikov. */
    public function users(Request $request): JsonResponse
    {
        $term = trim((string) $request->validate(['q' => 'required|string|min:2|max:100'])['q']);
        $like = '%'.mb_strtolower($term).'%';

        return response()->json(User::where('is_active', true)
            ->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(email) LIKE ?', [$like]))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'email']));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(Audience::rules() + [
            'channels' => 'required|array|min:1',
            'channels.*' => [Rule::in([Message::CHANNEL_MAIL, Message::CHANNEL_PORTAL])],
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:10000',
        ]);

        $message = $this->messages->send(
            $request->user(),
            $validated,
            array_unique($validated['channels']),
            $validated['subject'],
            $validated['body'],
        );

        if ($message->recipients_count === 0) {
            return redirect()->route('admin.messages.show', $message)->with('error', __('No recipients match the selected filters.'));
        }

        return redirect()->route('admin.messages.show', $message)
            ->with('success', trans_choice('Message sent to :count recipient.|Message sent to :count recipients.', $message->recipients_count, ['count' => $message->recipients_count]));
    }

    public function show(Message $message): Response
    {
        $message->load('sender:id,name')->loadCount([
            'recipients as sent_count' => fn ($q) => $q->where('mail_status', MessageRecipient::SENT),
            'recipients as failed_count' => fn ($q) => $q->where('mail_status', MessageRecipient::FAILED),
            'recipients as pending_count' => fn ($q) => $q->where('mail_status', MessageRecipient::PENDING),
            'recipients as read_count' => fn ($q) => $q->whereNotNull('read_at'),
        ]);

        $recipients = $message->recipients()->orderBy('name')->paginate(100);

        return Inertia::render('Admin/Messages/Show', [
            'message' => $this->summary($message) + ['body' => $message->body],
            'recipients' => [
                'data' => collect($recipients->items())->map(fn (MessageRecipient $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'email' => $r->email,
                    'guest' => $r->user_id === null,
                    'mail_status' => $r->mail_status,
                    'error' => $r->error,
                    'in_portal' => $r->in_portal,
                    'read_at' => $r->read_at?->format('Y-m-d\TH:i'),
                ]),
                'links' => $recipients->linkCollection(),
            ],
        ]);
    }

    private function summary(Message $m): array
    {
        return [
            'id' => $m->id,
            'subject' => $m->subject,
            'channels' => $m->channels,
            'audience' => $m->audience,
            'sender' => $m->sender?->name,
            'recipients_count' => $m->recipients_count,
            'sent_count' => (int) $m->sent_count,
            'failed_count' => (int) $m->failed_count,
            'pending_count' => (int) $m->pending_count,
            'read_count' => (int) $m->read_count,
            'created_at' => $m->created_at->format('Y-m-d\TH:i'),
            'completed' => $m->completed_at !== null,
        ];
    }
}
