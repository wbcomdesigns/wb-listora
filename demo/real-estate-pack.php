<?php
/**
 * Real Estate Demo Pack — 20 property listings across 5 property types.
 *
 * @package WBListora\Demo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-demo-seeder.php';

use WBListora\Demo\Demo_Seeder;

// ── Categories ──

Demo_Seeder::ensure_categories(
	array(
		'apartment'  => 'Apartment',
		'house'      => 'House',
		'condo'      => 'Condo',
		'commercial' => 'Commercial',
		'land'       => 'Land',
	)
);

Demo_Seeder::ensure_features(
	array(
		'elevator'     => 'Elevator',
		'gym'          => 'Gym',
		'pool'         => 'Pool',
		'parking'      => 'Parking',
		'ac'           => 'Air Conditioning',
		'doorman'      => 'Doorman',
		'laundry'      => 'In-Unit Laundry',
		'storage'      => 'Storage',
		'pet-friendly' => 'Pet Friendly',
		'balcony'      => 'Balcony',
	)
);

// ── Listings ──

$listings = array(
	// ── Apartments ──
	array(
		'title'      => 'Sunny 2BR Loft in SoHo',
		'type'       => 'real-estate',
		'categories' => array( 'Apartment' ),
		'featured'   => true,
		'features'   => array( 'elevator', 'gym', 'ac', 'doorman', 'laundry' ),
		'tags'       => array( 'loft', 'soho', 'luxury', 'doorman' ),
		'content'    => 'Stunning sun-drenched loft in the heart of SoHo. Beautifully renovated 2-bedroom with 12-foot ceilings, exposed brick, original cast-iron columns, and wide-plank hardwood floors. Open-concept kitchen with quartz countertops and stainless steel appliances. South-facing windows flood the space with light. Doorman, rooftop deck, gym, bike storage.',
		'meta'       => array(
			'address'        => array(
				'address'     => '125 Prince Street, SoHo, NY 10012',
				'lat'         => 40.7256,
				'lng'         => -73.9983,
				'city'        => 'SoHo',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10012',
			),
			'listing_action' => 'rent',
			'price'          => array(
				'amount'   => 6500,
				'currency' => 'USD',
			),
			'bedrooms'       => 2,
			'bathrooms'      => 2,
			'area_sqft'      => 1450,
			'property_type'  => 'apartment',
			'year_built'     => 1890,
			'parking'        => 0,
		),
		'reviews'    => array(
			array( 5, 'Dream apartment', 'The ceilings and natural light make this loft feel massive. Building amenities are top-notch. Management is responsive.' ),
		),
	),
	array(
		'title'      => 'Cozy Studio Near Central Park',
		'type'       => 'real-estate',
		'categories' => array( 'Apartment' ),
		'features'   => array( 'elevator', 'ac', 'laundry' ),
		'tags'       => array( 'studio', 'central park', 'affordable', 'starter' ),
		'content'    => 'Perfect starter apartment just two blocks from Central Park. This bright studio features a renovated kitchen with granite countertops, good closet space, and hardwood floors throughout. The building has a live-in super, laundry room, and a shared roof deck with park views. Ideal for young professionals.',
		'meta'       => array(
			'address'        => array(
				'address'     => '201 West 72nd Street, Upper West Side, NY 10023',
				'lat'         => 40.7790,
				'lng'         => -73.9805,
				'city'        => 'Upper West Side',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10023',
			),
			'listing_action' => 'rent',
			'price'          => array(
				'amount'   => 2350,
				'currency' => 'USD',
			),
			'bedrooms'       => 0,
			'bathrooms'      => 1,
			'area_sqft'      => 480,
			'property_type'  => 'apartment',
			'year_built'     => 1962,
			'parking'        => 0,
		),
	),
	array(
		'title'      => 'Spacious 3BR Pre-War in Harlem',
		'type'       => 'real-estate',
		'categories' => array( 'Apartment' ),
		'features'   => array( 'elevator', 'laundry', 'pet-friendly', 'storage' ),
		'tags'       => array( 'harlem', 'pre-war', 'spacious', 'family' ),
		'content'    => 'Massive pre-war 3-bedroom in historic Harlem. Original details include crown molding, hardwood floors, and high ceilings. Updated kitchen and bathrooms. Each bedroom fits a king-sized bed comfortably. Located near 125th Street subway hub with express trains to Midtown in 15 minutes. Pet-friendly building with live-in super.',
		'meta'       => array(
			'address'        => array(
				'address'     => '280 St. Nicholas Avenue, Harlem, NY 10027',
				'lat'         => 40.8090,
				'lng'         => -73.9520,
				'city'        => 'Harlem',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10027',
			),
			'listing_action' => 'rent',
			'price'          => array(
				'amount'   => 3200,
				'currency' => 'USD',
			),
			'bedrooms'       => 3,
			'bathrooms'      => 1,
			'area_sqft'      => 1200,
			'property_type'  => 'apartment',
			'year_built'     => 1928,
			'parking'        => 0,
		),
	),
	array(
		'title'      => 'Modern 1BR with City Views — UES',
		'type'       => 'real-estate',
		'categories' => array( 'Apartment' ),
		'features'   => array( 'elevator', 'gym', 'ac', 'doorman', 'balcony' ),
		'tags'       => array( 'modern', 'views', 'new construction', 'luxury' ),
		'content'    => 'Brand-new luxury 1-bedroom on the 28th floor with breathtaking city views. Floor-to-ceiling windows, white oak flooring, Caesarstone countertops, and Bosch appliances. Building features a landscaped rooftop terrace, fitness center, and 24-hour concierge. Steps from the Q train.',
		'meta'       => array(
			'address'        => array(
				'address'     => '1399 Park Avenue, Upper East Side, NY 10029',
				'lat'         => 40.7925,
				'lng'         => -73.9440,
				'city'        => 'Upper East Side',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10029',
			),
			'listing_action' => 'rent',
			'price'          => array(
				'amount'   => 4100,
				'currency' => 'USD',
			),
			'bedrooms'       => 1,
			'bathrooms'      => 1,
			'area_sqft'      => 750,
			'property_type'  => 'apartment',
			'year_built'     => 2024,
			'parking'        => 0,
		),
		'reviews'    => array(
			array( 5, 'Incredible views', 'Watching the sunset from the 28th floor every evening is priceless. Building is immaculate and staff is wonderful.' ),
		),
	),
	// ── Houses ──
	array(
		'title'      => 'Modern Family Townhouse — Park Slope',
		'type'       => 'real-estate',
		'categories' => array( 'House' ),
		'featured'   => true,
		'features'   => array( 'parking', 'ac', 'laundry', 'storage' ),
		'tags'       => array( 'brownstone', 'park slope', 'garden', 'family' ),
		'content'    => 'Elegant 4-bedroom brownstone on a tree-lined block. Completely renovated in 2023 with chef kitchen, marble counters, Wolf appliances. Parlor floor has 14-foot ceilings, crown molding, wood-burning fireplace. Private south-facing garden. Finished basement with home office. Walk to Prospect Park and excellent schools.',
		'meta'       => array(
			'address'        => array(
				'address'     => '342 3rd Street, Park Slope, Brooklyn, NY 11215',
				'lat'         => 40.6725,
				'lng'         => -73.9820,
				'city'        => 'Park Slope',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11215',
			),
			'listing_action' => 'sale',
			'price'          => array(
				'amount'   => 2850000,
				'currency' => 'USD',
			),
			'bedrooms'       => 4,
			'bathrooms'      => 3,
			'area_sqft'      => 3200,
			'property_type'  => 'townhouse',
			'year_built'     => 1899,
			'parking'        => 1,
		),
		'reviews'    => array(
			array( 5, 'Dream home', 'The renovation is exquisite. Garden is a private oasis. Best block in Park Slope.' ),
			array( 4, 'Beautiful brownstone', 'Incredible craftsmanship. Original details preserved beautifully. The kitchen is a chef\'s dream.' ),
		),
	),
	array(
		'title'      => 'Charming Victorian in Ditmas Park',
		'type'       => 'real-estate',
		'categories' => array( 'House' ),
		'features'   => array( 'parking', 'ac', 'laundry', 'pet-friendly' ),
		'tags'       => array( 'victorian', 'garden', 'garage', 'quiet street' ),
		'content'    => 'Rare detached Victorian home on a quiet, tree-lined street in Ditmas Park. This 5-bedroom beauty features a wraparound porch, original stained glass windows, and a large backyard with mature trees. Updated systems including central air and a new roof. Detached 2-car garage. One of the most charming neighborhoods in Brooklyn.',
		'meta'       => array(
			'address'        => array(
				'address'     => '485 East 17th Street, Ditmas Park, Brooklyn, NY 11226',
				'lat'         => 40.6420,
				'lng'         => -73.9600,
				'city'        => 'Ditmas Park',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11226',
			),
			'listing_action' => 'sale',
			'price'          => array(
				'amount'   => 1950000,
				'currency' => 'USD',
			),
			'bedrooms'       => 5,
			'bathrooms'      => 3,
			'area_sqft'      => 3800,
			'property_type'  => 'house',
			'year_built'     => 1905,
			'parking'        => 2,
		),
	),
	array(
		'title'      => 'Renovated Row House — Bedford-Stuyvesant',
		'type'       => 'real-estate',
		'categories' => array( 'House' ),
		'features'   => array( 'ac', 'laundry', 'storage' ),
		'tags'       => array( 'bed-stuy', 'renovated', 'investment', 'income' ),
		'content'    => 'Fully renovated 3-family row house in the heart of Bed-Stuy. Owner\'s duplex on the first two floors with a stunning kitchen, exposed brick, and backyard access. Two rental units above generate over $4,500 per month in income. New electrical, plumbing, and roof. Prime location near restaurants and the G train.',
		'meta'       => array(
			'address'        => array(
				'address'     => '215 Lewis Avenue, Bed-Stuy, Brooklyn, NY 11221',
				'lat'         => 40.6910,
				'lng'         => -73.9360,
				'city'        => 'Bedford-Stuyvesant',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11221',
			),
			'listing_action' => 'sale',
			'price'          => array(
				'amount'   => 1650000,
				'currency' => 'USD',
			),
			'bedrooms'       => 6,
			'bathrooms'      => 4,
			'area_sqft'      => 3500,
			'property_type'  => 'house',
			'year_built'     => 1910,
			'parking'        => 0,
		),
		'reviews'    => array(
			array( 4, 'Great investment', 'Rental income covers the mortgage. Renovation quality is excellent. Neighborhood is booming.' ),
		),
	),
	array(
		'title'      => 'Classic Colonial — Staten Island Waterfront',
		'type'       => 'real-estate',
		'categories' => array( 'House' ),
		'features'   => array( 'parking', 'ac', 'laundry', 'pool' ),
		'tags'       => array( 'waterfront', 'colonial', 'pool', 'suburban' ),
		'content'    => 'Stunning waterfront colonial with panoramic views of the Verrazzano Bridge. 4 bedrooms, 3.5 baths, and an in-ground pool with cabana. Gourmet kitchen with granite island and double ovens. Master suite with bay window and walk-in closet. Two-car attached garage. Manicured landscaping with stone patio and outdoor kitchen.',
		'meta'       => array(
			'address'        => array(
				'address'     => '120 Shore Road, Staten Island, NY 10305',
				'lat'         => 40.6060,
				'lng'         => -74.0620,
				'city'        => 'Staten Island',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10305',
			),
			'listing_action' => 'sale',
			'price'          => array(
				'amount'   => 1125000,
				'currency' => 'USD',
			),
			'bedrooms'       => 4,
			'bathrooms'      => 4,
			'area_sqft'      => 3000,
			'property_type'  => 'house',
			'year_built'     => 1985,
			'parking'        => 2,
		),
	),
	// ── Condos ──
	array(
		'title'      => 'Luxury Penthouse with Skyline Views',
		'type'       => 'real-estate',
		'categories' => array( 'Condo' ),
		'featured'   => true,
		'features'   => array( 'elevator', 'gym', 'pool', 'ac', 'doorman', 'balcony' ),
		'tags'       => array( 'penthouse', 'luxury', 'skyline views', 'terrace' ),
		'content'    => 'Breathtaking full-floor penthouse with 360-degree Manhattan skyline views. 3-bedroom masterpiece spanning 2,800 sq ft with floor-to-ceiling windows. 30-foot great room for entertaining. Spa-like master bath. Private rooftop terrace with outdoor kitchen and hot tub. Full-service building with concierge, valet, pool, wine storage.',
		'meta'       => array(
			'address'        => array(
				'address'     => '432 Park Avenue, Midtown, NY 10022',
				'lat'         => 40.7614,
				'lng'         => -73.9718,
				'city'        => 'Midtown',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10022',
			),
			'listing_action' => 'sale',
			'price'          => array(
				'amount'   => 8750000,
				'currency' => 'USD',
			),
			'bedrooms'       => 3,
			'bathrooms'      => 4,
			'area_sqft'      => 2800,
			'property_type'  => 'condo',
			'year_built'     => 2021,
			'parking'        => 2,
		),
		'reviews'    => array(
			array( 5, 'Pinnacle of luxury', 'The views from this penthouse are unmatched. Building amenities rival a five-star resort. Worth every dollar.' ),
			array( 5, 'Stunning residence', 'Finishes are impeccable. The private terrace is like having your own park in the sky. Concierge service is world-class.' ),
		),
	),
	array(
		'title'      => 'Waterfront Condo — Long Island City',
		'type'       => 'real-estate',
		'categories' => array( 'Condo' ),
		'features'   => array( 'elevator', 'gym', 'pool', 'parking', 'ac', 'doorman' ),
		'tags'       => array( 'waterfront', 'lic', 'skyline views', 'new construction' ),
		'content'    => 'Brand-new waterfront condominium in the booming Long Island City neighborhood. This 2-bedroom, 2-bath unit offers breathtaking Manhattan skyline views from every room. Floor-to-ceiling windows, European oak flooring, Italian marble bathrooms, and Bosch appliances. Resort-style amenities. Steps from the 7 train.',
		'meta'       => array(
			'address'        => array(
				'address'     => '4545 Center Boulevard, Long Island City, NY 11109',
				'lat'         => 40.7425,
				'lng'         => -73.9575,
				'city'        => 'Long Island City',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11109',
			),
			'listing_action' => 'sale',
			'price'          => array(
				'amount'   => 1295000,
				'currency' => 'USD',
			),
			'bedrooms'       => 2,
			'bathrooms'      => 2,
			'area_sqft'      => 1100,
			'property_type'  => 'condo',
			'year_built'     => 2024,
			'parking'        => 1,
		),
		'reviews'    => array(
			array( 4, 'Great value for waterfront', 'Amazing views at half the price of Manhattan. Building amenities are impressive. Commute is easy via the 7 train.' ),
		),
	),
	array(
		'title'      => 'Boutique Condo in Chelsea',
		'type'       => 'real-estate',
		'categories' => array( 'Condo' ),
		'features'   => array( 'elevator', 'gym', 'ac', 'laundry', 'storage' ),
		'tags'       => array( 'chelsea', 'boutique', 'gallery district', 'modern' ),
		'content'    => 'Sleek 1-bedroom in a boutique 12-unit condo building in the heart of the Chelsea gallery district. This 850 sq ft unit features 10-foot ceilings, wide-plank oak floors, a chef kitchen with waterfall island, and a marble bath with rainfall shower. Walking distance to the High Line, Chelsea Market, and Hudson River Park.',
		'meta'       => array(
			'address'        => array(
				'address'     => '520 West 23rd Street, Chelsea, NY 10011',
				'lat'         => 40.7470,
				'lng'         => -74.0040,
				'city'        => 'Chelsea',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10011',
			),
			'listing_action' => 'sale',
			'price'          => array(
				'amount'   => 1150000,
				'currency' => 'USD',
			),
			'bedrooms'       => 1,
			'bathrooms'      => 1,
			'area_sqft'      => 850,
			'property_type'  => 'condo',
			'year_built'     => 2022,
			'parking'        => 0,
		),
	),
	array(
		'title'      => 'Junior 4 Condo — Brooklyn Heights',
		'type'       => 'real-estate',
		'categories' => array( 'Condo' ),
		'features'   => array( 'elevator', 'laundry', 'doorman', 'pet-friendly' ),
		'tags'       => array( 'brooklyn heights', 'convertible', 'promenade', 'classic' ),
		'content'    => 'Convertible 2-bedroom in a stately Brooklyn Heights co-op with Promenade access. This spacious junior 4 has been thoughtfully updated with a modern kitchen and refinished hardwood floors while preserving classic details. Building has a beautiful garden, doorman, and laundry. One block from the iconic Promenade with its stunning harbor views.',
		'meta'       => array(
			'address'        => array(
				'address'     => '60 Remsen Street, Brooklyn Heights, Brooklyn, NY 11201',
				'lat'         => 40.6945,
				'lng'         => -73.9965,
				'city'        => 'Brooklyn Heights',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11201',
			),
			'listing_action' => 'sale',
			'price'          => array(
				'amount'   => 875000,
				'currency' => 'USD',
			),
			'bedrooms'       => 2,
			'bathrooms'      => 1,
			'area_sqft'      => 1000,
			'property_type'  => 'condo',
			'year_built'     => 1955,
			'parking'        => 0,
		),
	),
	// ── Commercial ──
	array(
		'title'      => 'Prime Retail Space — 5th Avenue',
		'type'       => 'real-estate',
		'categories' => array( 'Commercial' ),
		'featured'   => true,
		'features'   => array( 'ac', 'elevator' ),
		'tags'       => array( 'retail', '5th avenue', 'flagship', 'high traffic' ),
		'content'    => 'Exceptional ground-floor retail opportunity on iconic 5th Avenue. 3,500 sq ft with 50 feet of frontage and floor-to-ceiling glass storefront. High ceilings, column-free layout, and basement storage. Foot traffic exceeds 50,000 pedestrians daily. Ideal for flagship retail, luxury brand, or high-end restaurant concept.',
		'meta'       => array(
			'address'        => array(
				'address'     => '595 5th Avenue, Midtown, NY 10017',
				'lat'         => 40.7572,
				'lng'         => -73.9780,
				'city'        => 'Midtown',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10017',
			),
			'listing_action' => 'rent',
			'price'          => array(
				'amount'   => 45000,
				'currency' => 'USD',
			),
			'bedrooms'       => 0,
			'bathrooms'      => 1,
			'area_sqft'      => 3500,
			'property_type'  => 'commercial',
			'year_built'     => 1960,
			'parking'        => 0,
		),
	),
	array(
		'title'      => 'Creative Office Loft — DUMBO',
		'type'       => 'real-estate',
		'categories' => array( 'Commercial' ),
		'features'   => array( 'elevator', 'ac' ),
		'tags'       => array( 'office', 'loft', 'dumbo', 'creative' ),
		'content'    => 'Inspiring creative office space in a converted warehouse in DUMBO. 2,800 sq ft with soaring 16-foot ceilings, massive factory windows, and exposed timber beams. Open layout perfect for a design studio, tech startup, or creative agency. Building has a cafe, bike storage, and stunning Manhattan Bridge views from the roof.',
		'meta'       => array(
			'address'        => array(
				'address'     => '45 Main Street, DUMBO, Brooklyn, NY 11201',
				'lat'         => 40.7025,
				'lng'         => -73.9903,
				'city'        => 'DUMBO',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11201',
			),
			'listing_action' => 'rent',
			'price'          => array(
				'amount'   => 12500,
				'currency' => 'USD',
			),
			'bedrooms'       => 0,
			'bathrooms'      => 2,
			'area_sqft'      => 2800,
			'property_type'  => 'commercial',
			'year_built'     => 1915,
			'parking'        => 0,
		),
	),
	array(
		'title'      => 'Restaurant Space with Liquor License',
		'type'       => 'real-estate',
		'categories' => array( 'Commercial' ),
		'features'   => array( 'ac' ),
		'tags'       => array( 'restaurant', 'turnkey', 'liquor license', 'west village' ),
		'content'    => 'Turnkey restaurant space in the West Village with existing liquor license. 1,800 sq ft with full commercial kitchen, exhaust system, grease trap, and 40-seat dining room. Charming exposed brick interior. Outdoor cafe seating for 12 with existing sidewalk cafe license. Previous tenant operated successfully for 15 years.',
		'meta'       => array(
			'address'        => array(
				'address'     => '93 Christopher Street, West Village, NY 10014',
				'lat'         => 40.7335,
				'lng'         => -74.0025,
				'city'        => 'West Village',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10014',
			),
			'listing_action' => 'rent',
			'price'          => array(
				'amount'   => 15000,
				'currency' => 'USD',
			),
			'bedrooms'       => 0,
			'bathrooms'      => 2,
			'area_sqft'      => 1800,
			'property_type'  => 'commercial',
			'year_built'     => 1920,
			'parking'        => 0,
		),
	),
	array(
		'title'      => 'Medical Office Suite — Midtown East',
		'type'       => 'real-estate',
		'categories' => array( 'Commercial' ),
		'features'   => array( 'elevator', 'ac', 'doorman' ),
		'tags'       => array( 'medical', 'office', 'professional', 'midtown' ),
		'content'    => 'Professional medical office suite in a prestigious Midtown East building. 1,500 sq ft with 4 treatment rooms, reception area, private office, and storage. Plumbed for dental or medical use. Building has 24-hour security, managed HVAC, and is located near major hospitals. Ideal for dermatology, dental, or specialty practice.',
		'meta'       => array(
			'address'        => array(
				'address'     => '245 East 63rd Street, Midtown East, NY 10065',
				'lat'         => 40.7645,
				'lng'         => -73.9645,
				'city'        => 'Midtown East',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10065',
			),
			'listing_action' => 'rent',
			'price'          => array(
				'amount'   => 8500,
				'currency' => 'USD',
			),
			'bedrooms'       => 0,
			'bathrooms'      => 2,
			'area_sqft'      => 1500,
			'property_type'  => 'commercial',
			'year_built'     => 1975,
			'parking'        => 0,
		),
	),
	// ── Land ──
	array(
		'title'      => 'Development Site — Williamsburg Waterfront',
		'type'       => 'real-estate',
		'categories' => array( 'Land' ),
		'featured'   => true,
		'features'   => array(),
		'tags'       => array( 'development', 'waterfront', 'zoning', 'investment' ),
		'content'    => 'Rare waterfront development site in Williamsburg with R7A zoning allowing for approximately 45,000 buildable square feet. 10,000 sq ft lot with 100 feet of water frontage. Spectacular Manhattan skyline views. Environmental Phase I and II complete. Plans for a 6-story mixed-use building with retail and 40 residential units available.',
		'meta'       => array(
			'address'        => array(
				'address'     => '50 Kent Avenue, Williamsburg, Brooklyn, NY 11249',
				'lat'         => 40.7185,
				'lng'         => -73.9630,
				'city'        => 'Williamsburg',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11249',
			),
			'listing_action' => 'sale',
			'price'          => array(
				'amount'   => 12500000,
				'currency' => 'USD',
			),
			'bedrooms'       => 0,
			'bathrooms'      => 0,
			'area_sqft'      => 10000,
			'property_type'  => 'land',
			'year_built'     => 0,
			'parking'        => 0,
		),
	),
	array(
		'title'      => 'Buildable Lot — South Bronx Opportunity Zone',
		'type'       => 'real-estate',
		'categories' => array( 'Land' ),
		'features'   => array(),
		'tags'       => array( 'opportunity zone', 'bronx', 'tax benefits', 'development' ),
		'content'    => 'Vacant buildable lot in a designated Opportunity Zone, offering significant tax advantages for investors. 5,000 sq ft corner lot with R6 zoning allowing for 30,000 buildable square feet. Located in a rapidly developing area near new Bronx Point development and future Metro-North station. Survey and title report available.',
		'meta'       => array(
			'address'        => array(
				'address'     => '400 East 149th Street, South Bronx, NY 10455',
				'lat'         => 40.8175,
				'lng'         => -73.9225,
				'city'        => 'South Bronx',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10455',
			),
			'listing_action' => 'sale',
			'price'          => array(
				'amount'   => 2200000,
				'currency' => 'USD',
			),
			'bedrooms'       => 0,
			'bathrooms'      => 0,
			'area_sqft'      => 5000,
			'property_type'  => 'land',
			'year_built'     => 0,
			'parking'        => 0,
		),
	),
	array(
		'title'      => 'Suburban Lot — Tottenville, Staten Island',
		'type'       => 'real-estate',
		'categories' => array( 'Land' ),
		'features'   => array(),
		'tags'       => array( 'suburban', 'residential', 'quiet', 'buildable' ),
		'content'    => 'Spacious 7,500 sq ft residential lot in the quiet neighborhood of Tottenville, Staten Island. Zoned R3-2, perfect for a single-family home. Flat terrain, mature trees on the perimeter, and all utilities at the street. Steps from Tottenville train station for commuting. Rare find in an established community with top-rated schools.',
		'meta'       => array(
			'address'        => array(
				'address'     => '75 Page Avenue, Tottenville, Staten Island, NY 10307',
				'lat'         => 40.5090,
				'lng'         => -74.2450,
				'city'        => 'Tottenville',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10307',
			),
			'listing_action' => 'sale',
			'price'          => array(
				'amount'   => 450000,
				'currency' => 'USD',
			),
			'bedrooms'       => 0,
			'bathrooms'      => 0,
			'area_sqft'      => 7500,
			'property_type'  => 'land',
			'year_built'     => 0,
			'parking'        => 0,
		),
	),
	array(
		'title'      => 'Alcove Studio — East Village',
		'type'       => 'real-estate',
		'categories' => array( 'Apartment' ),
		'features'   => array( 'elevator', 'laundry', 'pet-friendly' ),
		'tags'       => array( 'east village', 'alcove', 'nightlife', 'affordable' ),
		'content'    => 'Charming alcove studio in the vibrant East Village. The alcove area fits a full bed behind a bookshelf partition, creating a separate sleeping area. Renovated kitchen with dishwasher and microwave. Hardwood floors throughout. Pet-friendly walk-up building with laundry in basement. Surrounded by the best bars, restaurants, and shops in downtown Manhattan.',
		'meta'       => array(
			'address'        => array(
				'address'     => '415 East 6th Street, East Village, NY 10009',
				'lat'         => 40.7255,
				'lng'         => -73.9840,
				'city'        => 'East Village',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10009',
			),
			'listing_action' => 'rent',
			'price'          => array(
				'amount'   => 2100,
				'currency' => 'USD',
			),
			'bedrooms'       => 0,
			'bathrooms'      => 1,
			'area_sqft'      => 425,
			'property_type'  => 'apartment',
			'year_built'     => 1950,
			'parking'        => 0,
		),
		'reviews'    => array(
			array( 4, 'Great location', 'Right in the heart of the East Village nightlife. The alcove layout is clever and gives good separation. Fun neighborhood.' ),
		),
	),
);

// ── Seed all listings ──

foreach ( $listings as $idx => $listing_data ) {
	$reviews = $listing_data['reviews'] ?? array();
	unset( $listing_data['reviews'] );

	$post_id = Demo_Seeder::seed_listing( $listing_data );
	if ( ! $post_id ) {
		continue;
	}

	if ( ! empty( $reviews ) ) {
		foreach ( $reviews as $review ) {
			Demo_Seeder::seed_review( $post_id, $review[0], $review[1], $review[2] );
		}
	}

	// Real-estate services: showings, virtual tours, mortgage referral.
	$services = array(
		array( 'Private Showing', 0, 60, 'Schedule a 1:1 in-person showing with the listing agent. Free, by appointment.', 'Showings' ),
		array( 'Live Virtual Tour', 0, 30, '30-minute live video walkthrough — get a feel for the space without leaving home.', 'Showings' ),
		array( 'Mortgage Pre-Qualification', 0, 0, 'Connect with a partner lender for a no-obligation pre-qualification letter — usually within 24 hours.', 'Financing' ),
	);

	Demo_Seeder::seed_pack_extras( $post_id, 'real-estate', $idx, $services );
}
