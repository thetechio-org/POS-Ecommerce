@extends('layouts.frontend.app')

@php
    use App\Models\Setting;
    $setting = Setting::first();
    $cur = $setting->currency_symbol ?? 'SAR';
@endphp


@section('frontend_content')

{{-- ─── Hero ─── --}}
<section class="lx-hero">
    <div class="container lx-hero-inner">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="lx-rating">
                    <span class="stars">★★★★★</span>
                    <span>{{ 400 + ($products->count() * 4) }} reviews from customers across the Kingdom</span>
                </div>

                <h1 class="lx-h1">Powering Your<br>Digital Lifestyle</h1>

                <p class="lx-lead">
                    Discover the latest smartphones, laptops, audio and smart home devices —
                    genuine stock, fast delivery across Saudi Arabia, and secure payment.
                </p>

                <div class="d-flex gap-3 flex-wrap">
                    <a href="{{ route('store.shop') }}" class="lx-cta">
                        Explore Products <i class="fas fa-arrow-right"></i>
                    </a>
                    <a href="{{ route('store.shop') }}" class="lx-cta-ghost">Browse Categories</a>
                </div>
            </div>

            <div class="col-lg-6 d-none d-lg-block">
                <div class="lx-stack">
                    @foreach($products->take(4) as $p)
                        <div class="lx-tile">
                            @if($p->product_img)
                                <img src="{{ asset('storage/'.$p->product_img) }}" alt="{{ $p->name }}">
                            @endif
                            <div class="n">{{ Str::limit($p->name, 22) }}</div>
                            <div class="p">{{ $cur }} {{ number_format($p->final_price ?? $p->actual_price, 0) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─── Brands we stock ─── --}}
@if($brands->count())
<section class="lx-brands">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-4">
            @foreach($brands as $brand)
                <span class="lx-brand">{{ strtoupper($brand) }}</span>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ─── Categories ─── --}}
@if($showcaseCategories->count())
<section class="py-5">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="lx-head">Most Popular <span>Categories</span></h2>
            <p class="lx-sub mb-0">Everything we stock, grouped the way you shop</p>
        </div>

        <div class="row g-3">
            @foreach($showcaseCategories as $category)
            <div class="col-6 col-md-4 col-lg-3">
                <a href="{{ route('store.shop', ['category' => $category->id]) }}" class="lx-cat">
                    @if($category->cover)
                        <img src="{{ asset('storage/'.$category->cover) }}" alt="{{ $category->name }}">
                    @endif
                    <div class="lx-cat-label">
                        {{ $category->name }}
                        <span class="lx-cat-count">{{ $category->products_count }} products</span>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ─── Best sellers ─── --}}
@if($bestSellers->count())
<section class="pb-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
            <div>
                <h2 class="lx-head mb-1">Best Seller <span>Products</span></h2>
                <p class="lx-sub mb-0">What customers are buying most this season</p>
            </div>
            <a href="{{ route('store.shop') }}" class="lx-link">View all products <i class="fas fa-arrow-right ms-1"></i></a>
        </div>

        <div class="row g-3">
            @foreach($bestSellers as $i => $product)
                <div class="col-6 col-lg-3">
                    @include('store.partials.product-card', [
                        'product' => $product,
                        'cur' => $cur,
                        'badge' => $i === 0 ? ['b-best', 'Best'] : ($i < 3 ? ['b-trend', 'Trending'] : null),
                    ])
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ─── Promo ─── --}}
<section class="pb-5">
    <div class="container">
        <div class="lx-promo">
            <div class="lx-promo-ring"></div>
            <div style="position:relative; z-index:2;">
                <div class="lx-promo-eyebrow">A new breed of {{ $setting->business_name ?? 'our store' }}</div>
                <h3>Elevate your lifestyle with premium essentials.</h3>
                <a href="{{ route('store.shop') }}" class="lx-cta" style="background:#fff; color:#0f172a;">
                    Explore All Products <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ─── New arrivals, filterable ─── --}}
<section class="pb-5">
    <div class="container">
        <div class="text-center mb-3">
            <h2 class="lx-head">New Arrival <span>Products</span></h2>
            <p class="lx-sub mb-0">Just landed in our warehouses</p>
        </div>

        <div class="lx-tabs" id="lxTabs">
            <button class="lx-tab active" data-filter="all">All Products</button>
            @foreach($products->pluck('category.name')->filter()->unique()->take(4) as $name)
                <button class="lx-tab" data-filter="{{ Str::slug($name) }}">{{ $name }}</button>
            @endforeach
        </div>

        <div class="row g-3" id="lxGrid">
            @forelse($products as $i => $product)
                <div class="col-6 col-lg-3 lx-item" data-cat="{{ Str::slug($product->category->name ?? '') }}">
                    @include('store.partials.product-card', [
                        'product' => $product,
                        'cur' => $cur,
                        'badge' => $product->has_discount ? ['b-sale', 'Sale'] : ($i < 2 ? ['b-new', 'New'] : null),
                    ])
                </div>
            @empty
                <div class="col-12 text-center py-5">
                    <i class="fas fa-box-open fa-3x mb-3 d-block" style="color:#cbd5e1;"></i>
                    <p style="color:#94a3b8;">No products yet — add some from the admin panel.</p>
                </div>
            @endforelse
        </div>
    </div>
