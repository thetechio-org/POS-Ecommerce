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
 * Real product shots are used wherever a matching one can be fetched — phones get
 * phone photos, laptops get laptop photos. For the categories with no source of
 * real shots (televisions, cameras, speakers), a clean typographic card is drawn
 * instead. Both read as deliberate; a mismatched stock photo does not, which is
 * why keyword image services are not used here.
 *
 *     php artisan demo:images
 *     php artisan demo:images --force    # redo products that already have one
 */
class DemoImages extends Command
{
    protected $signature = 'demo:images {--force : Replace images that already exist}';

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

    /** Background pairs for the drawn cards, keyed loosely by feel. */
    private const PALETTES = [
        ['#0f2c24', '#1b4d3e', '#c9a227'],
        ['#12233a', '#1d3a5f', '#7fb2e5'],
        ['#2a1a2e', '#452a4d', '#d9a5e0'],
        ['#2b2118', '#4a3826', '#e0b56a'],
        ['#1a2b2b', '#2c4a4a', '#8fd4cf'],
        ['#261c1c', '#452e2e', '#e09a9a'],
    ];

    private array $pool = [];

    public function handle(): int
    {
        Storage::disk('public')->makeDirectory('products');
        Storage::disk('public')->makeDirectory('products/variants');

        $this->fetchPool();

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

            if ($this->downloadFor($category, $path)) {
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
     * Draw a typographic product card: brand line, wrapped product name, and a
     * category label, over a soft diagonal gradient.
     */
    private function drawCard(string $name, string $brand, string $category, string $path): void
    {
        $size = 800;
        $img = imagecreatetruecolor($size, $size);
        [$from, $to, $accentHex] = self::PALETTES[abs(crc32($category)) % count(self::PALETTES)];

        $this->gradient($img, $size, $from, $to);

        $white  = imagecolorallocate($img, 255, 255, 255);
        $accent = $this->allocate($img, $accentHex);
        $muted  = imagecolorallocatealpha($img, 255, 255, 255, 70);

        $bold    = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        $regular = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

        // A quiet ring behind the type, so the card is not a flat rectangle
        imagesetthickness($img, 2);
        imageellipse($img, $size / 2, $size / 2, 560, 560, $muted);
        imageellipse($img, $size / 2, $size / 2, 620, 620, $muted);

        imagettftext($img, 20, 0, 70, 120, $accent, $bold, mb_strtoupper($brand));

        $lines = $this->wrap($name, 16);
        $y = ($size / 2) - (count($lines) - 1) * 34 - 10;
        foreach ($lines as $line) {
            imagettftext($img, 38, 0, 70, $y, $white, $bold, $line);
            $y += 68;
        }

        imagettftext($img, 17, 0, 70, $size - 80, $muted, $regular, $category);

        ob_start();
        imagewebp($img, null, 88);
        $binary = ob_get_clean();
        imagedestroy($img);

        Storage::disk('public')->put($path, $binary);
    }

    private function gradient($img, int $size, string $from, string $to): void
    {
        [$r1, $g1, $b1] = $this->rgb($from);
        [$r2, $g2, $b2] = $this->rgb($to);

        for ($i = 0; $i < $size; $i++) {
            $t = $i / $size;
            $colour = imagecolorallocate(
                $img,
                (int) ($r1 + ($r2 - $r1) * $t),
                (int) ($g1 + ($g2 - $g1) * $t),
                (int) ($b1 + ($b2 - $b1) * $t)
            );
            imageline($img, 0, $i, $size, $i, $colour);
        }
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
