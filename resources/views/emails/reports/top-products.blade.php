<!DOCTYPE html>
<html>
<head>
    <title>Top Products Report</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
<h1 style="color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
    Top Products Report
</h1>

<p>
    <strong>Tenant:</strong> {{ $tenantName }}<br>
    <strong>Period:</strong> {{ $startDate }} to {{ $endDate }}
</p>

@if(count($topProducts) > 0)
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0; border: 1px solid #ddd;">
        <thead>
        <tr style="background-color: #f8f9fa;">
            <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: bold;">Product</th>
            <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: bold;">SKU</th>
            <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: bold;">Quantity Sold</th>
            <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: bold;">Revenue</th>
            <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: bold;">Avg Price</th>
        </tr>
        </thead>
        <tbody>
        @foreach($topProducts as $product)
            <tr style="background-color: {{ $loop->even ? '#f9f9f9' : '#ffffff' }};">
                <td style="padding: 10px; border: 1px solid #ddd;">{{ $product->name }}</td>
                <td style="padding: 10px; border: 1px solid #ddd;">{{ $product->sku }}</td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">{{ number_format($product->total_quantity) }}</td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">${{ number_format($product->total_revenue, 2) }}</td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">${{ number_format($product->average_price, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p style="margin-top: 20px;">
        <strong>Total Revenue:</strong> ${{ number_format($topProducts->sum('total_revenue'), 2) }}<br>
        <strong>Total Quantity Sold:</strong> {{ number_format($topProducts->sum('total_quantity')) }}
    </p>
@else
    <p style="color: #666; font-style: italic;">
        No product sales data available for this period.
    </p>
@endif

<p style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee;">
    Thanks,<br>
    <strong>{{ config('app.name') }}</strong>
</p>
</body>
</html>