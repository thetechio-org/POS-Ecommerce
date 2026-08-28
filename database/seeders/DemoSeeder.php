<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

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
            'Mobile Phones'      => ['Smartphones', 'Tablets'],
            'Computers'          => ['Laptops', 'Desktops', 'Monitors'],
            'Audio'              => ['Headphones', 'Speakers'],
            'Wearables'          => ['Smartwatches', 'Fitness Trackers'],
            'Accessories'        => ['Chargers & Cables', 'Cases & Covers', 'Power Banks'],
            'Home Entertainment' => ['Televisions', 'Gaming'],
            'Cameras'            => ['Cameras', 'Drones'],
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
     * Prices are realistic Saudi retail in SAR. Each entry is
     * [name, brand, category, price, low-stock threshold, variant spec|null].
     */
    private function products(): void
    {
        $unit = DB::table('units')->where('name', 'Piece')->value('id')
             ?? DB::table('units')->orderBy('id')->value('id');

        DB::table('units')->where('id', $unit)->update(['conversion_factor' => 1]);

        $catalogue = [
            // Smartphones — the variant-heavy ones
            ['iPhone 16 Pro Max',            'Apple',    'Smartphones', 6299, 5, ['colors' => ['Natural Titanium', 'Black Titanium', 'Desert Titanium'], 'sizes' => ['256GB', '512GB', '1TB'], 'step' => 700]],
            ['iPhone 16 Pro',                'Apple',    'Smartphones', 5199, 5, ['colors' => ['Natural Titanium', 'Black Titanium'], 'sizes' => ['128GB', '256GB', '512GB'], 'step' => 600]],
            ['iPhone 16',                    'Apple',    'Smartphones', 3899, 6, ['colors' => ['Ultramarine', 'Teal', 'Black'], 'sizes' => ['128GB', '256GB'], 'step' => 500]],
            ['Samsung Galaxy S24 Ultra',     'Samsung',  'Smartphones', 5799, 5, ['colors' => ['Titanium Gray', 'Titanium Violet'], 'sizes' => ['256GB', '512GB'], 'step' => 650]],
            ['Samsung Galaxy S24+',          'Samsung',  'Smartphones', 4299, 5, ['colors' => ['Onyx Black', 'Marble Gray'], 'sizes' => ['256GB', '512GB'], 'step' => 550]],
            ['Samsung Galaxy Z Fold 6',      'Samsung',  'Smartphones', 7499, 3, null],
            ['Samsung Galaxy A55',           'Samsung',  'Smartphones', 1499, 10, ['colors' => ['Awesome Navy', 'Awesome Lilac'], 'sizes' => ['128GB', '256GB'], 'step' => 250]],
            ['Google Pixel 9 Pro',           'Google',   'Smartphones', 4599, 4, null],
            ['Xiaomi 14 Ultra',              'Xiaomi',   'Smartphones', 3999, 5, null],
            ['Xiaomi Redmi Note 13 Pro',     'Xiaomi',   'Smartphones',  999, 15, ['colors' => ['Midnight Black', 'Aurora Purple'], 'sizes' => ['128GB', '256GB'], 'step' => 200]],
            ['Honor Magic 6 Pro',            'Honor',    'Smartphones', 3299, 5, null],
            ['Nothing Phone (2a)',           'Nothing',  'Smartphones', 1199, 8, null],

            // Tablets
            ['iPad Pro 13" M4',              'Apple',    'Tablets',     5499, 4, ['colors' => ['Space Black', 'Silver'], 'sizes' => ['256GB', '512GB', '1TB'], 'step' => 900]],
            ['iPad Air 11" M2',              'Apple',    'Tablets',     2899, 6, ['colors' => ['Blue', 'Starlight'], 'sizes' => ['128GB', '256GB'], 'step' => 500]],
            ['iPad 10th Gen',                'Apple',    'Tablets',     1699, 8, null],
            ['Samsung Galaxy Tab S9',        'Samsung',  'Tablets',     3299, 5, null],
            ['Lenovo Tab P12',               'Lenovo',   'Tablets',     1099, 8, null],

            // Laptops
            ['MacBook Pro 16" M4 Pro',       'Apple',    'Laptops',    11999, 3, ['colors' => ['Space Black', 'Silver'], 'sizes' => ['512GB', '1TB'], 'step' => 1500]],
            ['MacBook Pro 14" M4',           'Apple',    'Laptops',     8299, 3, null],
            ['MacBook Air 15" M3',           'Apple',    'Laptops',     5799, 5, ['colors' => ['Midnight', 'Starlight', 'Space Gray'], 'sizes' => ['256GB', '512GB'], 'step' => 800]],
            ['Dell XPS 15',                  'Dell',     'Laptops',     7499, 4, null],
            ['Dell Inspiron 15',             'Dell',     'Laptops',     2299, 8, null],
            ['HP Spectre x360 14',           'HP',       'Laptops',     6299, 4, null],
            ['HP Pavilion 15',               'HP',       'Laptops',     2099, 10, null],
            ['Lenovo ThinkPad X1 Carbon',    'Lenovo',   'Laptops',     7899, 3, null],
            ['Lenovo IdeaPad Slim 5',        'Lenovo',   'Laptops',     2499, 8, null],
            ['ASUS ROG Zephyrus G14',        'ASUS',     'Laptops',     6999, 4, null],
            ['ASUS Vivobook 16',             'ASUS',     'Laptops',     2199, 9, null],
            ['Microsoft Surface Laptop 7',   'Microsoft','Laptops',     4999, 4, null],

            // Desktops & Monitors
            ['iMac 24" M4',                  'Apple',    'Desktops',    5999, 3, null],
            ['Mac mini M4',                  'Apple',    'Desktops',    2599, 5, null],
            ['HP EliteDesk 800 G9',          'HP',       'Desktops',    3799, 4, null],
            ['Dell OptiPlex 7010',           'Dell',     'Desktops',    2899, 5, null],
            ['LG UltraFine 27" 4K',          'LG',       'Monitors',    2699, 5, null],
            ['Samsung Odyssey G7 32"',       'Samsung',  'Monitors',    2399, 5, null],
            ['Dell UltraSharp U2723QE',      'Dell',     'Monitors',    2199, 6, null],
            ['BenQ PD2705U 27"',             'BenQ',     'Monitors',    1899, 6, null],

            // Audio
            ['AirPods Pro 2 (USB-C)',        'Apple',    'Headphones',   999, 20, null],
            ['AirPods Max',                  'Apple',    'Headphones',  2299, 6, ['colors' => ['Midnight', 'Starlight', 'Blue'], 'sizes' => null, 'step' => 0]],
            ['Sony WH-1000XM5',              'Sony',     'Headphones',  1599, 10, ['colors' => ['Black', 'Silver'], 'sizes' => null, 'step' => 0]],
            ['Bose QuietComfort Ultra',      'Bose',     'Headphones',  1699, 8, null],
            ['Samsung Galaxy Buds3 Pro',     'Samsung',  'Headphones',   799, 15, null],
            ['JBL Tune 770NC',               'JBL',      'Headphones',   449, 20, null],
            ['Sonos Era 300',                'Sonos',    'Speakers',    1899, 5, null],
            ['JBL Charge 5',                 'JBL',      'Speakers',     649, 15, null],
            ['Bose SoundLink Flex',          'Bose',     'Speakers',     599, 12, null],
            ['Marshall Emberton III',        'Marshall', 'Speakers',     749, 10, null],

            // Wearables
            ['Apple Watch Ultra 2',          'Apple',    'Smartwatches', 3399, 5, null],
            ['Apple Watch Series 10',        'Apple',    'Smartwatches', 1799, 10, ['colors' => ['Jet Black', 'Rose Gold', 'Silver'], 'sizes' => ['42mm', '46mm'], 'step' => 200]],
            ['Samsung Galaxy Watch 7',       'Samsung',  'Smartwatches', 1299, 10, null],
            ['Garmin Fenix 8',               'Garmin',   'Smartwatches', 4199, 3, null],
            ['Huawei Watch GT 5 Pro',        'Huawei',   'Smartwatches', 1499, 8, null],
            ['Fitbit Charge 6',              'Fitbit',   'Fitness Trackers', 599, 15, null],
            ['Xiaomi Smart Band 9',          'Xiaomi',   'Fitness Trackers', 199, 30, null],
            ['Whoop 4.0',                    'Whoop',    'Fitness Trackers', 999, 8, null],

            // Accessories
            ['Anker 140W GaN Charger',       'Anker',    'Chargers & Cables', 299, 30, null],
            ['Apple 20W USB-C Adapter',      'Apple',    'Chargers & Cables',  89, 50, null],
            ['Belkin BoostCharge 3-in-1',    'Belkin',   'Chargers & Cables', 449, 20, null],
            ['USB-C to Lightning 2m',        'Apple',    'Chargers & Cables',  109, 60, null],
            ['Spigen Ultra Hybrid Case',     'Spigen',   'Cases & Covers',      99, 80, ['colors' => ['Clear', 'Matte Black'], 'sizes' => null, 'step' => 0]],
            ['OtterBox Defender Series',     'OtterBox', 'Cases & Covers',     229, 25, null],
            ['Apple Silicone Case',          'Apple',    'Cases & Covers',     189, 35, null],
            ['Anker 737 PowerCore 24K',      'Anker',    'Power Banks',        549, 15, null],
            ['Anker MagGo 10K',              'Anker',    'Power Banks',        329, 25, null],
            ['Xiaomi 20000mAh Power Bank',   'Xiaomi',   'Power Banks',        149, 40, null],

            // Home entertainment
            ['Samsung 65" Neo QLED QN90D',   'Samsung',  'Televisions',  8999, 3, null],
            ['LG 55" OLED evo C4',           'LG',       'Televisions',  6499, 3, null],
            ['Samsung 43" Crystal UHD',      'Samsung',  'Televisions',  1899, 6, null],
            ['TCL 75" QLED C755',            'TCL',      'Televisions',  5299, 3, null],
            ['PlayStation 5 Slim',           'Sony',     'Gaming',       2199, 8, null],
            ['Xbox Series X',                'Microsoft','Gaming',       2099, 6, null],
            ['Nintendo Switch OLED',         'Nintendo', 'Gaming',       1499, 10, null],
            ['DualSense Edge Controller',    'Sony',     'Gaming',        899, 12, null],

            // Cameras
            ['Sony Alpha A7 IV',             'Sony',     'Cameras',      9999, 2, null],
            ['Canon EOS R6 Mark II',         'Canon',    'Cameras',      9499, 2, null],
            ['GoPro HERO13 Black',           'GoPro',    'Cameras',      1899, 8, null],
            ['DJI Osmo Pocket 3',            'DJI',      'Cameras',      1749, 8, null],
            ['DJI Mini 4 Pro',               'DJI',      'Drones',       3299, 4, null],
            ['DJI Air 3S',                   'DJI',      'Drones',       4999, 3, null],
        ];

        $n = 1;
        foreach ($catalogue as [$name, $brand, $category, $price, $lowStock, $variantSpec]) {
            $sku = 'SEL-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);

            $productId = DB::table('products')->insertGetId([
                'name' => $name,
                'category_id' => $this->ids['cat'][$category],
                'base_unit_id' => $unit,
                'default_display_unit_id' => $unit,
                'has_variants' => $variantSpec !== null,
                'sku' => $sku,
                'barcode' => '628' . str_pad((string) (100000 + $n * 7), 10, '0', STR_PAD_LEFT),
                'brand' => $brand,
                'track_expiry' => false,
                'tax_rate' => self::VAT,
                'actual_price' => $price,
                'low_stock' => $lowStock,
                'created_at' => now()->subMonths(rand(3, 10)),
                'updated_at' => now(),
            ]);

            $this->ids['product'][] = ['id' => $productId, 'price' => $price, 'name' => $name];

            // A supplier or two per product
            foreach ((array) array_rand(array_flip($this->ids['supplier']), 2) as $supplierId) {
                DB::table('product_supplier')->insert([
                    'product_id' => $productId, 'supplier_id' => $supplierId,
                ]);
            }

            if ($variantSpec) {
                $this->variantsFor($productId, $sku, $price, $variantSpec, $lowStock);
            }

            $n++;
        }
    }

    private function variantsFor(int $productId, string $sku, float $base, array $spec, int $lowStock): void
    {
        $colors = $spec['colors'] ?? [null];
        $sizes  = $spec['sizes']  ?? [null];
        $step   = $spec['step']   ?? 0;
        $v = 1;

        foreach ($colors as $color) {
            foreach ($sizes as $i => $size) {
                $price = $base + ($step * ($size === null ? 0 : $i));
                $label = trim(($color ?? '') . ($color && $size ? ' / ' : '') . ($size ?? ''));

                $variantId = DB::table('product_variants')->insertGetId([
                    'product_id' => $productId,
                    'color' => $color,
                    'size' => $size,
                    'variant_name' => $label,
                    'sku' => $sku . '-V' . $v,
                    'barcode' => '629' . str_pad((string) ($productId * 100 + $v), 10, '0', STR_PAD_LEFT),
                    'actual_price' => $price,
                    'low_stock' => $lowStock,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                $this->ids['variant'][] = ['id' => $variantId, 'product_id' => $productId, 'price' => $price];
                $v++;
            }
        }
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
