<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Alimarchal\LaravelChartOfAccounts\Support\AccountingMenu;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Settings part of the accounting area (React): users, roles and permissions, branches, companies, and how the books are set up. The page
 * shows only the links the user's permissions allow.
 */
class AccountingSettingsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('accounting/dashboard', [
            'section' => 'settings',
            'summary' => [],
            'hub' => array_values(array_filter(AccountingMenu::for($request->user(), '', true), fn (array $section): bool => $section['area'] === 'settings')),
        ]);
    }
}
