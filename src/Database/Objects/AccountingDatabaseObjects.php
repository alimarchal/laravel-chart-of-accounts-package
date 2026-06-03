<?php

namespace Alimarchal\LaravelChartOfAccounts\Database\Objects;

interface AccountingDatabaseObjects
{
    public function sync(): void;

    public function drop(): void;
}
