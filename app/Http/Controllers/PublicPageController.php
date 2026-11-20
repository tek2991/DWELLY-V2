<?php

namespace App\Http\Controllers;

use App\Domain\Geographic\Models\Locality;
use App\Domain\Property\Models\BhkType;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\PropertyType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

class PublicPageController extends Controller
{
    public function index()
    {
        try {
            $properties = Property::with([
                'photos',
                'bhkType',
                'propertyType',
                'localityRef',
                'currentPricing',
            ])
            ->where('is_listed', true)
            ->latest()
            ->take(6)
            ->get();

            if ($properties->isEmpty()) {
                $properties = Property::with([
                    'photos',
                    'bhkType',
                    'propertyType',
                    'localityRef',
                    'currentPricing',
                ])
                ->latest()
                ->take(6)
                ->get();
            }
        } catch (\Throwable $e) {
            $properties = collect([]);
        }

        return view('public.index', compact('properties'));
    }

    public function allProperties(Request $request)
    {
        $bhks = DB::table('bhk_types')->where('is_active', true)->orderBy('name')->get();
        $propertyTypes = DB::table('property_types')->where('is_active', true)->get();
        $furnishings = DB::table('furnishing_types')->where('is_active', true)->get();
        $amenities = DB::table('amenity_types')->where('is_active', true)->get();
        $amenities2 = DB::table('amenity_types')->where('is_active', true)->whereIn('name', ['Lift', 'Parking', 'Power Backup', 'Security', 'Swimming Pool'])->get();

        $localities = Locality::whereHas('properties')->get();
        if ($localities->isEmpty()) {
            $localities = Locality::where('is_active', true)->get();
        }

        $filters = new stdClass();
        $filters->bhks = (array) ($request->input('bhks') ?: []);
        $filters->amenities = (array) ($request->input('amenities') ?: []);
        $filters->propertyTypes = (array) ($request->input('propertyTypes') ?: []);
        $filters->furnishings = (array) ($request->input('furnishings') ?: []);
        $filters->localities = (array) ($request->input('localities') ?: []);

        // Filter out empty values
        $filters->bhks = array_filter($filters->bhks);
        $filters->amenities = array_filter($filters->amenities);
        $filters->propertyTypes = array_filter($filters->propertyTypes);
        $filters->furnishings = array_filter($filters->furnishings);
        $filters->localities = array_filter($filters->localities);

        $searchQuery = $request->input('query');
        $sortBy = $request->input('sortBy');

        $propertiesQuery = Property::with([
            'photos',
            'bhkType',
            'propertyType',
            'localityRef',
            'currentPricing',
            'furnishingType',
            'amenities.amenityType',
        ])
        ->where('is_listed', true)
        ->when($searchQuery, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('building_name', 'like', "%{$search}%")
                    ->orWhere('address_line_1', 'like', "%{$search}%")
                    ->orWhere('address_line_2', 'like', "%{$search}%")
                    ->orWhere('locality', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('landmark', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhereHas('localityRef', fn($loc) => $loc->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('amenities.amenityType', fn($am) => $am->where('name', 'like', "%{$search}%"));
            });
        })
        ->when(!empty($filters->bhks), function ($q) use ($filters) {
            $q->whereIn('bhk_type_id', $filters->bhks);
        })
        ->when(!empty($filters->propertyTypes), function ($q) use ($filters) {
            $q->whereIn('property_type_id', $filters->propertyTypes);
        })
        ->when(!empty($filters->furnishings), function ($q) use ($filters) {
            $q->whereIn('furnishing_type_id', $filters->furnishings);
        })
        ->when(!empty($filters->localities), function ($q) use ($filters) {
            $q->whereIn('locality_id', $filters->localities);
        })
        ->when(!empty($filters->amenities), function ($q) use ($filters) {
            $q->whereHas('amenities', function ($sub) use ($filters) {
                $sub->whereIn('amenity_type_id', $filters->amenities);
            });
        });

        if ($sortBy === 'price_asc') {
            $propertiesQuery->leftJoin('property_pricing_versions as ppv', function ($join) {
                $join->on('ppv.property_id', '=', 'properties.id');
            })
            ->select('properties.*')
            ->orderBy('ppv.rent', 'asc');
        } elseif ($sortBy === 'price_desc') {
            $propertiesQuery->leftJoin('property_pricing_versions as ppv', function ($join) {
                $join->on('ppv.property_id', '=', 'properties.id');
            })
            ->select('properties.*')
            ->orderBy('ppv.rent', 'desc');
        } else {
            $propertiesQuery->orderBy('properties.id', 'desc');
        }

        $properties = $propertiesQuery->paginate(10)->withQueryString();

        return view('public.allProperties', compact(
            'properties',
            'bhks',
            'amenities',
            'propertyTypes',
            'furnishings',
            'amenities2',
            'localities',
            'filters',
            'request'
        ));
    }

