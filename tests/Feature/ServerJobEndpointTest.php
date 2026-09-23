<?php

namespace Tests\Feature;

use App\Models\DailyIncomeRun;
use App\Support\ServerJobSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServerJobEndpointTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-server-job-secret-32chars-min';

    private string $incomePath = 'e7428b885ad4b5b8cad66e26d1e1e50039cc5f976a85f8f4';

    private string $paymentPath = '995ea036ca1686e8d33380fad6aa149eb394107857512925';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response(['ok' => true], 202),
        ]);
    }

    public function test_income_and_payment_jobs_run_only_with_a_valid_local_signature(): void
    {
        $this->signed($this->incomePath)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('skipped', false);

        $this->assertSame(1, DailyIncomeRun::query()->count());

        $this->signed($this->incomePath)
            ->assertOk()
            ->assertJsonPath('skipped', true);

        $this->assertSame(1, DailyIncomeRun::query()->count());

        $this->signed($this->paymentPath)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('checked', 0);
    }

    public function test_guesses_old_signatures_and_other_addresses_are_not_found(): void
    {
        $this->post('/'.$this->incomePath)->assertNotFound();
        $this->get('/'.$this->incomePath)->assertNotFound();

        $timestamp = (string) time();
        $mac = ServerJobSignature::sign($this->secret, $timestamp, $this->paymentPath, '');
        $this->call('POST', '/'.$this->incomePath, [], [], [], $this->server($timestamp, $mac))
            ->assertNotFound();

        $stale = (string) (time() - 120);
        $staleMac = ServerJobSignature::sign($this->secret, $stale, $this->incomePath, '');
        $this->call('POST', '/'.$this->incomePath, [], [], [], $this->server($stale, $staleMac))
            ->assertNotFound();

        $this->call('POST', '/'.$this->paymentPath, [], [], [], $this->server($timestamp, $mac, '203.0.113.8'))
            ->assertNotFound();

        $this->post('/not-a-real-job-path')->assertNotFound();
    }

    private function signed(string $path): \Illuminate\Testing\TestResponse
    {
        $timestamp = (string) time();
        $mac = ServerJobSignature::sign($this->secret, $timestamp, $path, '');

        return $this->call('POST', '/'.$path, [], [], [], $this->server($timestamp, $mac));
    }

    /**
     * @return array<string, string>
     */
    private function server(string $timestamp, string $mac, string $ip = '127.0.0.1'): array
    {
        return [
            'REMOTE_ADDR' => $ip,
            'HTTP_X_CM_TIME' => $timestamp,
            'HTTP_X_CM_MAC' => $mac,
            'CONTENT_TYPE' => 'application/json',
        ];
    }
}
