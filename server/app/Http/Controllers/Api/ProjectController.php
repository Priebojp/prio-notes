<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->projects()->active()->with('company')->get()
                ->sortBy(fn (Project $p) => $p->company->name.' '.$p->name)
                ->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name, 'company' => $p->company->name])
                ->values(),
        ]);
    }
}
