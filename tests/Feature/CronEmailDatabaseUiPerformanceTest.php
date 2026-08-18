<?php

namespace Tests\Feature;

use App\Mail\MemberCredentialsMail;
use App\Models\BinaryIncome;
use App\Models\CarryForward;
use App\Models\DailyIncomeRun;
use App\Models\PaymentDetail;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Income\DailyIncomeService;
use App\Services\Wallet\WalletService;
use DateTimeZone;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class CronEmailDatabaseUiPerformanceTest extends TestCase
{
    use CreatesCityMaxPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-14 12:00:00');
        $this->createCityMaxPlatform();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_daily_income_is_scheduled_at_malaysia_midnight(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('income:daily')
            ->assertSuccessful();

        $event = collect(app(Schedule::class)->events())->first(function ($scheduled) {
            return str_contains((string) ($scheduled->command ?? ''), 'income:daily')
                || str_contains((string) ($scheduled->description ?? ''), 'income:daily');
        });

        $this->assertNotNull($event, 'income:daily is not registered on the scheduler');
        $this->assertSame('0 0 * * *', $event->expression);

        $timezone = $event->timezone;
        $name = $timezone instanceof DateTimeZone ? $timezone->getName() : (string) $timezone;
        $this->assertSame('Asia/Kuala_Lumpur', $name);
    }

    public function test_income_daily_command_runs_and_is_idempotent(): void
    {
        $this->artisan('income:daily')
            ->expectsOutputToContain('Daily income')
            ->assertSuccessful();

        $asOf = app(DailyIncomeService::class)->previousDate();
        $this->assertSame(1, DailyIncomeRun::query()->whereDate('as_of', $asOf)->count());

        $this->artisan('income:daily')
            ->expectsOutputToContain('already calculated')
            ->assertSuccessful();
    }

    public function test_member_credentials_mail_content_and_delivery(): void
    {
        Mail::fake();

        $member = $this->addMember('mail-member@citymaxcrypto.com', 'left', extra: [
            'name' => 'Mail Member',
        ]);

        Mail::assertSent(MemberCredentialsMail::class, function (MemberCredentialsMail $mail) use ($member) {
            return $mail->hasTo('mail-member@citymaxcrypto.com')
                && $mail->loginId === (int) $member->id
                && $mail->password !== '';
        });

        $mailable = new MemberCredentialsMail(3558, 'SecretPass1!');
        $mailable->assertHasSubject('Your City Max Crypto login details');
        $mailable->assertSeeInHtml('3558');
        $mailable->assertSeeInHtml('SecretPass1!');
        $mailable->assertSeeInHtml(route('customer.login'));
    }

    public function test_unpaid_invite_does_not_send_credentials_email(): void
    {
        Mail::fake();

        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'right',
        ]))->assertOk();

        $this->assertRedirectedToPaymentCheckout(
            $this->post(route('customer.register.save'), [
                'name' => 'Unpaid Invite',
                'email' => 'unpaid-mail@citymaxcrypto.com',
                'package_id' => $this->package->id,
            ])
        );

        Mail::assertNothingSent();
        $this->assertNull(User::query()->where('email', 'unpaid-mail@citymaxcrypto.com')->first());
    }

    public function test_database_rejects_duplicate_income_rows(): void
    {
        $asOf = now()->toDateString();

        DailyIncomeRun::query()->create([
            'as_of' => $asOf,
            'status' => DailyIncomeRun::STATUS_COMPLETED,
            'triggered_by' => 'test',
            'processed' => 1,
            'total_paid' => '1.00',
        ]);
        try {
            DailyIncomeRun::query()->create([
                'as_of' => $asOf,
                'status' => DailyIncomeRun::STATUS_COMPLETED,
                'triggered_by' => 'test',
                'processed' => 1,
                'total_paid' => '1.00',
            ]);
            $this->fail('Duplicate daily income run was allowed');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        PaymentDetail::query()->create([
            'user_id' => $this->root->id,
            'roi_amount' => '1.00',
            'binary_amount' => '0.00',
            'referral_amount' => '0.00',
            'total_amount' => '1.00',
            'paid_on' => $asOf,
        ]);
        try {
            PaymentDetail::query()->create([
                'user_id' => $this->root->id,
                'roi_amount' => '1.00',
                'binary_amount' => '0.00',
                'referral_amount' => '0.00',
                'total_amount' => '1.00',
                'paid_on' => $asOf,
            ]);
            $this->fail('Duplicate payment detail was allowed');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        BinaryIncome::query()->create([
            'user_id' => $this->root->id,
            'amount' => '1.00',
            'left_volume' => '20.00',
            'right_volume' => '20.00',
            'earned_on' => $asOf,
        ]);
        try {
            BinaryIncome::query()->create([
                'user_id' => $this->root->id,
                'amount' => '1.00',
                'left_volume' => '20.00',
                'right_volume' => '20.00',
                'earned_on' => $asOf,
            ]);
            $this->fail('Duplicate binary income was allowed');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        CarryForward::query()->create([
            'user_id' => $this->root->id,
            'as_of' => $asOf,
            'left_carry' => '0.00',
            'right_carry' => '0.00',
        ]);
        try {
            CarryForward::query()->create([
                'user_id' => $this->root->id,
                'as_of' => $asOf,
                'left_carry' => '0.00',
                'right_carry' => '0.00',
            ]);
            $this->fail('Duplicate carry forward was allowed');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    public function test_wallet_ledger_matches_balance(): void
    {
        $wallet = app(WalletService::class);
        $initial = (float) $this->root->wallet_balance;

        $wallet->credit($this->root, '15.50', 'test_credit');
        $wallet->debit($this->root, '5.25', 'test_debit');

        $user = $this->root->fresh();
        $credits = (float) WalletTransaction::query()->where('user_id', $user->id)->where('type', 'credit')->sum('amount');
        $debits = (float) WalletTransaction::query()->where('user_id', $user->id)->where('type', 'debit')->sum('amount');

        $this->assertEqualsWithDelta($initial + 15.50 - 5.25, (float) $user->wallet_balance, 0.01);
        $this->assertEqualsWithDelta($initial + $credits - $debits, (float) $user->wallet_balance, 0.01);
        $this->assertSame('210.25', $wallet->balance($user));
    }

    public function test_occupied_tree_slot_and_duplicate_email_are_rejected(): void
    {
        $this->addMember('slot-left@citymaxcrypto.com', 'left');

        $this->actingAs($this->admin)->from(route('admin.users.create'))->post(route('admin.users.store'), [
            'name' => 'Taken Slot',
            'email' => 'slot-left-2@citymaxcrypto.com',
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'left',
            'package_id' => $this->package->id,
        ])->assertRedirect()->assertSessionHas('error');

        $this->actingAs($this->admin)->from(route('admin.users.create'))->post(route('admin.users.store'), [
            'name' => 'Dup Email',
            'email' => $this->root->email,
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'right',
            'package_id' => $this->package->id,
        ])->assertSessionHasErrors('email');
    }

    public function test_withdrawal_minimum_and_insufficient_funds(): void
    {
        $this->actingAs($this->root)->from(route('customer.withdrawals.create'))->post(route('customer.withdrawals.store'), [
            'amount' => 5,
            'wallet_address' => self::USDT_EVM_ADDRESS,
        ])->assertRedirect()->assertSessionHas('error');

        $this->root->update(['wallet_balance' => '15.00']);
        $this->actingAs($this->root)->from(route('customer.withdrawals.create'))->post(route('customer.withdrawals.store'), [
            'amount' => 20,
            'wallet_address' => self::USDT_EVM_ADDRESS,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame('15.00', number_format((float) $this->root->fresh()->wallet_balance, 2, '.', ''));
    }

    public function test_customer_and_admin_ui_surfaces(): void
    {
        $this->actingAs($this->root)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Available balance', false)
            ->assertSee('Customer ID '.$this->root->id, false)
            ->assertSee('ROI wallet income', false)
            ->assertSee('My Tree', false)
            ->assertSee('Withdrawal Now', false)
            ->assertSee('Income History', false)
            ->assertSee('Malaysia', false);

        $this->actingAs($this->root)->get(route('customer.tree'))
            ->assertOk()
            ->assertSee('Your network', false)
            ->assertSee('Add user', false);

        $this->actingAs($this->root)->get(route('customer.withdrawals.create'))
            ->assertOk()
            ->assertSee('Minimum $10.00', false)
            ->assertSee('Fee $2.00', false)
            ->assertSee('TRC-20', false)
            ->assertSee('BEP-20', false);

        $this->actingAs($this->root)->get(route('customer.income.history'))
            ->assertOk()
            ->assertSee('No income yet.', false);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Active Users', false)
            ->assertSee('Withdrawal Requests', false)
            ->assertSee('Daily Paid Income', false)
            ->assertSee('Power ID', false);

        $this->actingAs($this->admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee((string) config('citymax.seed.customer_id'), false);

        $this->get(route('customer.payment.success', ['ref' => 'NO-SUCH']))
            ->assertOk()
            ->assertSee('Registration successful', false);
        $this->get(route('customer.payment.cancel'))
            ->assertOk()
            ->assertSee('Payment cancelled', false);
    }

    public function test_dashboard_and_admin_lists_stay_within_performance_budget(): void
    {
        User::factory()->customer()->count(40)->create([
            'package_id' => $this->package->id,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $started = microtime(true);

        $this->actingAs($this->root)->get(route('customer.dashboard'))->assertOk();
        $customerElapsed = microtime(true) - $started;
        $customerQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $started = microtime(true);
        $admin = $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
        $adminElapsed = microtime(true) - $started;
        $adminQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(2.0, $customerElapsed, 'Customer dashboard took too long');
        $this->assertLessThan(3.0, $adminElapsed, 'Admin users list took too long');
        $this->assertLessThan(25, $customerQueries, 'Customer dashboard ran too many queries');
        $this->assertLessThan(30, $adminQueries, 'Admin users list ran too many queries');
        $admin->assertSee('Showing', false);
    }

    public function test_health_check_and_seo_endpoints(): void
    {
        $this->get('/up')->assertOk();
        $this->get(route('seo.robots'))->assertOk()->assertSee('Sitemap:', false);
        $this->get(route('seo.sitemap'))->assertOk()->assertSee(url('/'), false);
    }

    public function test_admin_password_change_then_relogin(): void
    {
        $this->actingAs($this->admin)->get(route('admin.password.edit'))
            ->assertOk()
            ->assertSee('name="current_password"', false);

        $this->actingAs($this->admin)->put(route('admin.password.update'), [
            'current_password' => 'Admin@12345',
            'password' => 'Admin@99999',
            'password_confirmation' => 'Admin@99999',
        ])->assertRedirect()->assertSessionHas('success');

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.login.submit'), [
            'email' => $this->admin->email,
            'password' => 'Admin@99999',
        ])->assertRedirect(route('admin.dashboard'));
    }
}
