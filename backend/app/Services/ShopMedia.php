<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ShopMedia
{
    private const TYPES = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    private static function directory(Shop $shop): string
    {
        return 'shop-media/'.$shop->id;
    }

    public static function store(Shop $shop, UploadedFile $photo): string
    {
        $extension = array_search($photo->getMimeType(), self::TYPES, true);
        abort_if($extension === false, 422, 'Choose a JPEG, PNG or WebP image.');
        $name = Str::uuid().'.'.$extension;
        abort_unless(Storage::disk('local')->putFileAs(self::directory($shop), $photo, $name), 500, 'Unable to store this photo.');

        return $name;
    }

    public static function removeAfterCommit(Shop $shop, string $name): void
    {
        DB::afterCommit(fn () => Storage::disk('local')->delete(self::directory($shop).'/'.$name));
    }

    public static function show(Shop $shop, string $name, bool $admin = false)
    {
        $branding = $shop->settings['branding'] ?? [];
        $allowed = in_array($name, [$branding['logo'] ?? null, $branding['cover'] ?? null], true);
        if (! $allowed && ($admin || $shop->status === 'active')) {
            $allowed = collect(ShopCatalog::barbers($shop, $admin))->contains(
                fn ($barber) => collect($barber['portfolio'] ?? [])->contains('image', $name),
            );
        }
        $disk = Storage::disk('local');
        $path = self::directory($shop).'/'.$name;
        abort_unless($allowed && $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Type' => self::TYPES[pathinfo($name, PATHINFO_EXTENSION)],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $admin ? 'private, no-store' : 'public, max-age=300',
        ]);
    }
}
