<?php

if (! function_exists('asset_ver')) {
    function asset_ver(string $path): string
    {
        $full = public_path($path);
        $version = is_file($full) ? (string) filemtime($full) : '1';

        return asset($path).'?v='.$version;
    }
}

if (! function_exists('customer_portal_route')) {
    function customer_portal_route(string $name, mixed ...$parameters): string
    {
        return \App\Support\CustomerPortal::route($name, ...$parameters);
    }
}
