<?php

namespace Alimarchal\LaravelChartOfAccounts\Exceptions;

use DomainException;

class JournalEntryNotEditableException extends DomainException implements AccountingRuleViolation {}
