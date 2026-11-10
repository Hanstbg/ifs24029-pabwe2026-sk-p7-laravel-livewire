<?php

namespace App\Features\Admin\Livewire;

use App\Features\Admin\Services\AdminUserService;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Stok User')]
class AdminUserStockList extends Component
{
    use WithPagination;

    public User $user;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render(AdminUserService $service)
    {
        return view('livewire.admin.admin-user-stock-list', [
            'stocks' => $service->stocksOf($this->user, $this->search ?: null, $this->statusFilter ?: null),
        ]);
    }
}