</section>

{{-- ─── Deal of the week ─── --}}
@if($dealProducts->count())
<section class="pb-5">
    <div class="container">
        <div class="lx-deal">
            <div class="row align-items-center g-4">
                <div class="col-lg-4">
                    <div class="lx-deal-eyebrow">Limited time</div>
                    <h3 class="lx-deal-title">Deal of the Week</h3>
                    <p class="lx-deal-text">
                        Selected accessories and audio at a reduced price. Offer ends when the clock does.
                    </p>
                    <div class="lx-clock" id="lxClock" data-ends="{{ $dealEndsAt }}">
                        <div><b id="cd-d">00</b><span>Days</span></div>
                        <div><b id="cd-h">00</b><span>Hrs</span></div>
                        <div><b id="cd-m">00</b><span>Min</span></div>
                        <div><b id="cd-s">00</b><span>Sec</span></div>
                    </div>
                    <a href="{{ route('store.shop') }}" class="lx-cta mt-4">Shop the deal <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="col-lg-8">
                    <div class="row g-3">
                        @foreach($dealProducts as $product)
                            <div class="col-6 col-md-4">
                                @include('store.partials.product-card', ['product' => $product, 'cur' => $cur])
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ─── Testimonials ─── --}}
<section class="pb-5">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="lx-head">What Our <span>Customers Say</span></h2>
            <p class="lx-sub mb-0">From shoppers across the Kingdom</p>
        </div>
        <div class="row g-3">
            @foreach([
                ['Abdulaziz Al-Otaibi', 'Riyadh',   'Ordered on Sunday, delivered to Olaya on Monday morning. The phone was sealed and genuine — exactly as listed.'],
                ['Reem Al-Ghamdi',      'Jeddah',   'I compared prices across three stores. Sellora was the best, and the warranty was registered before I left the counter.'],
                ['Sultan Al-Dosari',    'Al Khobar','Bought a laptop for the office. Invoice with VAT arrived by email straight away, which made expensing it painless.'],
            ] as $t)
            <div class="col-md-4">
                <div class="lx-quote">
                    <div class="lx-stars mb-2">
                        @for($i = 0; $i < 5; $i++)<i class="fas fa-star"></i>@endfor
                    </div>
                    <p>{{ $t[2] }}</p>
                    <div class="lx-quote-by">
                        <span class="lx-avatar">{{ substr($t[0], 0, 1) }}</span>
                        <div>
                            <b>{{ $t[0] }}</b>
                            <small>{{ $t[1] }}</small>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ─── Trust row ─── --}}
<section class="pb-5">
    <div class="container">
        <div class="row g-3">
            @foreach([
                ['fas fa-truck-fast', 'Free shipping', 'On every order across the Kingdom'],
                ['fas fa-rotate-left', 'Money-back guarantee', 'Refunded in full, no questions'],
                ['fas fa-shield-halved', 'Easy 30-day returns', 'Genuine stock, warranty included'],
            ] as $t)
            <div class="col-md-4">
                <div class="lx-trust">
                    <i class="{{ $t[0] }}"></i>
                    <div>
                        <b>{{ $t[1] }}</b>
                        <span>{{ $t[2] }}</span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

@endsection

@section('frontend_js')
<script>
    // Deal countdown
    document.addEventListener('DOMContentLoaded', function () {
        const clock = document.getElementById('lxClock');
        if (clock) {
            const ends = new Date(clock.dataset.ends).getTime();
            const pad = n => String(Math.max(0, n)).padStart(2, '0');

            const tick = () => {
                const left = ends - Date.now();
                if (left <= 0) { return; }
                document.getElementById('cd-d').textContent = pad(Math.floor(left / 86400000));
                document.getElementById('cd-h').textContent = pad(Math.floor(left / 3600000) % 24);
                document.getElementById('cd-m').textContent = pad(Math.floor(left / 60000) % 60);
                document.getElementById('cd-s').textContent = pad(Math.floor(left / 1000) % 60);
            };
            tick();
            setInterval(tick, 1000);
        }
    });

    // New-arrivals category filter
    document.addEventListener('DOMContentLoaded', function () {
        const tabs  = document.querySelectorAll('#lxTabs .lx-tab');
        const items = document.querySelectorAll('#lxGrid .lx-item');

        tabs.forEach(tab => tab.addEventListener('click', function () {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            const filter = this.dataset.filter;
            items.forEach(item => {
                item.style.display = (filter === 'all' || item.dataset.cat === filter) ? '' : 'none';
            });
        }));
    });
</script>
@endsection
