<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\ResourceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Vsebina: vsebinske strani in javni meni (glava, noga). */
class ContentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Content/Index', [
            'pages' => Page::orderBy('title')->get()->map(fn (Page $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'url' => $p->url(),
                'is_published' => $p->is_published,
                'updated_at' => $p->updated_at?->format('Y-m-d\TH:i'),
            ]),
            'menu' => collect(MenuItem::LOCATIONS)->mapWithKeys(fn ($location) => [
                $location => MenuItem::with(['page:id,title', 'resourceType:id,name'])
                    ->where('location', $location)
                    ->orderBy('display_order')->orderBy('id')
                    ->get()
                    ->map(fn (MenuItem $m) => [
                        'id' => $m->id,
                        'kind' => $m->kind,
                        'label' => $m->label,
                        'target' => match ($m->kind) {
                            'page' => $m->page?->title,
                            'offer' => $m->resourceType?->name,
                            default => $m->url,
                        },
                        'page_id' => $m->page_id,
                        'resource_type_id' => $m->resource_type_id,
                        'url' => $m->url,
                        'visibility' => $m->visibility,
                        'new_tab' => $m->new_tab,
                    ]),
            ]),
            'types' => ResourceType::ordered()->get(['id', 'name']),
        ]);
    }

    // ── Strani ──

    public function create(): Response
    {
        return Inertia::render('Admin/Content/PageForm', ['page' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->pageRules());
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?: $validated['title']);

        $page = Page::create($validated);

        return redirect()->route('admin.pages.edit', $page)->with('success', __('Saved.'));
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('Admin/Content/PageForm', [
            'page' => $page->only(['id', 'title', 'slug', 'body', 'is_published']) + ['url' => $page->url()],
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate($this->pageRules());
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?: $validated['title'], $page);

        $page->update($validated);

        return back()->with('success', __('Saved.'));
    }

    public function destroy(Page $page): RedirectResponse
    {
        // Postavke menija, ki kažejo na stran, se izbrišejo skupaj z njo (cascade)
        $page->delete();

        return redirect()->route('admin.content.index')->with('success', __('Deleted.'));
    }

    // ── Meni ──

    public function storeMenuItem(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->menuRules());

        MenuItem::create($this->menuAttributes($validated) + [
            'display_order' => (int) MenuItem::where('location', $validated['location'])->max('display_order') + 1,
        ]);

        return back()->with('success', __('Saved.'));
    }

    public function updateMenuItem(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $menuItem->update($this->menuAttributes($request->validate($this->menuRules())));

        return back()->with('success', __('Saved.'));
    }

    public function destroyMenuItem(MenuItem $menuItem): RedirectResponse
    {
        $menuItem->delete();

        return back()->with('success', __('Deleted.'));
    }

    public function reorderMenu(Request $request): RedirectResponse
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        foreach ($validated['ids'] as $index => $id) {
            MenuItem::whereKey($id)->update(['display_order' => $index]);
        }

        return back();
    }

    private function pageRules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'body' => 'nullable|string|max:100000',
            'is_published' => 'boolean',
        ];
    }

    private function menuRules(): array
    {
        return [
            'location' => ['required', Rule::in(MenuItem::LOCATIONS)],
            'kind' => ['required', Rule::in(MenuItem::KINDS)],
            'label' => 'nullable|string|max:100|required_if:kind,link',
            'page_id' => 'required_if:kind,page|nullable|integer|exists:pages,id',
            'resource_type_id' => 'required_if:kind,offer|nullable|integer|exists:resource_types,id',
            // Samo varne povezave: notranje (/…), http(s), e-pošta, telefon
            'url' => ['required_if:kind,link', 'nullable', 'string', 'max:500', 'regex:/^(\/(?!\/)|https?:\/\/|mailto:|tel:)/i'],
            'visibility' => ['required', Rule::in(MenuItem::VISIBILITY)],
            'new_tab' => 'boolean',
        ];
    }

    private function menuAttributes(array $validated): array
    {
        $kind = $validated['kind'];

        return [
            'location' => $validated['location'],
            'kind' => $kind,
            'label' => $validated['label'] ?? null,
            'page_id' => $kind === 'page' ? $validated['page_id'] : null,
            'resource_type_id' => $kind === 'offer' ? $validated['resource_type_id'] : null,
            'url' => $kind === 'link' ? $validated['url'] : null,
            'visibility' => $validated['visibility'],
            'new_tab' => (bool) ($validated['new_tab'] ?? false),
        ];
    }

    private function uniqueSlug(string $source, ?Page $ignore = null): string
    {
        $base = Str::slug($source) ?: 'stran';
        $slug = $base;
        $i = 2;

        while (Page::where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
