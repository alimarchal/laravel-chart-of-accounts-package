<?php

namespace Alimarchal\LaravelChartOfAccounts\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait HasUserTracking
{
    public static function bootHasUserTracking(): void
    {
        static::creating(function (Model $model): void {
            if (Auth::check()) {
                $model->setAttribute('created_by', $model->getAttribute('created_by') ?? Auth::id());
                $model->setAttribute('updated_by', $model->getAttribute('updated_by') ?? Auth::id());
            }
        });

        static::updating(function (Model $model): void {
            if (Auth::check()) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'updated_by');
    }
}