    public function viewProperty(Property $property)
    {
        $property->load([
            'photos',
            'bhkType',
            'propertyType',
            'localityRef',
            'currentPricing',
            'furnishingType',
            'flooringType',
            'rooms.roomDefinition',
            'inventories.inventoryType',
            'amenities.amenityType',
            'establishments.establishment.establishmentType',
        ]);

        $primaryRooms = ['Bedroom', 'Bathroom', 'Hall', 'Dining', 'Kitchen', 'Puja Room', 'Balcony'];

        $furnitures = $property->inventories;
        $amenities = $property->amenities;

        $nearbyEstablishments = $property->establishments()
            ->with(['establishment.establishmentType'])
            ->get()
            ->groupBy(fn($item) => $item->establishment?->establishment_type_id ?? 'default');

        $propertyImages = $property->photos()
            ->where('is_visible', true)
            ->orderBy('order_column', 'asc')
            ->get();

        if ($propertyImages->isEmpty()) {
            $propertyImages = $property->photos;
        }

        return view('public.viewProperty', compact(
            'property',
            'primaryRooms',
            'furnitures',
            'amenities',
            'nearbyEstablishments',
            'propertyImages'
        ));
    }

    public function propertyBrocure(Property $property)
    {
        $property->load([
            'photos',
            'bhkType',
            'propertyType',
            'localityRef',
            'currentPricing',
            'furnishingType',
            'flooringType',
            'rooms.roomDefinition',
            'amenities.amenityType',
            'establishments.establishment.establishmentType',
        ]);

        $coverPhoto = $property->coverPhoto ?: $property->photos()->first();
        $coverImage = null;
        if ($coverPhoto && file_exists(storage_path('app/public/' . $coverPhoto->file_path))) {
            $imageData = base64_encode(file_get_contents(storage_path('app/public/' . $coverPhoto->file_path)));
            $coverImage = 'data:image/jpeg;base64,' . $imageData;
        }

        $subPhotos = $property->photos()->where('id', '!=', $coverPhoto?->id)->take(3)->get();
        $images = [];
        foreach ($subPhotos as $photo) {
            if (file_exists(storage_path('app/public/' . $photo->file_path))) {
                $imageData = base64_encode(file_get_contents(storage_path('app/public/' . $photo->file_path)));
                $images[] = 'data:image/jpeg;base64,' . $imageData;
            }
        }

        $nearbyEstablishments = $property->establishments()
            ->with(['establishment.establishmentType'])
            ->get()
            ->groupBy(fn($item) => $item->establishment?->establishment_type_id ?? 'default');

        $pdf = Pdf::loadView('pdf.propertyBrochure', compact('property', 'coverImage', 'images', 'nearbyEstablishments'));
        $filename = ($property->code ?: 'Dwelly') . '_Brochure.pdf';
        return $pdf->download($filename);
    }

    public function about()
    {
        return view('public.about');
    }

    public function contact()
    {
        return view('public.contact');
    }

    public function owners()
    {
        return view('public.owners');
    }
}
