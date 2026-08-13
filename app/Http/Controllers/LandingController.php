<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(): View
    {
        return view('landing', [
            'brand' => config('citymax.name', 'City Max Crypto'),
            'tagline' => config('citymax.tagline', 'CREATE • CONNECT • CONQUER'),
            'packages' => $this->packagesForLanding(),
            'withdrawalMinimum' => (float) config('citymax.withdrawal.minimum', 20),
            'withdrawalFee' => (float) config('citymax.withdrawal.fee', 5),
            'supportEmail' => 'support@citymaxcrypto.com',
        ]);
    }

    /**
     * Always return the full plan grid. Prefer DB rows when present; fill gaps from the catalog.
     */
    private function packagesForLanding(): Collection
    {
        $fromDb = Package::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('amount')
            ->get(['name', 'amount', 'roi_percent'])
            ->keyBy(fn ($package) => (int) round((float) $package->amount));

        return collect(config('citymax.packages', []))->map(function (array $plan) use ($fromDb) {
            $amount = (int) round((float) $plan['amount']);

            if ($fromDb->has($amount)) {
                return $fromDb->get($amount);
            }

            return (object) [
                'name' => $plan['name'],
                'amount' => $plan['amount'],
                'roi_percent' => $plan['roi_percent'],
            ];
        });
    }
}
