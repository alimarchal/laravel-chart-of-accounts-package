<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Support\FeatureManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The switches for the optional modules: one screen (React or Blade) and one API serve the same list.
 */
class FeatureController extends Controller
{
    public function __construct(private readonly FeatureManager $features) {}

    public function index(Request $request): Response|View|JsonResponse
    {
        $rows = $this->features->all();

        if ($request->expectsJson()) {
            return response()->json(['data' => $rows]);
        }

        return config('accounting.ui_driver') === 'blade'
            ? view('accounting::features.index', ['features' => $rows])
            : Inertia::render('accounting/features/index', ['features' => $rows]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['features' => ['required', 'array', 'min:1']]);

        foreach ($data['features'] as $key => $value) {
            if (! $this->features->known((string) $key)) {
                throw ValidationException::withMessages(["features.{$key}" => "'{$key}' is not a feature that can be switched."]);
            }

            if (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
                throw ValidationException::withMessages(["features.{$key}" => 'Use true or false.']);
            }
        }

        foreach ($data['features'] as $key => $value) {
            $this->features->set((string) $key, filter_var($value, FILTER_VALIDATE_BOOLEAN));
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Features saved.', 'data' => $this->features->all()])
            : back()->with('success', 'Features saved.');
    }
}
