<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Support\Help;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The help centre of the accounting area (React): an index of articles grouped like the menu, and one article per screen.
 */
class HelpController extends Controller
{
    public function index(Request $request, Help $help): Response
    {
        return Inertia::render('accounting/help/index', ['blocks' => $help->index($request->user())]);
    }

    public function show(Request $request, Help $help, string $topic): Response
    {
        $article = $help->topic($topic);

        abort_if($article === null, 404);

        return Inertia::render('accounting/help/show', [
            'article' => ['slug' => $article['slug'], 'title' => $article['title'], 'summary' => $article['summary']],
            'html' => $help->html($topic),
            'blocks' => $help->index($request->user()),
        ]);
    }
}
