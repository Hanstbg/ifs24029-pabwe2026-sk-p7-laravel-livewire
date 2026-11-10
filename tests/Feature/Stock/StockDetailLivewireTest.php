<?php

namespace Tests\Feature\Stock;

use App\Features\Stock\Livewire\StockDetail;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StockDetailLivewireTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Stock $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->stock = Stock::factory()->create([
            'user_id' => $this->user->id, 'name' => 'Item', 'quantity' => 10, 'min_stock' => 5,
            'description' => '**tebal**', 'sku' => null, 'price' => null,
        ]);
        $this->actingAs($this->user);
    }

    public function test_renders_detail_with_markdown(): void
    {
        Livewire::test(StockDetail::class, ['stock' => $this->stock])
            ->assertSee('Item')->assertSeeHtml('<strong>tebal</strong>');
    }

    public function test_other_user_cannot_view(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(StockDetail::class, ['stock' => $this->stock])->assertForbidden();
    }

    public function test_save(): void
    {
        Livewire::test(StockDetail::class, ['stock' => $this->stock])
            ->set('name', 'Diubah')->set('quantity', 2)->set('price', '')->set('sku', '')
            ->call('save')->assertSet('message', 'Stok berhasil disimpan.');

        $this->assertDatabaseHas('stocks', ['id' => $this->stock->id, 'name' => 'Diubah', 'status' => 'low_stock']);
    }

    public function test_save_validation(): void
    {
        Livewire::test(StockDetail::class, ['stock' => $this->stock])->set('name', '')->call('save')->assertHasErrors('name');
    }

    public function test_adjust(): void
    {
        Livewire::test(StockDetail::class, ['stock' => $this->stock])->call('adjust', 1)->assertSet('quantity', 11);
    }

    public function test_upload_cover_and_replace(): void
    {
        Storage::fake('public');

        $component = Livewire::test(StockDetail::class, ['stock' => $this->stock])
            ->set('cover', UploadedFile::fake()->image('a.jpg'))->call('uploadCover');
        $first = $this->stock->fresh()->cover_image_path;
        Storage::disk('public')->assertExists($first);

        $component->set('cover', UploadedFile::fake()->image('b.jpg'))->call('uploadCover');
        Storage::disk('public')->assertMissing($first);
    }

    public function test_cover_validation(): void
    {
        Livewire::test(StockDetail::class, ['stock' => $this->stock])->call('uploadCover')->assertHasErrors('cover');
    }

    public function test_delete_removes_cover(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('c.jpg')->store('covers', 'public');
        $this->stock->update(['cover_image_path' => $path]);

        Livewire::test(StockDetail::class, ['stock' => $this->stock->fresh()])->call('delete')->assertRedirect('/stocks');

        $this->assertDatabaseMissing('stocks', ['id' => $this->stock->id]);
        Storage::disk('public')->assertMissing($path);
    }
}
