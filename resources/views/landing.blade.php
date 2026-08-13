<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $brand }} — packages, daily ROI, referral & binary income, and fast USDT withdrawals.">
    <meta name="robots" content="index,follow">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>{{ $brand }} — {{ $tagline }}</title>
    <link rel="icon" type="image/png" href="{{ asset('branding/favicon-32.png') }}">
    <link rel="stylesheet" href="{{ asset('landing/css/landing.css') }}?v={{ @filemtime(public_path('landing/css/landing.css')) ?: '1' }}">
</head>
<body>
<header class="site-header" id="siteHeader">
    <div class="container nav">
        <a class="brand" href="{{ route('landing') }}">
            <img src="{{ asset('branding/icon-180.png') }}" alt="" width="40" height="40">
            <span>{{ $brand }}</span>
        </a>
        <nav class="nav-links" aria-label="Primary">
            <a href="#home">Home</a>
            <a href="#packages">Packages</a>
            <a href="#income">Income</a>
            <a href="#withdrawals">Withdrawals</a>
            <a href="#payments">Payments</a>
            <a href="#why">Why join</a>
        </nav>
        <a class="btn btn-primary" href="{{ route('customer.login') }}">Member Login</a>
        <button class="nav-toggle" id="navToggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="navDrawer">☰</button>
    </div>
    <div class="nav-drawer" id="navDrawer">
        <div class="container">
            <a href="#home">Home</a>
            <a href="#packages">Packages</a>
            <a href="#income">Income</a>
            <a href="#withdrawals">Withdrawals</a>
            <a href="#payments">Payments</a>
            <a href="#why">Why join</a>
            <a class="btn btn-primary" href="{{ route('customer.login') }}">Member Login</a>
        </div>
    </div>
</header>

