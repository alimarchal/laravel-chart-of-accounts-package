<?php

namespace Alimarchal\LaravelChartOfAccounts\Exceptions;

/**
 * Marker for business-rule violations raised by the accounting package.
 *
 * Exceptions implementing this interface are rendered as HTTP 422 (JSON) or
 * as a redirect back with an "error" flash message (web), instead of a 500.
 */
interface AccountingRuleViolation {}
