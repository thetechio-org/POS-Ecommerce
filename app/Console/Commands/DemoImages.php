<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Give every demo product a picture.
 *
 * Every product gets a drawn typographic card by default. Cards are consistent
 * and never wrong; the free sources of real product shots only cover a few
 * categories and are a generation or two out of date, so a phone photo ends up
 * under the wrong model name — which an audience of phone buyers will notice.
 *
 *     php artisan demo:images --force
 *     php artisan demo:images --force --photos   # opt back into real shots
 */
class DemoImages extends Command
{
    protected $signature = 'demo:images {--force : Replace images that already exist}
                            {--photos : Pull real product shots where a matching one exists}';

    protected $description = 'Download or draw a product image for every demo product';

    /** Our category name → the upstream category that has usable shots. */
    private const SOURCES = [
        'Smartphones'       => 'smartphones',
        'Tablets'           => 'tablets',
        'Laptops'           => 'laptops',
        'Smartwatches'      => 'mens-watches',
        'Fitness Trackers'  => 'mens-watches',
        'Chargers & Cables' => 'mobile-accessories',
        'Cases & Covers'    => 'mobile-accessories',
        'Power Banks'       => 'mobile-accessories',
    ];

    /** Accent colours for the drawn cards — one per category, on white. */
    private const ACCENTS = [
        '#0f5132', '#1d4e89', '#7d3c98', '#a8500f', '#0e7490', '#9d2f4f',
    ];

    private array $pool = [];

    public function handle(): int
    {
        Storage::disk('public')->makeDirectory('products');
        Storage::disk('public')->makeDirectory('products/variants');

        if ($this->option('photos')) {
            $this->fetchPool();
        }

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
            $category = $product->category->name ?? '';

            if ($this->option('photos') && $this->downloadFor($category, $path)) {
                $downloaded++;
            } else {
                $this->drawCard($product->name, $product->brand ?? 'Sellora', $category, $path);
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

    /**
     * Collect real product-shot URLs, grouped by our category name.
     * A failure here is not fatal — every product falls back to a drawn card.
     */
    private function fetchPool(): void
    {
        foreach (array_unique(array_values(self::SOURCES)) as $slug) {
            try {
                $response = Http::timeout(20)->retry(2, 500)
                    ->get("https://dummyjson.com/products/category/{$slug}", ['limit' => 50, 'select' => 'images']);

                if (! $response->successful()) {
                    continue;
                }

                foreach ($response->json('products', []) as $item) {
                    foreach ($item['images'] ?? [] as $url) {
                        $this->pool[$slug][] = $url;
                    }
                }
            } catch (\Throwable $e) {
                $this->warn("Could not reach the image source for {$slug}: {$e->getMessage()}");
            }
        }

        $total = array_sum(array_map('count', $this->pool));
        $this->line("Real product shots available: {$total}");
    }

    private function downloadFor(string $category, string $path): bool
    {
        $slug = self::SOURCES[$category] ?? null;

        if (! $slug || empty($this->pool[$slug])) {
            return false;
        }

        // Rotate through the pool so the same shot is not used twice in a row.
        $url = array_shift($this->pool[$slug]);
        $this->pool[$slug][] = $url;

        try {
            $response = Http::timeout(20)->get($url);

            if (! $response->successful() || strlen($response->body()) < 1000) {
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
