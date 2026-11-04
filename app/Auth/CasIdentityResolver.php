<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Lovevisss\LaravelZufedfcCas\Contracts\CasIdentityResolver as ResolverContract;
use Lovevisss\LaravelZufedfcCas\Data\CasIdentity;

final class CasIdentityResolver implements ResolverContract
{
    public function resolve(CasIdentity $identity): ?Authenticatable
    {
        return User::query()
            ->where('school_account', $identity->account)
            ->where('is_active', true)
            ->whereNotNull('department_id')
            ->first();
    }
}
