<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Give every demo product its own picture.
 *
 * DemoSeeder records, per product, the URL of the photograph its name came from,
 * so what is downloaded here always matches what the listing says. Anything the
 * seeder could not find a photograph for falls back to a drawn typographic card.
 *
 *     php artisan demo:images
 *     php artisan demo:images --force    # re-fetch products that already have one
 */
class DemoImages extends Command
{
    protected $signature = 'demo:images {--force : Replace images that already exist}';

    protected $description = 'Download or draw a product image for every demo product';

    /** Accent colours for the drawn cards — one per category, on white. */
    private const ACCENTS = [
        '#0f5132', '#1d4e89', '#7d3c98', '#a8500f', '#0e7490', '#9d2f4f',
    ];

    /** product id → source image URL, written by DemoSeeder. */
    private array $map = [];

    public function handle(): int
    {
        Storage::disk('public')->makeDirectory('products');
        Storage::disk('public')->makeDirectory('products/variants');

        $this->map = json_decode(Storage::disk('local')->get('demo-images.json') ?: '{}', true) ?: [];
        $this->line('Source images recorded by the seeder: ' . count(array_filter($this->map)));

        $products = Product::with('category')->get();
        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        $downloaded = $drawn = $skipped = 0;

        foreach ($products as $product) {
            if ($product->product_img && ! $this->option('force')
                && Storage::disk('public')->exists($product->product_img)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $path = "products/{$product->id}.webp";

            if ($this->download($this->map[$product->id] ?? null, $path)) {
                $downloaded++;
            } else {
                $this->drawCard($product->name, $product->brand ?? 'Sellora',
                                $product->category->name ?? '', $path);
                $drawn++;
            }

            $product->forceFill(['product_img' => $path])->save();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Variants reuse the parent's picture — a colour name on a card would be
        // the only difference, and that is not worth another 70 files.
        $variants = ProductVariant::whereNull('product_img')->get();
        foreach ($variants as $variant) {
            $parent = Product::find($variant->product_id);
            if ($parent?->product_img) {
                $variant->forceFill(['product_img' => $parent->product_img])->save();
            }
        }

        $this->info("Downloaded: {$downloaded}   Drawn: {$drawn}   Skipped: {$skipped}");
        $this->info("Variants linked: {$variants->count()}");

        return self::SUCCESS;
    }

    /** Fetch one product's own photograph. */
    private function download(?string $url, string $path): bool
    {
        if (! $url) {
            return false;
        }

        try {
            $response = Http::timeout(25)->retry(2, 400)->get($url);

            if (! $response->successful() || strlen($response->body()) < 800) {
                return false;
            }

            Storage::disk('public')->put($path, $response->body());

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Draw a typographic product card.
     *
     * The real product shots come on white, so these do too — a dark card sitting
     * beside them in the grid reads as a broken image rather than a considered
     * one. Only the accent colour changes between categories.
     */
    private function drawCard(string $name, string $brand, string $category, string $path): void
    {
        $size = 800;
        $img = imagecreatetruecolor($size, $size);

        $accentHex = self::ACCENTS[abs(crc32($category)) % count(self::ACCENTS)];
        $accent = $this->allocate($img, $accentHex);
        $white  = imagecolorallocate($img, 255, 255, 255);
        $ink    = imagecolorallocate($img, 26, 32, 38);
        $muted  = imagecolorallocate($img, 140, 150, 158);
        $hair   = imagecolorallocate($img, 232, 236, 239);

        imagefilledrectangle($img, 0, 0, $size, $size, $white);

        // A faint disc keeps the centre from reading as empty paper
        imagefilledellipse($img, $size / 2, $size / 2 - 30, 470, 470, $hair);

        $bold    = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        $regular = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

        // Accent rule + brand, top left
        imagefilledrectangle($img, 70, 96, 118, 100, $accent);
        imagettftext($img, 19, 0, 70, 84, $accent, $bold, mb_strtoupper($brand));

        $lines = $this->wrap($name, 15);
        $y = ($size / 2) - (count($lines) - 1) * 32;
        foreach ($lines as $line) {
            imagettftext($img, 36, 0, 70, $y, $ink, $bold, $line);
            $y += 64;
        }

        imagettftext($img, 17, 0, 70, $size - 82, $muted, $regular, $category);

        ob_start();
        imagewebp($img, null, 90);
        $binary = ob_get_clean();
        imagedestroy($img);

        Storage::disk('public')->put($path, $binary);
    }

    private function allocate($img, string $hex): int
    {
        [$r, $g, $b] = $this->rgb($hex);

        return imagecolorallocate($img, $r, $g, $b);
    }

    /** @return array{0:int,1:int,2:int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** @return array<int,string> */
    private function wrap(string $text, int $perLine): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($current === '') {
                $current = $word;
            } elseif (mb_strlen($current . ' ' . $word) <= $perLine) {
                $current .= ' ' . $word;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return array_slice($lines, 0, 4);
    }
}
