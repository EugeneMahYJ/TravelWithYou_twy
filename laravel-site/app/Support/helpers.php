<?php

use Carbon\Carbon;

if (! function_exists('fmt_rm')) {
    function fmt_rm(int|float|string $amount): string
    {
        return 'RM '.number_format((int) round((float) $amount), 0, '.', ',');
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date(DateTimeInterface|string $date): string
    {
        return Carbon::parse($date)->format('j M Y');
    }
}

if (! function_exists('fmt_month')) {
    function fmt_month(DateTimeInterface|string $date): string
    {
        return Carbon::parse($date)->format('M Y');
    }
}

if (! function_exists('asset_img')) {
    function asset_img(string $id): string
    {
        $id = trim($id);

        if ($id === '') {
            return asset_img_placeholder();
        }

        if (preg_match('/\Ahttps?:\/\//i', $id) === 1 || str_starts_with($id, 'data:') || str_starts_with($id, '//')) {
            return $id;
        }

        $path = ltrim($id, '/');

        if (str_starts_with($path, 'assets/')) {
            $path = substr($path, 7);
        }

        if (! str_contains($path, '..') && is_file(public_path('assets/'.$path))) {
            return asset('assets/'.$path);
        }

        if (preg_match('/\A[a-zA-Z0-9-]+\z/', $id) === 1 && is_file(public_path('assets/'.$id.'.jpg'))) {
            return asset('assets/'.$id.'.jpg');
        }

        return asset_img_placeholder();
    }
}

if (! function_exists('asset_img_placeholder')) {
    function asset_img_placeholder(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 640"><rect width="900" height="640" fill="#1f4e5f"/></svg>';

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }
}
