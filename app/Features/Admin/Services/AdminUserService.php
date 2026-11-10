<?php

namespace App\Features\Admin\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminUserService
{
    public function __construct(private StockRepositoryInterface $stocks) {}

    public function users(?string $search = null): LengthAwarePaginator
    {
        return User::query()
            ->withCount('stocks')
            ->when($search, fn ($q, $s) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('username', 'like', "%{$s}%")
            ))
            ->orderBy('name')
            ->paginate(10);
    }

    public function stocksOf(User $user, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->stocks->paginateForUser($user->id, $search, $status);
    }
}
