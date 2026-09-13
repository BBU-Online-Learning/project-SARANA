<?php

namespace App\Services\Chat;

final class StickerCatalog
{
    /**
     * @return array<string, array{name: string, pack: string, asset: string}>
     */
    public static function all(): array
    {
        return config('chat.stickers', []);
    }

    /**
     * @return array{name: string, pack: string, asset: string}|null
     */
    public static function find(?string $id): ?array
    {
        if (! $id) {
            return null;
        }

        return self::all()[$id] ?? null;
    }

    /** @return array<int, string> */
    public static function ids(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array<int, array{id: string, name: string, pack: string, url: string}>
     */
    public static function forClient(): array
    {
        return collect(self::all())
            ->map(fn (array $sticker, string $id): array => [
                'id' => $id,
                'name' => $sticker['name'],
                'pack' => $sticker['pack'],
                'url' => asset($sticker['asset']),
            ])->values()->all();
    }
}
