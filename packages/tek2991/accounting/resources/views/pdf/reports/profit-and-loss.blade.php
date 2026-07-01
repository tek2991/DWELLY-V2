@extends('accounting::pdf.reports.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th>Account Code</th>
                <th>Account Name</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="group-header">
                <td colspan="3">REVENUE</td>
            </tr>
            @foreach($data['revenue'] as $class => $items)
                <tr>
                    <td colspan="3" style="font-weight: bold; font-style: italic; padding-left: 15px;">{{ $class }}</td>
                </tr>
                @foreach($items as $row)
                    <tr>
                        <td style="padding-left: 30px;">{{ $row['account']->code }}</td>
                        <td>{{ $row['account']->name }}</td>
                        <td class="text-right">{{ $row['balance']->format() }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr class="summary-row">
                <td colspan="2" class="text-right">Total Revenue</td>
                <td class="text-right">{{ $data['totalRevenue']->format() }}</td>
            </tr>

            <tr class="group-header">
                <td colspan="3"><br>EXPENSES</td>
            </tr>
            @foreach($data['expenses'] as $class => $items)
                <tr>
                    <td colspan="3" style="font-weight: bold; font-style: italic; padding-left: 15px;">{{ $class }}</td>
                </tr>
                @foreach($items as $row)
                    <tr>
                        <td style="padding-left: 30px;">{{ $row['account']->code }}</td>
                        <td>{{ $row['account']->name }}</td>
                        <td class="text-right">{{ $row['balance']->format() }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr class="summary-row">
                <td colspan="2" class="text-right">Total Expenses</td>
                <td class="text-right">{{ $data['totalExpenses']->format() }}</td>
            </tr>

            <tr class="summary-row" style="border-top: 3px double #333;">
                <td colspan="2" class="text-right">NET INCOME</td>
                <td class="text-right">{{ $data['netIncome']->format() }}</td>
            </tr>
        </tbody>
    </table>
@endsection
