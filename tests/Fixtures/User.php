<?php

namespace Alimarchal\LaravelChartOfAccounts\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
