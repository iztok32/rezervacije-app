<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Page;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Javni prikaz vsebinske strani; neobjavljeno vidi samo, kdor ureja vsebino (predogled). */
class PageController extends Controller
{
    public function show(Request $request, Page $page): Response
    {
        abort_unless($page->is_published || $request->user()?->hasPermission(Permission::ContentManage), 404);

        return Inertia::render('Page', [
            'page' => [
                'title' => $page->title,
                'html' => $page->html(),
                'is_published' => $page->is_published,
                'edit_url' => $request->user()?->hasPermission(Permission::ContentManage) ? route('admin.pages.edit', $page) : null,
            ],
        ]);
    }
}
