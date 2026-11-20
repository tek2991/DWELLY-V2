<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $property->code ?: 'Dwelly' }} Brochure</title>
    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
            color: #4f5047;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
        }

        .center-align {
            text-align: center;
        }

        .main-image {
            width: 100%;
            height: 260px;
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
            border: 2px solid #ccc;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .sub-images-row {
            margin-bottom: 20px;
        }

        .small-image {
            width: 32%;
            height: 120px;
            display: inline-block;
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-right: 1%;
        }

        .page-break {
            page-break-before: always;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 15px;
            font-size: 12px;
        }

        td, th {
            border: 1px solid #dddddd;
            text-align: left;
            padding: 8px;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        footer {
            position: fixed;
            bottom: 0px;
            left: 0px;
            right: 0px;
            height: 30px;
            font-size: 10px;
            text-align: right;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }

        .header-title {
            color: #4c4d47;
            margin-bottom: 5px;
        }

        .rent-badge {
            font-size: 18px;
            color: #bfa305;
            font-weight: bold;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div>
        <h2 class="center-align header-title">
            {{ ($property->bhkType?->name ?? 'BHK') . ' • ' . ($property->propertyType?->name ?? 'Apartment') }}
        </h2>
        <div class="center-align" style="font-size: 14px; margin-bottom: 15px;">
            {{ $property->building_name ?: 'Residential Property' }} — {{ $property->localityRef?->name ?? ($property->locality ?: 'Guwahati') }}
        </div>
    </div>

    <!-- Main Image -->
    @if ($coverImage)
        <div style="background-image: url('{{ $coverImage }}');" class="main-image"></div>
    @endif

    <!-- Sub images -->
    @if (!empty($images))
        <div class="sub-images-row">
            @foreach ($images as $img)
                <div style="background-image: url('{{ $img }}');" class="small-image"></div>
            @endforeach
        </div>
    @endif

    <!-- Rent -->
    <div class="rent-badge">
        Monthly Rent: INR {{ number_format($property->rent) }}/-
    </div>

    <!-- Property Details Table -->
    <table>
        <tr>
            <th>Specification</th>
            <th>Detail</th>
            <th>Specification</th>
            <th>Detail</th>
        </tr>
        <tr>
            <td>Bedroom</td>
            <td>{{ $property->getNoOfRooms('Bedroom') ?: '1+' }}</td>
            <td>Bathroom</td>
            <td>{{ $property->getNoOfRooms('Bathroom') ?: '1+' }}</td>
        </tr>
        <tr>
            <td>Floor</td>
            <td>{{ $property->floor ?: '1' }} of {{ $property->total_floors ?: '4' }}</td>
            <td>Floor Space</td>
            <td>{{ $property->floor_space_sqft ? $property->floor_space_sqft . ' sqft' : '-' }}</td>
        </tr>
        <tr>
            <td>Furnishing</td>
            <td>{{ $property->furnishingType?->name ?? 'Standard' }}</td>
            <td>Flooring</td>
            <td>{{ $property->flooringType?->name ?? 'Vitrified' }}</td>
        </tr>
        <tr>
            <td>Monthly Rent</td>
            <td>INR {{ number_format($property->rent) }}</td>
            <td>Security Deposit</td>
            <td>INR {{ number_format($property->security_deposit) }}</td>
        </tr>
        <tr>
            <td>Society Fee</td>
            <td>INR {{ number_format($property->society_fee) }}</td>
            <td>Booking Amount</td>
            <td>INR {{ number_format($property->booking_amount) }}</td>
        </tr>
    </table>

    <footer>
        Dwelly Property Management • Printed on: {{ date('Y-m-d H:i:s') }}
    </footer>

    <!-- Page Break -->
    <div class="page-break"></div>

    <!-- Page 2: Amenities and Nearby -->
    <h3 style="margin-top: 20px;">Amenities & Features</h3>
    <table>
        <tr>
            <th>Amenity</th>
            <th>Available</th>
            <th>Amenity</th>
            <th>Available</th>
        </tr>
        <tr>
            <td>Car Parking</td>
            <td>{{ $property->checkPropertyAmenity('Parking') ? 'Yes' : 'No' }}</td>
            <td>Lift</td>
            <td>{{ $property->checkPropertyAmenity('Lift') ? 'Yes' : 'No' }}</td>
        </tr>
        <tr>
            <td>Security</td>
            <td>{{ $property->checkPropertyAmenity('Security') ? 'Yes' : 'No' }}</td>
            <td>Gym</td>
            <td>{{ $property->checkPropertyAmenity('Gym') ? 'Yes' : 'No' }}</td>
        </tr>
        <tr>
            <td>Swimming Pool</td>
            <td>{{ $property->checkPropertyAmenity('Swimming Pool') ? 'Yes' : 'No' }}</td>
            <td>Power Backup</td>
            <td>{{ $property->checkPropertyAmenity('Power Backup') ? 'Yes' : 'No' }}</td>
        </tr>
    </table>

    <br>

    @if ($nearbyEstablishments->isNotEmpty())
        <h3>Points of Interest</h3>
        <table>
            <tr>
                <th style="width: 35%;">Category</th>
                <th>Establishments</th>
            </tr>
            @foreach ($nearbyEstablishments as $typeId => $establishments)
                <tr>
                    <td>{{ $establishments[0]->establishment?->establishmentType?->name ?? 'Nearby' }}</td>
                    <td>
                        @foreach ($establishments as $est)
                            <div>
                                {{ $est->remarks ?: ($est->establishment?->name ?? 'Point of interest') }}:
                                <b>{{ $est->distance_km ? $est->distance_km . ' km' : 'Nearby' }}</b>
                            </div>
                        @endforeach
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <footer>
        Dwelly Property Management • Debadaru Path, Zoo Road, Guwahati • +91 96786 44832
    </footer>
</body>
</html>
