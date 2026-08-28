@extends('layouts.frontend.app')

@section('frontend_css')
<style>
    .sidebar-box {
        background: #fff;
        border: 1px solid var(--clr-border);
        border-radius: var(--radius-card);
        padding: 1.25rem;
    }
    .sidebar-label {
        font-size: .72rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .8px; color: #94a3b8; margin-bottom: .75rem;
    }
    .search-wrap .form-control {
        border: 1.5px solid var(--clr-border);
        border-radius: 10px 0 0 10px;
        padding: 12px 16px; font-size: .875rem;
    }
    .search-wrap .form-control:focus {
        border-color: var(--clr-primary);
        box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    }
    .search-wrap .btn-search {
        background: var(--clr-primary); color: #fff;
        border: none; border-radius: 0 10px 10px 0;
        padding: 12px 20px; font-size: .875rem; font-weight: 600;
    }
</style>
@endsection

@section('frontend_content')
@php
    use App\Models\Setting;
    $setting = Setting::first();
@endphp

{{-- Page Header --}}
<div class="page-header-band">
    <div class="container text-center">
        <h1>Shop</h1>
        <nav>
            <ol class="breadcrumb justify-content-center mb-0">
                <li class="breadcrumb-item"><a href="{{ route('store.landing') }}">Home</a></li>
                <li class="breadcrumb-item active">Shop</li>
            </ol>
        </nav>
    </div>
</div>

{{-- Shop Body --}}
<section class="py-5">
    <div class="container">

        {{-- Search --}}
        <form method="GET" action="{{ route('store.shop') }}" class="mb-4">
            @if($categoryId)
                <input type="hidden" name="category" value="{{ $categoryId }}">
            @endif
            <div class="row g-2 align-items-center">
                <div class="col search-wrap">
                    <div class="input-group">
                        <input type="search" name="q" class="form-control"
                               placeholder="Search products by name, SKU or barcode..."
                               value="{{ $search ?? '' }}">
                        <button type="submit" class="btn-search px-4">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                @if($search)
                <div class="col-auto d-flex align-items-center gap-2">
                    <a href="{{ route('store.shop', $categoryId ? ['category' => $categoryId] : []) }}"
                       class="btn-outline" style="padding:10px 16px; text-decoration:none; font-size:.82rem;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                    <span style="font-size:.82rem; color:#94a3b8;">
                        {{ $products->total() }} results for &ldquo;{{ $search }}&rdquo;
                    </span>
                </div>
                @endif
            </div>
        </form>

        <div class="row g-4">

            {{-- Sidebar --}}
            <div class="col-lg-3">
                <div class="sidebar-box">
                    <div class="sidebar-label">Categories</div>
                    <a href="{{ route('store.shop') }}"
                       class="cat-link {{ !$categoryId ? 'active' : '' }}">
                        <span><i class="fas fa-th-large me-2" style="font-size:.8rem;"></i> All Products</span>
                        <span class="cat-count">All</span>
                    </a>
                    @foreach($categories as $cat)
                    <a href="{{ route('store.shop', ['category' => $cat->id]) }}"
                       class="cat-link {{ $categoryId == $cat->id ? 'active' : '' }}">
                        <span><i class="fas fa-tag me-2" style="font-size:.75rem;"></i> {{ $cat->name }}</span>
                        <span class="cat-count">{{ $cat->products_count }}</span>
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- Products --}}
            <div class="col-lg-9">
                @if($products->isEmpty())
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x mb-3 d-block" style="color:#cbd5e1;"></i>
                        <h5 style="color:#475569;">No products found</h5>
                        <p style="color:#94a3b8; font-size:.875rem;">Try a different search term or browse all categories.</p>
                    </div>
                @else
                    <div class="row g-3">
                        @foreach($products as $product)
                        <div class="col-6 col-xl-4">
                            @include('store.partials.product-card', [
                                'product' => $product,
                                'cur' => $setting->currency_symbol ?? 'SAR',
                                'badge' => $product->has_discount ? ['b-sale', 'Sale'] : null,
                            ])
                        </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-center mt-5">
                        {{ $products->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

@endsection

@section('frontend_js')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @foreach($products as $product)
        (function() {
            var el = document.getElementById('final_price_{{ $product->id }}');
            if (el) el.value = '{{ $product->has_discount ? $product->final_price : ($product->actual_price ?? 0) }}';
        })();
        @endforeach
    });
</script>
@endsection
