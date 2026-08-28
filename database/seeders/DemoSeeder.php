<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Demo dataset — a Saudi electronics retailer.
 *
 * Built for showing the system to an audience rather than for testing: prices are
 * in SAR, VAT is the Saudi 15%, branches are real Riyadh/Jeddah/Khobar districts,
 * and eight months of trading history sit behind the dashboard so its charts and
 * reports have something to draw.
 *
 *     php artisan db:seed --class=DemoSeeder --force
 *
 * Re-running wipes and rebuilds every demo table. It deliberately leaves the
 * `users` row you sign in with alone, so a re-seed never locks you out.
 */
class DemoSeeder extends Seeder
{
    private const VAT = 15.00;

    /** Staff and customers all share this, so the demo is easy to drive. */
    private const DEMO_PASSWORD = 'Sellora@2026';

    private array $ids = [];

    public function run(): void
    {
        $this->command->info('Building the Riyadh electronics demo…');

        // Not wrapped in a transaction: TRUNCATE causes an implicit commit in
        // MySQL, so the wipe below would end it and the commit would then fail.
        $this->wipe();
        $this->settings();
        $this->warehousesAndBranches();
        $this->staff();
        $this->catalogue();
        $this->suppliers();
        $this->products();
        $this->stock();
        $this->customers();
        $this->discounts();
        $this->purchases();
        $this->sales();
        $this->expenses();
        $this->quotations();
        $this->registers();

        $this->command->info('Done.');
        $this->summary();
    }

