<?php

namespace App\Http\Controllers;

use App\Models\ResourceType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /** Javna stran s ponudbo (aktivni tipi virov). */
    public function index(Request $request): Response
    {
        $types = ResourceType::active()->ordered()->openTo($request->user())
            ->withCount(['resources' => fn ($q) => $q->active()])
            ->get()
            ->map(fn (ResourceType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'description' => $t->description,
                'icon' => $t->icon,
                'color' => $t->color,
                'booking_mode' => $t->booking_mode->value,
                'allow_guests' => $t->allow_guests,
                'resource_label' => $t->resourceLabel(),
                'resources_count' => $t->resources_count,
            ]);

        return Inertia::render('Welcome', ['types' => $types]);
    }
}
