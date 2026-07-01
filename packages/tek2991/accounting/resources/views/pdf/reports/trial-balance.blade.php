@extends('accounting::pdf.reports.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th>Account Code</th>
                <th>Account Name</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['rows'] as $row)
                <tr>
                    <td>{{ $row['account']->code }}</td>
                    <td>{{ $row['account']->name }}</td>
                    <td class="text-right">{{ $row['debit']->getAmount() > 0 ? $row['debit']->format() : '-' }}</td>
                    <td class="text-right">{{ $row['credit']->getAmount() > 0 ? $row['credit']->format() : '-' }}</td>
                </tr>
            @endforeach
            <tr class="summary-row" style="border-top: 3px double #333;">
                <td colspan="2" class="text-right">Totals</td>
                <td class="text-right">{{ $data['totalDebit']->format() }}</td>
                <td class="text-right">{{ $data['totalCredit']->format() }}</td>
            </tr>
        </tbody>
    </table>
@endsection
