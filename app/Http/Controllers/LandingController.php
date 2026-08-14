<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(): View
    {
        $brand = (string) config('citymax.name', 'City Max Crypto');
        $canonical = url('/');
        $ogImage = asset('branding/icon-180.png');
        $referralPercent = (float) config('citymax.income.referral_percent');
        $binaryPercent = (float) config('citymax.income.binary_percent');
        $binaryMax = (float) config('citymax.income.binary_max');
        $withdrawalMinimum = (float) config('citymax.withdrawal.minimum');
        $withdrawalFee = (float) config('citymax.withdrawal.fee');
        $supportEmail = (string) config('citymax.support_email', 'support@citymaxcrypto.com');
        $referralLabel = rtrim(rtrim(number_format($referralPercent, 2), '0'), '.');
        $binaryLabel = rtrim(rtrim(number_format($binaryPercent, 2), '0'), '.');

        return view('landing', [
            'brand' => $brand,
            'tagline' => config('citymax.tagline', 'CREATE • CONNECT • CONQUER'),
            'packages' => $this->packagesForLanding(),
            'withdrawalMinimum' => $withdrawalMinimum,
            'withdrawalFee' => $withdrawalFee,
            'referralPercent' => $referralPercent,
            'binaryPercent' => $binaryPercent,
            'binaryMax' => $binaryMax,
            'supportEmail' => $supportEmail,
            'seoTitle' => (string) config('citymax.seo.title'),
            'seoDescription' => (string) config('citymax.seo.description'),
            'seoKeywords' => (string) config('citymax.seo.keywords'),
            'seoLocale' => (string) config('citymax.seo.locale', 'en_MY'),
            'seoCountry' => (string) config('citymax.seo.country', 'Malaysia'),
            'seoCountryCode' => (string) config('citymax.seo.country_code', 'MY'),
            'canonical' => $canonical,
            'ogImage' => $ogImage,
            'schema' => $this->schema(
                $brand,
                $canonical,
                $ogImage,
                $supportEmail,
                $referralLabel,
                $binaryLabel,
                $binaryMax,
                $withdrawalMinimum,
                $withdrawalFee,
            ),
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function schema(
        string $brand,
        string $canonical,
        string $ogImage,
        string $supportEmail,
        string $referralLabel,
        string $binaryLabel,
        float $binaryMax,
        float $withdrawalMinimum,
        float $withdrawalFee,
    ): array {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $canonical.'#organization',
                    'name' => $brand,
                    'alternateName' => ['CityMax', 'CityMax Crypto', 'City Max', 'citymax'],
                    'url' => $canonical,
                    'logo' => $ogImage,
                    'email' => $supportEmail,
                    'areaServed' => [
                        '@type' => 'Country',
                        'name' => 'Malaysia',
                    ],
                    'address' => [
                        '@type' => 'PostalAddress',
                        'addressCountry' => 'MY',
                        'addressLocality' => 'Kuala Lumpur',
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $canonical.'#website',
                    'name' => $brand,
                    'url' => $canonical,
                    'inLanguage' => 'en-MY',
                    'publisher' => [
                        '@id' => $canonical.'#organization',
                    ],
                    'description' => (string) config('citymax.seo.description'),
                ],
                [
                    '@type' => 'FAQPage',
                    '@id' => $canonical.'#faq',
                    'inLanguage' => 'en-MY',
                    'mainEntity' => [
                        [
                            '@type' => 'Question',
                            'name' => 'How does daily ROI work?',
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => 'Activated packages earn 1% daily ROI, credited Tuesday through Saturday (Sunday and Monday are skipped).',
                            ],
                        ],
                        [
                            '@type' => 'Question',
                            'name' => 'What is the referral bonus?',
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => "When someone you invite activates a package, the full package amount is stored for you. Daily income pays {$referralLabel}% of that day's stored referral volume.",
                            ],
                        ],
                        [
                            '@type' => 'Question',
                            'name' => 'How is binary income capped?',
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => "Daily binary is {$binaryLabel}% of matched (weaker-side) volume, then capped at your activated package amount and \$".number_format($binaryMax, 0).', whichever is lower.',
                            ],
                        ],
                        [
                            '@type' => 'Question',
                            'name' => 'What are the withdrawal rules?',
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => 'Minimum withdrawal is $'.number_format($withdrawalMinimum, 0).' with a $'.number_format($withdrawalFee, 0).' fee. Processing is within 24 hours after request.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