    /**
     * Clear the demo tables. The admin account survives — being locked out of
     * your own demo an hour before a keynote is not a recoverable situation.
     */
    private function wipe(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ([
            'sale_items', 'sales', 'purchase_items', 'purchases',
            'quotation_items', 'quotations', 'payments', 'payment_transactions',
            'stock_ledgers', 'inventory_stocks', 'stock_transfers',
            'sales_return_items', 'sales_returns', 'purchase_return_items', 'purchase_returns',
            'stock_adjustments', 'cash_registers', 'expenses', 'expense_categories',
            'product_supplier', 'product_variants', 'products', 'categories',
            'suppliers', 'customers', 'discount_rules', 'branches', 'warehouses',
        ] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        DB::table('users')->where('email', '!=', $this->adminEmail())->delete();
        DB::table('settings')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function adminEmail(): string
    {
        return DB::table('users')->orderBy('id')->value('email') ?? 'admin@sellora.sa';
    }

    // ── settings ────────────────────────────────────────────────────────────

    private function settings(): void
    {
        DB::table('settings')->insert([
            'business_name'   => 'Sellora Electronics',
            'logo_path'       => null,
            'currency_symbol' => 'SAR',
            'currency_code'   => 'SAR',
            'primary_color'   => '#0f5132',
            'secondary_color' => '#c9a227',
            'timezone'        => 'Asia/Riyadh',
            'default_email'   => 'hello@sellora.sa',
            'company_phone'   => '+966 11 293 4400',
            'footer'          => 'Thank you for shopping with Sellora Electronics.',
            'country'         => 'Saudi Arabia',
            'state'           => 'Riyadh Province',
            'city'            => 'Riyadh',
            'postal_code'     => '12211',
            'address'         => 'Olaya Street, Al Olaya District, Riyadh 12211',
            'developed_by'    => 'Sellora',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    // ── warehouses and branches ─────────────────────────────────────────────

    private function warehousesAndBranches(): void
    {
        $warehouses = [
            ['Riyadh Central Warehouse',   'Second Industrial City, Riyadh',  '60000', 21400],
            ['Jeddah Distribution Center', 'Al Khumrah, Jeddah',              '45000', 16800],
            ['Dammam Regional Warehouse',  'Dammam Industrial City',          '30000',  9200],
            ['Olaya Store Stockroom',      'Olaya Street, Riyadh',             '4000',     0],
            ['Exit 9 Store Stockroom',     'Eastern Ring Road, Riyadh',        '3500',     0],
            ['Tahlia Store Stockroom',     'Tahlia Street, Jeddah',            '3500',     0],
            ['Khobar Store Stockroom',     'Prince Turkey Street, Khobar',     '3000',     0],
            ['Online Fulfilment Center',   'Riyadh Logistics Park',           '20000',  6400],
        ];

        foreach ($warehouses as [$name, $location, $capacity, $used]) {
            $this->ids['wh'][] = DB::table('warehouses')->insertGetId([
                'name' => $name, 'location' => $location,
                'capacity' => $capacity, 'capacity_unit' => 'units', 'used_capacity' => $used,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $branches = [
            ['Riyadh Olaya',     'Olaya Street, Al Olaya, Riyadh',        '+966 11 293 4400', 3],
            ['Riyadh Exit 9',    'Eastern Ring Road, Al Hamra, Riyadh',   '+966 11 293 4411', 4],
            ['Jeddah Tahlia',    'Tahlia Street, Al Andalus, Jeddah',     '+966 12 660 7788', 5],
            ['Khobar Corniche',  'Prince Turkey Street, Al Khobar',       '+966 13 895 2200', 6],
            ['Ecommerce-store',  'Online',                                'support@sellora.sa', 7],
        ];

        foreach ($branches as [$name, $location, $contact, $whIndex]) {
            $this->ids['branch'][] = DB::table('branches')->insertGetId([
                'name' => $name, 'location' => $location, 'contact' => $contact,
                'warehouse_id' => $this->ids['wh'][$whIndex],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    // ── staff ───────────────────────────────────────────────────────────────

    private function staff(): void
    {
        $roles = DB::table('roles')->pluck('id', 'name');

        // Keep the existing admin, and put them on the flagship branch.
        DB::table('users')->where('email', $this->adminEmail())->update([
            'role_id'   => $roles['Admin'],
            'branch_id' => $this->ids['branch'][0],
            'status'    => 'Active',
        ]);

        $staff = [
            ['Faisal Al-Otaibi',  'faisal.manager@sellora.sa',    'Manager',    0],
            ['Noura Al-Harbi',    'noura.manager@sellora.sa',     'Manager',    2],
            ['Abdullah Al-Qahtani','abdullah.cashier@sellora.sa', 'Cashier',    0],
            ['Sara Al-Dosari',    'sara.cashier@sellora.sa',      'Cashier',    1],
            ['Yousef Al-Ghamdi',  'yousef.cashier@sellora.sa',    'Cashier',    2],
            ['Hessa Al-Mutairi',  'hessa.accounts@sellora.sa',    'Accountant', 0],
            ['Omar Al-Shehri',    'omar.inventory@sellora.sa',    'Inventory',  0],
        ];

        foreach ($staff as [$name, $email, $role, $branchIndex]) {
            $this->ids['user'][] = DB::table('users')->insertGetId([
                'name' => $name, 'email' => $email,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'status' => 'Active',
                'role_id' => $roles[$role],
                'branch_id' => $this->ids['branch'][$branchIndex],
                'email_verified_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->ids['admin'] = DB::table('users')->where('email', $this->adminEmail())->value('id');
        $this->ids['allUsers'] = array_merge([$this->ids['admin']], $this->ids['user']);
    }

    // ── categories ──────────────────────────────────────────────────────────

    /**
     * The shop's category filter only lists categories that have a parent, so
     * every browsable category here is a child of a top-level one.
     */
    private function catalogue(): void
    {
        $tree = [
            'Mobile'    => ['Smartphones', 'Phone Accessories'],
            'Computing' => ['Laptops', 'Tablets'],
            'Audio'     => ['Headphones & Speakers'],
            'Watches'   => ['Luxury Watches', 'Smart Watches'],
        ];

        foreach ($tree as $parent => $children) {
            $parentId = DB::table('categories')->insertGetId([
                'name' => $parent, 'parent_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($children as $child) {
                $this->ids['cat'][$child] = DB::table('categories')->insertGetId([
                    'name' => $child, 'parent_id' => $parentId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    // ── suppliers ───────────────────────────────────────────────────────────

    private function suppliers(): void
    {
        $suppliers = [
            ['Gulf Tech Distribution',      'Khalid Al-Rasheed',  '+966 11 464 2200', 'sales@gulftech.sa',        'Al Sulaymaniyah, Riyadh'],
            ['Arabian Electronics Co.',     'Mona Al-Zahrani',    '+966 12 651 9080', 'orders@arabianelec.sa',    'Al Balad, Jeddah'],
            ['Jarir Wholesale',             'Tariq Al-Saleh',     '+966 11 219 8877', 'b2b@jarirwholesale.sa',    'King Abdullah Road, Riyadh'],
            ['Emirates Digital Trading',    'Rashid Al-Nuaimi',   '+971 4 883 1100',  'export@emiratesdigi.ae',   'Jebel Ali Free Zone, Dubai'],
            ['Al Falak Technology',         'Hana Al-Subaie',     '+966 13 833 4455', 'info@alfalaktech.sa',      'Dhahran Techno Valley'],
            ['Riyadh Mobile Supplies',      'Majed Al-Harthy',    '+966 11 405 6677', 'sales@riyadhmobile.sa',    'Al Batha, Riyadh'],
            ['Nesma Electronics Import',    'Layla Al-Amoudi',    '+966 12 690 3344', 'purchase@nesmaelec.sa',    'Al Rawdah, Jeddah'],
            ['Shenzhen Prime Export',       'Wei Chen',           '+86 755 8899 1200','intl@szprime.cn',          'Futian District, Shenzhen'],
            ['Al Khobar Trading House',     'Sultan Al-Dawsari',  '+966 13 887 6600', 'contact@khobartrading.sa', 'Corniche Road, Al Khobar'],
            ['Bahrain Gulf Imports',        'Ali Al-Mahmood',     '+973 1751 3300',   'sales@bahraingulf.bh',     'Manama, Bahrain'],
        ];

        foreach ($suppliers as [$name, $person, $phone, $email, $address]) {
            $this->ids['supplier'][] = DB::table('suppliers')->insertGetId([
                'name' => $name, 'contact_person' => $person, 'phone' => $phone,
                'email' => $email, 'address' => $address, 'balance' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    // ── products ────────────────────────────────────────────────────────────

    /**
     * Build the catalogue from a source that ships real product photography.
     *
     * The names come from the same place as the pictures, so a phone listing
     * always shows that phone. Building the other way round — inventing a
     * catalogue and then hunting for images — is what produces an iPhone 5
     * photograph under an "iPhone 16 Pro Max" heading.
     *
     * Prices are the source's USD converted at the riyal's 3.75 peg, which lands
     * them in the right place for Saudi retail.
     */
    private function products(): void
    {
        $unit = DB::table('units')->where('name', 'Piece')->value('id')
             ?? DB::table('units')->orderBy('id')->value('id');

        DB::table('units')->where('id', $unit)->update(['conversion_factor' => 1]);

        $imageMap = [];
        $n = 1;

        foreach ($this->fetchCatalogue() as $item) {
            $sku = 'SEL-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            $price = (float) round($item['usd'] * 3.75);
            $lowStock = $price > 3000 ? 3 : ($price > 800 ? 6 : 15);

            $productId = DB::table('products')->insertGetId([
                'name' => $item['name'],
                'category_id' => $this->ids['cat'][$item['category']],
                'base_unit_id' => $unit,
                'default_display_unit_id' => $unit,
                'has_variants' => false,
                'sku' => $sku,
                'barcode' => '628' . str_pad((string) (100000 + $n * 7), 10, '0', STR_PAD_LEFT),
                'brand' => $item['brand'],
                'track_expiry' => false,
                'tax_rate' => self::VAT,
                'actual_price' => $price,
                'low_stock' => $lowStock,
                'created_at' => now()->subDays(rand(20, 300)),
                'updated_at' => now(),
            ]);

            $this->ids['product'][] = ['id' => $productId, 'price' => $price, 'name' => $item['name']];
            $imageMap[$productId] = $item['image'];

            foreach ((array) array_rand(array_flip($this->ids['supplier']), 2) as $supplierId) {
                DB::table('product_supplier')->insert([
                    'product_id' => $productId, 'supplier_id' => $supplierId,
                ]);
            }

            $n++;
        }

        // demo:images reads this to fetch each product's own picture.
        Storage::disk('local')->put('demo-images.json', json_encode($imageMap));

        $this->command->line('  catalogue built from ' . count($imageMap) . ' photographed products');
    }

    /**
     * Pull the photographed catalogue, falling back to a small built-in list so
     * seeding still works on a machine with no outbound network.
     *
     * @return array<int, array{name:string,brand:string,category:string,usd:float,image:?string}>
     */
    private function fetchCatalogue(): array
    {
        $sources = [
            'smartphones'        => 'Smartphones',
            'laptops'            => 'Laptops',
            'tablets'            => 'Tablets',
            'mobile-accessories' => null,          // split by what the item is
            'mens-watches'       => 'Luxury Watches',
            'womens-watches'     => 'Luxury Watches',
        ];

        $audio = ['airpods', 'beats', 'homepod', 'echo', 'headphone', 'earphone', 'speaker'];
        $smartWatch = ['apple watch', 'smart watch'];
        $catalogue = [];

        foreach ($sources as $slug => $category) {
            try {
                $response = Http::timeout(25)->retry(2, 500)->get(
                    "https://dummyjson.com/products/category/{$slug}",
                    ['limit' => 50, 'select' => 'title,price,brand,images']
                );

                if (! $response->successful()) {
                    continue;
                }

                foreach ($response->json('products', []) as $item) {
                    $title = $item['title'] ?? null;

                    if (! $title) {
                        continue;
                    }

                    $resolved = $category;

                    if ($resolved === null) {
                        $lower = mb_strtolower($title);
                        $resolved = 'Phone Accessories';

                        foreach ($audio as $needle) {
                            if (str_contains($lower, $needle)) {
                                $resolved = 'Headphones & Speakers';
                                break;
                            }
                        }
                        foreach ($smartWatch as $needle) {
                            if (str_contains($lower, $needle)) {
                                $resolved = 'Smart Watches';
                                break;
                            }
                        }
                    }

                    $catalogue[] = [
                        'name' => $title,
                        'brand' => $item['brand'] ?? explode(' ', $title)[0],
                        'category' => $resolved,
                        'usd' => (float) ($item['price'] ?? 99),
                        'image' => $item['images'][0] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                $this->command->warn("Could not reach the catalogue source for {$slug}.");
            }
        }

        return $catalogue ?: $this->fallbackCatalogue();
    }

    /** @return array<int, array<string, mixed>> */
    private function fallbackCatalogue(): array
    {
        $items = [
            ['iPhone 13 Pro', 'Apple', 'Smartphones', 1099.99],
            ['iPhone X', 'Apple', 'Smartphones', 899.99],
            ['Samsung Galaxy S10', 'Samsung', 'Smartphones', 699.99],
            ['Oppo F19 Pro Plus', 'Oppo', 'Smartphones', 399.99],
            ['Apple MacBook Pro 14 Inch', 'Apple', 'Laptops', 1999.99],
            ['DELL XPS 13 9300', 'Dell', 'Laptops', 1499.99],
            ['iPad Mini 2021', 'Apple', 'Tablets', 499.99],
            ['Apple AirPods Max', 'Apple', 'Headphones & Speakers', 549.99],
            ['Apple iPhone Charger', 'Apple', 'Phone Accessories', 19.99],
            ['Rolex Datejust', 'Rolex', 'Luxury Watches', 10999.99],
        ];

        return array_map(fn ($i) => [
            'name' => $i[0], 'brand' => $i[1], 'category' => $i[2], 'usd' => $i[3], 'image' => null,
        ], $items);
    }

    // ── stock ───────────────────────────────────────────────────────────────

    /**
     * Stock is spread across the warehouses, and a handful of lines are pushed
     * below their threshold on purpose so the low-stock report has something in it.
     */
    private function stock(): void
    {
        $storeWarehouses = array_slice($this->ids['wh'], 3);  // store rooms + online
        $bulkWarehouses  = array_slice($this->ids['wh'], 0, 3);

        $lowStockPicks = array_rand(array_flip(array_column($this->ids['product'], 'id')), 9);

        foreach ($this->ids['product'] as $p) {
            $isLow = in_array($p['id'], $lowStockPicks, true);

            foreach ($bulkWarehouses as $wh) {
                $this->stockRow($p['id'], null, $wh, $isLow ? rand(0, 2) : rand(15, 90));
            }
            foreach ($storeWarehouses as $wh) {
                $this->stockRow($p['id'], null, $wh, $isLow ? rand(0, 1) : rand(4, 30));
            }
        }

        foreach ($this->ids['variant'] ?? [] as $v) {
            foreach ($this->ids['wh'] as $wh) {
                $this->stockRow($v['product_id'], $v['id'], $wh, rand(2, 25));
            }
        }
    }

    private function stockRow(int $productId, ?int $variantId, int $warehouseId, int $qty): void
    {
        DB::table('inventory_stocks')->insert([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'quantity_in_base_unit' => $qty,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ── customers ───────────────────────────────────────────────────────────

    private function customers(): void
    {
        $first = ['Mohammed', 'Abdulaziz', 'Fahad', 'Sultan', 'Khalid', 'Nasser', 'Turki', 'Bandar',
                  'Reem', 'Noura', 'Sara', 'Lama', 'Aisha', 'Maha', 'Hind', 'Jawaher',
                  'Omar', 'Yazeed', 'Salman', 'Ibrahim', 'Layla', 'Dana', 'Rana', 'Amal'];
        $last  = ['Al-Saud', 'Al-Otaibi', 'Al-Qahtani', 'Al-Ghamdi', 'Al-Harbi', 'Al-Zahrani',
                  'Al-Dosari', 'Al-Shehri', 'Al-Mutairi', 'Al-Subaie', 'Al-Anazi', 'Al-Rashid',
                  'Al-Amoudi', 'Al-Sahli', 'Al-Balawi'];
        $cities = ['Riyadh', 'Jeddah', 'Dammam', 'Al Khobar', 'Mecca', 'Medina', 'Tabuk', 'Abha'];
        $districts = ['Al Olaya', 'Al Malqa', 'Al Nakheel', 'Al Andalus', 'Al Rawdah',
                      'Al Hamra', 'King Fahd District', 'Al Yasmin'];

        for ($i = 0; $i < 48; $i++) {
            $name = $first[$i % count($first)] . ' ' . $last[($i * 3) % count($last)];
            $city = $cities[$i % count($cities)];

            $this->ids['customer'][] = DB::table('customers')->insertGetId([
                'name' => $name,
                'phone' => '+9665' . rand(0, 5) . rand(1000000, 9999999),
                'email' => strtolower(str_replace([' ', '-'], ['.', ''], $name)) . $i . '@example.sa',
                // The first dozen can sign in to the storefront and browse their orders.
                'password' => $i < 12 ? Hash::make(self::DEMO_PASSWORD) : null,
                'address' => $districts[$i % count($districts)] . ', ' . $city,
                'city' => $city,
                'country' => 'Saudi Arabia',
                'postcode' => (string) rand(11000, 34999),
                'card_id' => 'SEL-C-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'balance' => 0,
                'created_at' => now()->subDays(rand(10, 250)),
                'updated_at' => now(),
            ]);
        }
    }

    // ── discounts ───────────────────────────────────────────────────────────

    private function discounts(): void
    {
        DB::table('discount_rules')->insert([
            [
                'name' => 'Accessories Week', 'type' => 'category',
                'target_ids' => json_encode([$this->ids['cat']['Chargers & Cables'], $this->ids['cat']['Cases & Covers']]),
                'discount' => 15, 'coupon_code' => null,
                'start_date' => now()->subDays(5)->toDateString(),
                'end_date' => now()->addDays(25)->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'name' => 'Wearables Promo', 'type' => 'category',
                'target_ids' => json_encode([$this->ids['cat']['Fitness Trackers']]),
                'discount' => 10, 'coupon_code' => null,
                'start_date' => now()->subDays(2)->toDateString(),
                'end_date' => now()->addDays(30)->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'name' => 'LEAP 2026 Launch Offer', 'type' => 'coupon',
                'target_ids' => json_encode([]),
                'discount' => 12, 'coupon_code' => 'LEAP2026',
                'start_date' => now()->subDays(1)->toDateString(),
                'end_date' => now()->addDays(60)->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'name' => 'Welcome Discount', 'type' => 'coupon',
                'target_ids' => json_encode([]),
                'discount' => 5, 'coupon_code' => 'WELCOME5',
                'start_date' => now()->subDays(30)->toDateString(),
                'end_date' => now()->addDays(90)->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    // ── purchases ───────────────────────────────────────────────────────────

    private function purchases(): void
    {
        $products = $this->ids['product'];

        for ($i = 1; $i <= 38; $i++) {
            $date = now()->subDays(rand(15, 260));
            $supplier = $this->ids['supplier'][array_rand($this->ids['supplier'])];
            $warehouse = $this->ids['wh'][array_rand(array_slice($this->ids['wh'], 0, 3))];
            $user = $this->ids['allUsers'][array_rand($this->ids['allUsers'])];

            $purchaseId = DB::table('purchases')->insertGetId([
                'supplier_id' => $supplier,
                'warehouse_id' => $warehouse,
                'invoice_number' => $date->format('Y') . '-PUR-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'purchase_date' => $date->toDateString(),
                'total_amount' => 0, 'paid_amount' => 0, 'due_amount' => 0,
                'notes' => null, 'created_by' => $user,
                'created_at' => $date, 'updated_at' => $date,
            ]);

            $total = 0;
            foreach ((array) array_rand($products, rand(3, 7)) as $idx) {
                $p = $products[$idx];
                $qty = rand(5, 40);
                $cost = round($p['price'] * 0.72, 2);   // a ~28% retail margin
                $lineTotal = $cost * $qty;
                $total += $lineTotal;

                DB::table('purchase_items')->insert([
                    'purchase_id' => $purchaseId, 'product_id' => $p['id'], 'variant_id' => null,
                    'quantity' => $qty, 'quantity_in_base_unit' => $qty,
                    'unit_cost' => $cost, 'total_cost' => $lineTotal,
                    'created_at' => $date, 'updated_at' => $date,
                ]);

                DB::table('stock_ledgers')->insert([
                    'product_id' => $p['id'], 'variant_id' => null, 'warehouse_id' => $warehouse,
                    'ref_type' => 'purchase', 'ref_id' => $purchaseId,
                    'quantity_change_in_base_unit' => $qty, 'unit_cost' => $cost,
                    'direction' => 'in', 'created_by' => $user,
                    'created_at' => $date, 'updated_at' => $date,
                ]);
            }

            // Most purchases settled, a few still owing
            $paid = $i % 5 === 0 ? round($total * 0.6, 2) : $total;
            $due = round($total - $paid, 2);

            DB::table('purchases')->where('id', $purchaseId)->update([
                'total_amount' => $total, 'paid_amount' => $paid, 'due_amount' => $due,
            ]);

            if ($due > 0) {
                DB::table('suppliers')->where('id', $supplier)->increment('balance', $due);
            }

            DB::table('payments')->insert([
                'entity_type' => 'supplier', 'entity_id' => $supplier,
                'transaction_type' => 'out', 'ref_type' => 'purchase', 'ref_id' => $purchaseId,
                'amount' => $paid, 'payment_method' => ['cash', 'bank', 'card'][rand(0, 2)],
                'note' => 'Payment for purchase', 'created_by' => $user,
                'created_at' => $date, 'updated_at' => $date,
            ]);
        }
    }

    // ── sales ───────────────────────────────────────────────────────────────

    /**
     * Eight months of trading, weighted towards recent weeks so the dashboard's
     * trend lines slope the right way. Roughly a third are storefront orders.
     */
    private function sales(): void
    {
        $products = $this->ids['product'];
        $cashiers = $this->ids['allUsers'];
        $ecomBranch = $this->ids['branch'][4];
        $storeBranches = array_slice($this->ids['branch'], 0, 4);
        $counters = [];

        for ($i = 1; $i <= 240; $i++) {
            // Weight towards the last two months
            $daysAgo = rand(0, 100) < 55 ? rand(0, 60) : rand(61, 240);
            $date = now()->subDays($daysAgo)->setTime(rand(9, 21), rand(0, 59));

            $isEcom = rand(0, 100) < 34;
            $branch = $isEcom ? $ecomBranch : $storeBranches[array_rand($storeBranches)];
            $user = $isEcom ? null : $cashiers[array_rand($cashiers)];
            $customer = $this->ids['customer'][array_rand($this->ids['customer'])];

            $year = $date->format('Y');
            $counters[$year] = ($counters[$year] ?? 0) + 1;
            $invoice = $year . '-invoice-' . str_pad((string) $counters[$year], 4, '0', STR_PAD_LEFT);

            $lines = [];
            $subtotal = 0;
            foreach ((array) array_rand($products, rand(1, 5)) as $idx) {
                $p = $products[$idx];
                $qty = rand(1, 3);
                $lineTotal = $p['price'] * $qty;
                $subtotal += $lineTotal;
                $lines[] = ['p' => $p, 'qty' => $qty, 'total' => $lineTotal];
            }

            $discount = rand(0, 100) < 25 ? round($subtotal * (rand(5, 15) / 100), 2) : 0;
            $tax = round(($subtotal - $discount) * self::VAT / 100, 2);
            $shipping = $isEcom ? (rand(0, 100) < 60 ? 25 : 0) : 0;
            $final = round($subtotal - $discount + $tax + $shipping, 2);

            if ($isEcom) {
                $status = $this->weighted(['delivered' => 55, 'shipped' => 15, 'confirmed' => 12, 'pending' => 12, 'cancelled' => 6]);
                $paid = in_array($status, ['delivered'], true) ? $final : 0;
            } else {
                $status = 'delivered';
                // A few counter sales left partly unpaid
                $paid = rand(0, 100) < 12 ? round($final * 0.5, 2) : $final;
            }
            $due = round($final - $paid, 2);

            $saleId = DB::table('sales')->insertGetId([
                'customer_id' => $customer, 'branch_id' => $branch,
                'invoice_number' => $invoice, 'sale_date' => $date->toDateString(),
                'total_amount' => $subtotal, 'discount_amount' => $discount, 'discount_type' => 'fixed',
                'tax_amount' => $tax, 'tax_percentage' => self::VAT, 'shipping' => $shipping,
                'final_amount' => $final, 'paid_amount' => $paid, 'due_amount' => $due,
                'payment_method' => $isEcom ? 'cash' : $this->weighted(['card' => 60, 'cash' => 35, 'mixed' => 5]),
                'sale_origin' => $isEcom ? 'E-commerce' : 'POS',
                'status' => $status, 'created_by' => $user,
                'created_at' => $date, 'updated_at' => $date,
            ]);

            $warehouse = DB::table('branches')->where('id', $branch)->value('warehouse_id');

            foreach ($lines as $line) {
                DB::table('sale_items')->insert([
                    'sale_id' => $saleId, 'product_id' => $line['p']['id'], 'variant_id' => null,
                    'quantity' => $line['qty'], 'quantity_in_base_unit' => $line['qty'],
                    'unit_price' => $line['p']['price'], 'total_price' => $line['total'],
                    'discount' => 0, 'tax' => 0,
                    'created_at' => $date, 'updated_at' => $date,
                ]);

                if ($status !== 'cancelled') {
                    DB::table('stock_ledgers')->insert([
                        'product_id' => $line['p']['id'], 'variant_id' => null, 'warehouse_id' => $warehouse,
                        'ref_type' => 'sale', 'ref_id' => $saleId,
                        'quantity_change_in_base_unit' => $line['qty'], 'unit_cost' => $line['p']['price'],
                        'direction' => 'out', 'created_by' => $user,
                        'created_at' => $date, 'updated_at' => $date,
                    ]);
                }
            }

            if ($paid > 0) {
                DB::table('payments')->insert([
                    'entity_type' => 'customer', 'entity_id' => $customer,
                    'transaction_type' => 'in', 'ref_type' => 'sale', 'ref_id' => $saleId,
                    'amount' => $paid, 'payment_method' => ['cash', 'card', 'bank'][rand(0, 2)],
                    'note' => $isEcom ? 'Online order payment' : null, 'created_by' => $user,
                    'created_at' => $date, 'updated_at' => $date,
                ]);
            }

            if ($due > 0) {
                DB::table('customers')->where('id', $customer)->increment('balance', $due);
            }
        }
    }

    // ── expenses ────────────────────────────────────────────────────────────

    private function expenses(): void
    {
        $categories = ['Rent', 'Utilities', 'Salaries', 'Marketing', 'Logistics',
                       'Maintenance', 'Office Supplies', 'Bank Charges'];
        $ids = [];
        foreach ($categories as $name) {
            $ids[$name] = DB::table('expense_categories')->insertGetId([
                'name' => $name, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $ranges = [
            'Rent' => [45000, 90000], 'Utilities' => [3000, 9000], 'Salaries' => [60000, 140000],
            'Marketing' => [5000, 35000], 'Logistics' => [2000, 15000], 'Maintenance' => [1000, 8000],
            'Office Supplies' => [500, 4000], 'Bank Charges' => [200, 2500],
        ];

        for ($i = 0; $i < 90; $i++) {
            $cat = $categories[array_rand($categories)];
            $date = now()->subDays(rand(0, 240));

            DB::table('expenses')->insert([
                'branch_id' => $this->ids['branch'][array_rand(array_slice($this->ids['branch'], 0, 4))],
                'category_id' => $ids[$cat],
                'amount' => rand($ranges[$cat][0], $ranges[$cat][1]),
                'description' => $cat . ' — ' . $date->format('F Y'),
                'expense_date' => $date->toDateString(),
                'created_by' => $this->ids['allUsers'][array_rand($this->ids['allUsers'])],
                'created_at' => $date, 'updated_at' => $date,
            ]);
        }
    }

    // ── quotations ──────────────────────────────────────────────────────────

    private function quotations(): void
    {
        $products = $this->ids['product'];

        for ($i = 1; $i <= 18; $i++) {
            $date = now()->subDays(rand(1, 90));
            $customer = $this->ids['customer'][array_rand($this->ids['customer'])];

            $quotationId = DB::table('quotations')->insertGetId([
                'quotation_number' => 'QT-' . $date->format('Y') . '-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'customer_id' => $customer,
                'branch_id' => $this->ids['branch'][array_rand(array_slice($this->ids['branch'], 0, 4))],
                'quotation_date' => $date->toDateString(),
                'order_tax_amount' => 0, 'tax_percentage' => self::VAT,
                'discount_percentage' => 0, 'discount_type' => 'fixed',
                'shipping_cost' => 0, 'grand_total' => 0,
                // The DB enforces these five values with a CHECK constraint.
                'status' => $this->weighted(['pending' => 35, 'sent' => 25, 'accepted' => 20, 'rejected' => 10, 'converted' => 10]),
                'note' => 'Corporate enquiry — bulk pricing requested.',
                'created_at' => $date, 'updated_at' => $date,
            ]);

            $subtotal = 0;
            foreach ((array) array_rand($products, rand(2, 5)) as $idx) {
                $p = $products[$idx];
                $qty = rand(2, 15);
                $line = $p['price'] * $qty;
                $subtotal += $line;

                DB::table('quotation_items')->insert([
                    'quotation_id' => $quotationId, 'product_id' => $p['id'], 'product_variant_id' => null,
                    'unit_price' => $p['price'], 'quantity' => $qty,
                    'discount_amount' => 0, 'tax_amount' => 0, 'subtotal' => $line,
                    'created_at' => $date, 'updated_at' => $date,
                ]);
            }

            $tax = round($subtotal * self::VAT / 100, 2);
            DB::table('quotations')->where('id', $quotationId)->update([
                'order_tax_amount' => $tax,
                'grand_total' => round($subtotal + $tax, 2),
            ]);
        }
    }

    // ── cash registers ──────────────────────────────────────────────────────

    private function registers(): void
    {
        foreach (array_slice($this->ids['allUsers'], 0, 5) as $user) {
            for ($d = 1; $d <= 6; $d++) {
                $open = now()->subDays($d)->setTime(9, 0);
                $sales = rand(8000, 65000);
                $expense = rand(200, 2500);
                $closing = 2000 + $sales - $expense;

                DB::table('cash_registers')->insert([
                    'user_id' => $user,
                    'opened_at' => $open,
                    'closed_at' => $open->copy()->setTime(22, 0),
                    'opening_cash' => 2000,
                    'closing_cash' => $closing,
                    'total_sales' => $sales,
                    'total_expense' => $expense,
                    'cash_difference' => 0,
                    'created_at' => $open, 'updated_at' => $open,
                ]);
            }
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** Pick a key from [value => weight]. */
    private function weighted(array $weights): string
    {
        $roll = rand(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return (string) $value;
            }
        }

        return (string) array_key_first($weights);
    }

    private function summary(): void
    {
        foreach (['categories', 'products', 'product_variants', 'suppliers', 'customers',
                  'warehouses', 'branches', 'users', 'inventory_stocks', 'purchases',
                  'sales', 'sale_items', 'payments', 'expenses', 'quotations',
                  'stock_ledgers', 'cash_registers', 'discount_rules'] as $table) {
            $this->command->line(sprintf('  %-20s %d', $table, DB::table($table)->count()));
        }

        $this->command->newLine();
        $this->command->line('  Staff and storefront demo password: ' . self::DEMO_PASSWORD);
        $this->command->line('  Coupon codes: LEAP2026 (12%), WELCOME5 (5%)');
    }
}
