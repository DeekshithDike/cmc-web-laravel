<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\UserRole;
use App\Models\Package;
use App\Models\PaymentTransaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_truncates_all_data_and_leaves_admin_and_parent_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $extra = User::factory()->customer()->create(['email' => 'extra@example.com']);
        PaymentTransaction::query()->create([
            'user_id' => $extra->id,
            'provider' => PaymentProvider::Manual,
            'amount' => '10.00',
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $this->assertGreaterThan(2, User::query()->count());
        $this->assertSame(1, PaymentTransaction::query()->count());

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, User::query()->count());
        $this->assertSame(0, PaymentTransaction::query()->count());
        $this->assertSame(1, User::query()->where('role', UserRole::Admin)->count());
        $this->assertSame(1, User::query()->where('role', UserRole::Customer)->count());
        $this->assertSame(count(config('citymax.packages')), Package::query()->count());
        $this->assertTrue(Package::query()->where('amount', '100.00')->exists());
        $this->assertTrue(Package::query()->where('amount', '5000.00')->exists());

        $admin = User::query()->where('role', UserRole::Admin)->first();
        $parent = User::query()->where('role', UserRole::Customer)->first();

        $this->assertSame((int) config('citymax.seed.admin_id'), $admin->id);
        $this->assertSame((int) config('citymax.seed.customer_id'), $parent->id);
        $this->assertSame(1, $admin->id);
        $this->assertSame(3558, $parent->id);
        $this->assertSame(config('citymax.seed.admin_email'), $admin->email);
        $this->assertSame(config('citymax.seed.customer_email'), $parent->email);
        $this->assertNotNull($parent->binaryTree);
        $this->assertNull($parent->binaryTree->parent_id);

        $next = User::factory()->customer()->create(['email' => 'next-customer@example.com']);
        $this->assertSame(3559, $next->id);

        $this->assertTrue(Hash::check((string) config('citymax.seed.admin_password'), $admin->password));
        $this->assertTrue(Hash::check((string) config('citymax.seed.customer_password'), $parent->password));
        $this->assertSame('testing-admin-pass', (string) config('citymax.seed.admin_password'));
        $this->assertSame('testing-customer-pass', (string) config('citymax.seed.customer_password'));
    }

    public function test_seed_requires_passwords_from_env(): void
    {
        config([
            'citymax.seed.admin_password' => '',
            'citymax.seed.customer_password' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEED_ADMIN_PASSWORD');

        $this->seed(DatabaseSeeder::class);
    }

    public function test_app_source_does_not_hardcode_seed_passwords(): void
    {
        $files = array_merge(
            File::allFiles(app_path()),
            File::allFiles(config_path()),
            File::allFiles(database_path('seeders')),
        );

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $source = $file->getContents();
            $this->assertStringNotContainsString('Admin@12345', $source, $file->getPathname());
            $this->assertStringNotContainsString('Customer@123', $source, $file->getPathname());
        }
    }
}
