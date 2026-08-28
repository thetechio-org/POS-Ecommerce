{{--
    One product tile, shared by the landing page and the shop grid.

    Expects: $product (decorated with stock_quantity, in_stock, final_price,
    has_discount), $cur (currency symbol), and optionally $badge as
    [css-class, label].
--}}
@php
    $badge = $badge ?? null;
    $price = $product->has_discount ? $product->final_price : ($product->actual_price ?? 0);

    // Demo ratings. Derived from the id so a product always shows the same score
    // rather than a new one on every render — these are not real reviews.
    $rating = 3.6 + (($product->id * 7) % 15) / 10;
    $rating = min(5.0, round($rating * 2) / 2);
    $reviews = 8 + (($product->id * 13) % 180);

    $off = ($product->has_discount && $product->actual_price > 0)
        ? round((($product->actual_price - $product->final_price) / $product->actual_price) * 100)
        : null;
@endphp

<div class="lx-p">
    <div class="lx-p-img">
        @if($off)
            <span class="lx-badge b-sale">-{{ $off }}%</span>
        @elseif($badge)
            <span class="lx-badge {{ $badge[0] }}">{{ $badge[1] }}</span>
        @endif

        <span class="lx-wish"><i class="far fa-heart"></i></span>

        @if(!$product->in_stock)
            <div class="lx-oos">Out of stock</div>
        @endif

        <a href="{{ route('store.product', $product->id) }}" class="d-block h-100">
            @if(!empty($product->product_img))
                <img src="{{ asset('storage/'.$product->product_img) }}" alt="{{ $product->name }}" loading="lazy">
            @else
                <div class="d-flex flex-column align-items-center justify-content-center h-100" style="gap:.4rem;">
                    <i class="fas fa-image" style="font-size:1.8rem; color:#cbd5e1;"></i>
                </div>
            @endif
        </a>
    </div>

    <div class="lx-p-body">
        <span class="lx-p-cat">{{ $product->brand ?? ($product->category->name ?? 'General') }}</span>

        <p class="lx-p-name">
            <a href="{{ route('store.product', $product->id) }}">{{ Str::limit($product->name, 42) }}</a>
        </p>

        <div class="lx-stars mb-2">
            @for($i = 1; $i <= 5; $i++)
                @if($rating >= $i)
                    <i class="fas fa-star"></i>
                @elseif($rating >= $i - 0.5)
                    <i class="fas fa-star-half-stroke"></i>
                @else
                    <i class="far fa-star"></i>
                @endif
            @endfor
            <span>({{ $reviews }})</span>
        </div>

        <div class="mt-auto">
            <div class="mb-3">
                <span class="lx-price">{{ $cur }} {{ number_format($price, 0) }}</span>
                @if($product->has_discount)
                    <span class="lx-price-was">{{ $cur }} {{ number_format($product->actual_price, 0) }}</span>
                @endif
            </div>

            @if(!$product->in_stock)
                <button class="lx-add" disabled>Out of stock</button>
            @elseif($product->has_variants)
                <a href="{{ route('store.product', $product->id) }}" class="lx-add">
                    <i class="fas fa-sliders"></i> Choose options
                </a>
            @else
                <form action="{{ route('cart.add') }}" method="POST">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" class="lx-add">
                        <i class="fas fa-cart-shopping"></i> Add to cart
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