<main>
    <section class="hero" id="home">
        <div class="container hero-copy">
            <span class="eyebrow">{{ $brand }}</span>
            <h1>Trade crypto with <span>clarity</span> and control</h1>
            <p>A modern trading workspace for spot markets, portfolio tracking, and secure USDT settlements — built for focused decision-making.</p>
            <div class="btn-row hero-actions">
                <a class="btn btn-primary" href="{{ route('customer.login') }}">Open dashboard</a>
                <a class="btn btn-ghost" href="#packages">View packages</a>
            </div>
        </div>
    </section>

    <section class="section" id="packages">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">available packages</span>
                <h2 class="title">Choose a package and <span>start</span></h2>
                <p class="lede">Affordable USD packages designed for every stage of your journey.</p>
            </div>
            <div class="package-grid">
                @foreach($packages as $package)
                    @php $amount = (float) ($package->amount ?? 0); @endphp
                    <article class="package-card">
                        <p class="package-amount">${{ number_format($amount, 0) }}</p>
                        <p class="package-label">{{ $package->name ?? 'Package' }}</p>
                        <ul>
                            <li>1% daily ROI (Mon–Fri)</li>
                            <li>Binary cap ${{ number_format($amount, 0) }}/day</li>
                            <li>USDT activation</li>
                        </ul>
                        <a class="btn btn-ghost" href="{{ route('customer.login') }}">Get started</a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section" id="income">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">income plan</span>
                <h2 class="title">Three ways to <span>earn</span></h2>
                <p class="lede">Grow with daily ROI, instant referral rewards, and binary matching.</p>
            </div>
            <div class="card-grid">
                <article class="card income-card">
                    <img src="{{ asset('landing/img/icon/crypto_icon01.svg') }}" alt="" width="64" height="64" loading="lazy" decoding="async">
                    <h3>Daily <span>ROI</span></h3>
                    <p class="income-stat">1% daily</p>
                    <p>Credited Monday through Friday on your activated package.</p>
                </article>
                <article class="card income-card">
                    <img src="{{ asset('landing/img/icon/crypto_icon02.svg') }}" alt="" width="64" height="64" loading="lazy" decoding="async">
                    <h3>Direct <span>referral</span></h3>
                    <p class="income-stat">10% instant</p>
                    <p>Earn a 10% referral bonus when someone you invite activates a package.</p>
                </article>
                <article class="card income-card">
                    <img src="{{ asset('landing/img/icon/crypto_icon03.svg') }}" alt="" width="64" height="64" loading="lazy" decoding="async">
                    <h3>Binary <span>income</span></h3>
                    <p class="income-stat">10% commission</p>
                    <p>Build left and right teams and earn matching binary volume.</p>
                </article>
            </div>
            <div class="market-panel mt-panel">
                <h3>Daily binary capping</h3>
                <p class="lede mt-sm">Your daily binary cap equals your activated package amount.</p>
                <ul class="market-list">
                    <li><span>$500 package</span><strong>Up to $500 / day</strong></li>
                    <li><span>$2,000 package</span><strong>Up to $2,000 / day</strong></li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section" id="withdrawals">
        <div class="container markets">
            <div>
                <span class="eyebrow">withdrawals</span>
                <h2 class="title">Fast payout <span>rules</span></h2>
                <p class="lede">Request withdrawals from your member dashboard and receive funds to your crypto wallet.</p>
                <div class="btn-row mt-md">
                    <a class="btn btn-primary" href="{{ route('customer.login') }}">Open dashboard</a>
                </div>
            </div>
            <div class="market-panel">
                <h3>Withdrawal details</h3>
                <ul class="market-list">
                    <li><span>Minimum</span><strong>${{ number_format($withdrawalMinimum, 0) }}</strong></li>
                    <li><span>Fee</span><strong>${{ number_format($withdrawalFee, 0) }}</strong></li>
                    <li><span>Processing</span><strong>Within 24 hours</strong></li>
                </ul>
                <p class="market-note">Timing starts after your withdrawal request is submitted.</p>
            </div>
        </div>
    </section>

    <section class="section" id="payments">
        <div class="container">
            <div class="exchange">
                <div class="exchange-copy">
                    <img src="{{ asset('landing/img/images/exchange_img.png') }}" alt="" width="56" height="56" loading="lazy" decoding="async">
                    <div>
                        <h3>Payment <span>method</span></h3>
                        <p>Deposits and withdrawals are processed in USDT on major networks.</p>
                    </div>
                </div>
                <div class="pay-chips">
                    <span class="pay-chip">USDT (ERC20)</span>
                    <span class="pay-chip">USDT (BEP20 / BSC)</span>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="why">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">why join</span>
                <h2 class="title">Why members choose <span>{{ $brand }}</span></h2>
            </div>
            <div class="feature-grid">
                <article class="feature">
                    <img src="{{ asset('landing/img/icon/features_icon01.png') }}" alt="" width="52" height="52" loading="lazy" decoding="async">
                    <div>
                        <h3>Affordable <span>packages</span></h3>
                        <p>Start from $100 and scale up to $5,000 as you grow.</p>
                    </div>
                </article>
                <article class="feature">
                    <img src="{{ asset('landing/img/icon/features_icon02.png') }}" alt="" width="52" height="52" loading="lazy" decoding="async">
                    <div>
                        <h3>Multiple <span>income streams</span></h3>
                        <p>Earn through ROI, referral, and binary income in one plan.</p>
                    </div>
                </article>
                <article class="feature compact">
                    <img src="{{ asset('landing/img/icon/features_icon03.png') }}" alt="" width="52" height="52" loading="lazy" decoding="async">
                    <div>
                        <h3>Fast <span>withdrawals</span></h3>
                        <p>Requests are processed within 24 hours after submission.</p>
                    </div>
                </article>
                <article class="feature compact">
                    <img src="{{ asset('landing/img/icon/features_icon04.png') }}" alt="" width="52" height="52" loading="lazy" decoding="async">
                    <div>
                        <h3>Secure <span>crypto rails</span></h3>
                        <p>USDT settlements on ERC20 and BEP20 for transparent transfers.</p>
                    </div>
                </article>
                <article class="feature compact">
                    <img src="{{ asset('landing/img/icon/features_icon05.png') }}" alt="" width="52" height="52" loading="lazy" decoding="async">
                    <div>
                        <h3>Team <span>growth</span></h3>
                        <p>Build your network and grow long-term passive income potential.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="section" id="work">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">how it works</span>
                <h2 class="title">From activation to <span>earnings</span></h2>
            </div>
            <div class="steps-wrap">
                <div class="steps-visual">
                    <img src="{{ asset('landing/img/images/work_img.png') }}" alt="" width="280" height="280" loading="lazy" decoding="async">
                </div>
                <div class="steps">
                    <article class="step">
                        <div class="num">01</div>
                        <h3>Choose a <span>package</span></h3>
                        <p>Select any package from $100 to $5,000 that fits your goal.</p>
                    </article>
                    <article class="step">
                        <div class="num">02</div>
                        <h3>Activate with <span>USDT</span></h3>
                        <p>Complete payment on ERC20 or BEP20 and activate your account.</p>
                    </article>
                    <article class="step">
                        <div class="num">03</div>
                        <h3>Earn daily <span>ROI</span></h3>
                        <p>Receive 1% daily ROI Monday to Friday on your package.</p>
                    </article>
                    <article class="step">
                        <div class="num">04</div>
                        <h3>Withdraw <span>earnings</span></h3>
                        <p>Request payouts anytime above the minimum — processed within 24 hours.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="faq">
        <div class="container faq-grid">
            <img src="{{ asset('landing/img/images/faq_img.png') }}" alt="" width="480" height="420" loading="lazy" decoding="async">
            <div class="faq">
                <span class="eyebrow">faq</span>
                <h2 class="title">Plan details at a <span>glance</span></h2>
                <div class="faq-list">
                    <details open>
                        <summary>How does daily ROI work?</summary>
                        <p>Activated packages earn 1% daily ROI, credited Monday through Friday.</p>
                    </details>
                    <details>
                        <summary>What is the referral bonus?</summary>
                        <p>You receive a 10% instant referral bonus when a person you invite activates a package.</p>
                    </details>
                    <details>
                        <summary>How is binary income capped?</summary>
                        <p>Daily binary income is capped at an amount equal to your activated package (example: $500 package = $500 daily binary cap).</p>
                    </details>
                    <details>
                        <summary>What are the withdrawal rules?</summary>
                        <p>Minimum withdrawal is ${{ number_format($withdrawalMinimum, 0) }} with a ${{ number_format($withdrawalFee, 0) }} fee. Processing is within 24 hours after request.</p>
                    </details>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <img class="brand-mark" src="{{ asset('branding/icon-180.png') }}" alt="" width="56" height="56" loading="lazy" decoding="async">
            <div class="brand cta-brand">
                <span>{{ $brand }}</span>
            </div>
            <span class="eyebrow">{{ $tagline }}</span>
            <h2 class="title">Start with any package and grow with <span>{{ $brand }}</span></h2>
            <p class="lede cta-copy">Join now, activate your package, and begin building your crypto income today.</p>
            <div class="btn-row">
                <a class="btn btn-primary" href="{{ route('customer.login') }}">Member Login</a>
                <a class="btn btn-ghost" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container">&copy; {{ date('Y') }} {{ $brand }}. All rights reserved.</div>
</footer>

<button class="scroll-top" id="scrollTop" type="button" aria-label="Scroll to top">↑</button>
<script src="{{ asset('landing/js/landing.js') }}?v={{ @filemtime(public_path('landing/js/landing.js')) ?: '1' }}" defer></script>
</body>
</html>
