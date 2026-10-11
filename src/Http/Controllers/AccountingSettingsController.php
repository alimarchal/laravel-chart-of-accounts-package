<?php

namespace Alimarchal\LaravelChartOfAccounts\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Settings part of the accounting area (React): users, roles and permissions, branches, companies, and how the books are set up. The page
 * shows only the links the user's permissions allow.
 */
class AccountingSettingsController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('accounting/dashboard', ['section' => 'settings', 'summary' => []]);
    }
}
