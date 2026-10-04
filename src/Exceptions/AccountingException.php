<?php

namespace Alimarchal\LaravelChartOfAccounts\Exceptions;

use InvalidArgumentException;

class AccountingException extends InvalidArgumentException implements AccountingRuleViolation {}
