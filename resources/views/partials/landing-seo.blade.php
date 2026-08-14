<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<meta name="keywords" content="{{ $seoKeywords }}">
<meta name="author" content="{{ $brand }}">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
<meta name="googlebot" content="index,follow">
<meta name="referrer" content="strict-origin-when-cross-origin">
<meta name="theme-color" content="#06101c">
<meta name="application-name" content="{{ $brand }}">
<meta name="apple-mobile-web-app-title" content="{{ $brand }}">
<meta name="geo.region" content="{{ $seoCountryCode }}">
<meta name="geo.placename" content="{{ $seoCountry }}">
<meta name="geo.country" content="{{ $seoCountryCode }}">
<meta name="ICBM" content="3.1390, 101.6869">
<link rel="canonical" href="{{ $canonical }}">
<link rel="alternate" hreflang="en-MY" href="{{ $canonical }}">
<link rel="alternate" hreflang="x-default" href="{{ $canonical }}">
<link rel="icon" type="image/png" href="{{ asset('branding/favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('branding/icon-180.png') }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $brand }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:alt" content="{{ $brand }} logo">
<meta property="og:locale" content="{{ $seoLocale }}">
<meta property="og:locale:alternate" content="en_US">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $ogImage }}">
<script type="application/ld+json">@json($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
