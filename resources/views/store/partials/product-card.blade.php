{{--
    One product tile, shared by the landing page and the shop grid.

    Expects: $product (decorated with stock_quantity, in_stock, final_price,
    has_discount), $cur (currency symbol), and optionally $badge as
    [css-class, label].
--}}
@php
    $badge = $badge ?? null;
    $price = $product->has_discount ? $product->final_price : ($product->actual_price ?? 0);
@endphp

<div class="lx-p">
    <div class="lx-p-img">
        @if($badge)
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
        <span class="lx-p-cat">{{ $product->category->name ?? 'General' }}</span>

        <p class="lx-p-name">
            <a href="{{ route('store.product', $product->id) }}">{{ Str::limit($product->name, 42) }}</a>
        </p>

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
