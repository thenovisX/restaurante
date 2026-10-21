<?php
declare(strict_types=1);

/*
 * Fotos de la web. Son de Unsplash (licencia gratuita: se pueden usar en webs comerciales sin pagar).
 * Para poner fotos propias del restaurante, súbelas a assets/img/ y cambia aquí la dirección,
 * por ejemplo 'src' => 'assets/img/sala.jpg'.
 */
const PHOTOS = [
    'hero' => [
        'src' => 'https://images.unsplash.com/photo-1762806883627-4bcbfad98a2c',
        'alt' => 'Mesa con una lámpara encendida en la sala, de noche',
        'by' => 'Roman', 'url' => 'https://unsplash.com/photos/mLPNw6L5t5o',
    ],
    'sala' => [
        'src' => 'https://images.unsplash.com/photo-1758405155772-ef0f0077375c',
        'alt' => 'La sala en penumbra, con la luz de la calle entrando por la ventana',
        'by' => 'Aconitum', 'url' => 'https://unsplash.com/photos/ORAV7_PAXAE',
    ],
    'cocina' => [
        'src' => 'https://images.unsplash.com/photo-1687369595840-e96a912586f1',
        'alt' => 'Cocinero emplatando con guantes negros',
        'by' => 'Gastro Editorial', 'url' => 'https://unsplash.com/photos/Rp0KQRcQfDc',
    ],
    'barra' => [
        'src' => 'https://images.unsplash.com/photo-1740247374238-af6f14719373',
        'alt' => 'La barra con luces de neón por la noche',
        'by' => 'wei', 'url' => 'https://unsplash.com/photos/x337TitjvNE',
    ],
    'coctel' => [
        'src' => 'https://images.unsplash.com/photo-1750124933194-e021f47b5f8b',
        'alt' => 'Un cóctel sobre la barra con botellas al fondo',
        'by' => 'Brett Wharton', 'url' => 'https://unsplash.com/photos/Pq6omXyUpWA',
    ],
    'plato' => [
        'src' => 'https://images.unsplash.com/photo-1612929633738-8fe44f7ec841',
        'alt' => 'Plato de la casa servido en vajilla negra',
        'by' => 'ikhsan baihaqi', 'url' => 'https://unsplash.com/photos/pbc2wXbQYpI',
    ],
];

/** URL de una foto a un ancho concreto (Unsplash las redimensiona al vuelo). */
function photo(string $key, int $w = 1600): string
{
    $own = settings()['photos'][$key] ?? '';
    if ($own !== '') return $own;
    $src = PHOTOS[$key]['src'];
    if (str_starts_with($src, 'https://images.unsplash.com/')) {
        return $src . '?auto=format&fit=crop&q=70&w=' . $w;
    }
    return $src;
}

function photo_srcset(string $key, array $widths = [640, 1024, 1600, 2200]): string
{
    if (!empty(settings()['photos'][$key])) return '';
    if (!str_starts_with(PHOTOS[$key]['src'], 'https://images.unsplash.com/')) return '';
    return implode(', ', array_map(fn($w) => photo($key, $w) . " {$w}w", $widths));
}

/** Fotos de Unsplash que siguen en uso (para los créditos del pie). */
function photo_credits(): array
{
    $own = settings()['photos'];
    return array_filter(PHOTOS, fn($p, $k) => empty($own[$k]) && isset($p['by']), ARRAY_FILTER_USE_BOTH);
}
