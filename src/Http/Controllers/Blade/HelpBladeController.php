<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers\Blade;

use Alimarchal\LaravelChartOfAccounts\Support\Help;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * The help centre of the accounting area (Blade): an index of articles grouped like the menu, and one article per screen.
 */
class HelpBladeController extends Controller
{
    public function index(Request $request, Help $help): View
    {
        return view('accounting::help.index', ['blocks' => $help->index($request->user())]);
    }

    public function show(Request $request, Help $help, string $topic): View
    {
        $article = $help->topic($topic);

        abort_if($article === null, 404);

        return view('accounting::help.show', [
            'article' => $article,
            'html' => $help->html($topic),
            'blocks' => $help->index($request->user()),
        ]);
    }
}
