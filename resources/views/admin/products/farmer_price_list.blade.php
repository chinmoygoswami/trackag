@extends('admin.layout.layout')
@section('title', 'Farmer Product Price List | Trackag')

@section('content')
<main class="app-main">

    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Farmer Product Price List</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">State Wise Farmer Product Price</h3>
                </div>

                <form method="POST" action="{{ route('products.farmer-price.store') }}">
                    @csrf

                    <div class="card-body">

                        <div class="alert alert-info py-2">
                            These prices are used for farmer orders and are maintained separately from party product prices. Enter a farmer price before using a product packing in an order.
                        </div>

                        {{-- PRODUCT FILTER --}}
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Filter Product</label>
                                <select id="productFilter" class="form-select">
                                    <option value="">All Products</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">
                                            {{ $product->product_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height:600px;">
                            <table class="table table-bordered table-hover table-sm align-middle">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th rowspan="2">No</th>
                                        <th rowspan="2">Product</th>
                                        <th rowspan="2">Packing</th>

                                        @foreach($states as $state)
                                            <th colspan="2" class="text-center">
                                                {{ $state->name }}
                                            </th>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        @foreach($states as $state)
                                            <th class="text-center">Cash</th>
                                            <th class="text-center">Credit</th>
                                        @endforeach
                                    </tr>
                                </thead>

                                <tbody>
                                @php $sr = 1; @endphp

                                @foreach($products as $product)
                                    @foreach($product->packings as $packing)

                                    <tr class="product-row"
                                        data-product="{{ $product->id }}">

                                        <td>{{ $sr++ }}</td>

                                        <td>
                                            <strong>{{ $product->product_name }}</strong>
                                            <br>
                                            <small class="text-muted">
                                                {{ $product->technical_name }}
                                            </small>
                                        </td>

                                        <td>
                                            {{ $packing->packing_value }}
                                            {{ $packing->packing_size }}
                                        </td>

                                        <input type="hidden"
                                            name="product_id[{{ $packing->id }}]"
                                            value="{{ $product->id }}">

                                        @foreach($states as $state)

                                            @php
                                                $price = $packing->farmerPrices
                                                    ->where('state_id', $state->id)
                                                    ->first();

                                                // Product State
                                                $productState = $product->productStates
                                                    ->where('state_id', $state->id)
                                                    ->first();

                                                $productCredit = $productState && $productState->is_rpl == 1;
                                                 $productCash = $productState && $productState->is_ncr == 1;

                                                // Packing State
                                                $packingAllowed = $packing->packingStates
                                                    ->pluck('state_id')
                                                    ->contains($state->id);

                                                // Final
                                                $isCashAllowed = $productCash && $packingAllowed;
                                                $isCreditAllowed = $productCredit && $packingAllowed;
                                            @endphp

                                            {{-- CASH --}}
                                            <td>
                                                <input type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="form-control form-control-sm text-end no-arrow"
                                                    name="prices[{{ $packing->id }}][{{ $state->id }}][cash_price]"
                                                    value="{{ $price->cash_price ?? '' }}"
                                                    {{ $isCashAllowed ? '' : 'readonly disabled' }}
                                                    style="{{ $isCashAllowed ? '' : 'background:#f1f1f1;' }}">
                                            </td>

                                            {{-- CREDIT --}}
                                            <td>
                                                <input type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="form-control form-control-sm text-end no-arrow"
                                                    name="prices[{{ $packing->id }}][{{ $state->id }}][credit_price]"
                                                    value="{{ $price->credit_price ?? '' }}"
                                                    {{ $isCreditAllowed ? '' : 'readonly disabled' }}
                                                    style="{{ $isCreditAllowed ? '' : 'background:#f1f1f1;' }}">
                                            </td>

                                        @endforeach

                                    </tr>
                                    @endforeach
                                @endforeach

                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-primary">
                    Save Farmer Prices
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</main>
@endsection


@push('styles')
<style>
input.no-arrow::-webkit-outer-spin-button,
input.no-arrow::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input.no-arrow {
    -moz-appearance: textfield;
}
</style>
@endpush


@push('scripts')
<script>

$(document).ready(function(){

    // Prevent minus
    $(document).on('input', '.no-arrow', function () {
        if (this.value < 0) {
            this.value = 0;
        }
    });

    // Product filter
    $('#productFilter').on('change', function(){
        let productId = $(this).val();

        if(productId == ''){
            $('.product-row').show();
        } else {
            $('.product-row').hide();
            $('.product-row[data-product="'+productId+'"]').show();
        }
    });

});
</script>
@endpush
