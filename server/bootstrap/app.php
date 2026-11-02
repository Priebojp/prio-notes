<?php

use App\Exceptions\PriorityFullException;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Phone app: a full priority column is a question for the user, not an error.
        $exceptions->render(fn (PriorityFullException $e, Request $request) => $request->is('api/*')
            ? response()->json([
                'message' => $e->priority->label().' je plná. Vyber, ktorá úloha pôjde na '.$e->priority->lower()?->label().'.',
                'task_id' => $e->task->id,
                'priority' => $e->priority->value,
                'index' => $e->index,
                'occupants' => TaskResource::collection($e->occupants->each(fn (Task $t) => $t->loadMissing('project.company'))),
            ], 409)
            : null);
    })->create();
