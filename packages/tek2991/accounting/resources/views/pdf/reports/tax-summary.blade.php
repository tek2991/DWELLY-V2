@extends('accounting::pdf.reports.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th>Tax Group / Component</th>
                <th class="text-right">Output Tax (Collected)</th>
                <th class="text-right">Input Tax (Paid)</th>
                <th class="text-right">Net Payable</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['rows'] as $row)
                <tr class="group-header">
                    <td>{{ $row['tax']->name }}</td>
                    <td class="text-right">{{ $row['output']->format() }}</td>
                    <td class="text-right">{{ $row['input']->format() }}</td>
                    <td class="text-right">{{ $row['payable']->format() }}</td>
                </tr>
                @foreach($row['components'] as $component)
                    <tr>
                        <td style="padding-left: 20px;">- {{ $component['name'] }}</td>
                        <td class="text-right">{{ $component['output']->format() }}</td>
                        <td class="text-right">{{ $component['input']->format() }}</td>
                        <td class="text-right">{{ $component['payable']->format() }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="4" class="text-center">No tax transactions found for this period.</td>
                </tr>
            @endforelse
            <tr class="summary-row" style="border-top: 3px double #333;">
                <td class="text-right">Total Tax Payable</td>
                <td class="text-right">{{ $data['totalOutput']->format() }}</td>
                <td class="text-right">{{ $data['totalInput']->format() }}</td>
                <td class="text-right">{{ $data['totalPayable']->format() }}</td>
            </tr>
        </tbody>
    </table>
@endsection
