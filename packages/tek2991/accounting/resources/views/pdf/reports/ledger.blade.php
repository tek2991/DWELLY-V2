@extends('accounting::pdf.reports.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th class="text-center">Ref</th>
                <th class="text-center">Description</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['rows'] as $row)
                @if($row['is_summary'] ?? false)
                    <tr class="summary-row">
                        <td colspan="3" class="text-right">{{ $row['description'] }}</td>
                        <td class="text-right">{{ isset($row['debit']) ? $row['debit']->format() : '' }}</td>
                        <td class="text-right">{{ isset($row['credit']) ? $row['credit']->format() : '' }}</td>
                        <td class="text-right">{{ $row['balance']->format() }}</td>
                    </tr>
                @else
                    <tr>
                        <td style="white-space: nowrap;">{{ $row['date'] }}</td>
                        <td class="text-center font-bold">{{ $row['ref'] }}</td>
                        <td class="text-center">{{ $row['description'] }}</td>
                        <td class="text-right">{{ isset($row['debit']) && $row['debit']->getAmount() > 0 ? $row['debit']->format() : '' }}</td>
                        <td class="text-right">{{ isset($row['credit']) && $row['credit']->getAmount() > 0 ? $row['credit']->format() : '' }}</td>
                        <td class="text-right">{{ $row['balance']->format() }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
@endsection
