<?php
/**
 * Place / Tourist Attraction Demo Pack — 8 attractions, parks, museums.
 *
 * @package WBListora\Demo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-demo-seeder.php';

use WBListora\Demo\Demo_Seeder;

// ── Categories ──

Demo_Seeder::ensure_categories(
	array(
		'museum'         => 'Museum',
		'park'           => 'Park',
		'monument'       => 'Monument',
		'beach'          => 'Beach',
		'temple'         => 'Temple',
		'zoo'            => 'Zoo',
		'amusement-park' => 'Amusement Park',
		'garden'         => 'Garden',
		'historic-site'  => 'Historic Site',
		'viewpoint'      => 'Viewpoint',
		'market'         => 'Market',
	)
);

Demo_Seeder::ensure_features(
	array(
		'family-friendly'  => 'Family Friendly',
		'pet-friendly'     => 'Pet Friendly',
		'free-admission'   => 'Free Admission',
		'paid-admission'   => 'Paid Admission',
		'wheelchair'       => 'Wheelchair Accessible',
		'audio-tours'      => 'Audio Tours',
		'guided-tours'     => 'Guided Tours',
		'gift-shop'        => 'Gift Shop',
		'cafe'             => 'Cafe On-Site',
		'parking-lot'      => 'Parking Lot',
	)
);

// ── Listings ──

$listings = array(
	array(
		'title'      => 'Brooklyn Bridge Park',
		'type'       => 'place',
		'categories' => array( 'Park', 'Viewpoint' ),
		'featured'   => true,
		'features'   => array( 'family-friendly', 'pet-friendly', 'free-admission', 'wheelchair' ),
		'tags'       => array( 'park', 'waterfront', 'skyline-views' ),
		'content'    => 'An 85-acre revitalized post-industrial waterfront park stretching 1.3 miles along the East River. Six piers offer everything from soccer fields to handball courts to a giant carousel. Sweeping views of the Manhattan skyline, the Statue of Liberty, and the Brooklyn Bridge. Free outdoor movies in summer. Multiple food vendors and Smorgasburg pop-ups.',
		'address'    => array(
			'address' => 'Brooklyn Bridge Park, Brooklyn, NY',
			'city'    => 'Brooklyn',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'            => array(
				'address' => '334 Furman St, Brooklyn, NY 11201',
				'lat'     => 40.6991,
				'lng'     => -73.9999,
				'city'    => 'Brooklyn',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'              => '(718) 555-0080',
			'website'            => 'https://brooklynbridgepark.example.com',
			'admission_fee'      => 0,
			'business_hours'     => Demo_Seeder::make_hours( '06:00', '23:00', false ),
			'duration_suggested' => '2-4 hours',
			'best_time_to_visit' => 'Spring through early fall — sunset over Manhattan is magic.',
			'accessibility'      => array( 'Wheelchair accessible paths', 'ADA-compliant restrooms' ),
		),
		'reviews'    => array(
			array( 5, 'Best park in NYC', 'Could spend an entire weekend here. Pier 6 has great playgrounds for kids; Pier 1 is incredible at sunset.' ),
			array( 5, 'Free, gorgeous, accessible', 'Walked the whole length with a stroller — no problem at all. The skyline views are unreal.' ),
		),
	),
	array(
		'title'      => 'The Metropolitan Museum of Art',
		'type'       => 'place',
		'categories' => array( 'Museum' ),
		'featured'   => true,
		'features'   => array( 'paid-admission', 'wheelchair', 'audio-tours', 'guided-tours', 'gift-shop', 'cafe' ),
		'tags'       => array( 'art', 'museum', 'world-famous' ),
		'content'    => 'One of the world’s great art museums, with collections spanning 5,000 years of human creativity across two locations. The Egyptian Wing, the European Paintings galleries, the American Wing, and the rooftop garden are highlights. NYC residents pay what they wish; out-of-state visitors $30. Plan at least 3 hours — you’ll want to come back.',
		'address'    => array(
			'address' => 'Upper East Side, Manhattan, NY',
			'city'    => 'Manhattan',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'            => array(
				'address' => '1000 5th Ave, New York, NY 10028',
				'lat'     => 40.7794,
				'lng'     => -73.9632,
				'city'    => 'Manhattan',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'              => '(212) 555-0535',
			'website'            => 'https://metmuseum.example.com',
			'admission_fee'      => 30,
			'business_hours'     => array(
				array(
					'day'    => 1,
					'closed' => true,
				),
				array(
					'day'   => 2,
					'open'  => '10:00',
					'close' => '17:00',
				),
				array(
					'day'   => 3,
					'open'  => '10:00',
					'close' => '17:00',
				),
				array(
					'day'   => 4,
					'open'  => '10:00',
					'close' => '17:00',
				),
				array(
					'day'   => 5,
					'open'  => '10:00',
					'close' => '21:00',
				),
				array(
					'day'   => 6,
					'open'  => '10:00',
					'close' => '21:00',
				),
				array(
					'day'   => 0,
					'open'  => '10:00',
					'close' => '17:00',
				),
			),
			'duration_suggested' => '3-5 hours',
			'best_time_to_visit' => 'Friday and Saturday evenings — open until 9 pm and far less crowded.',
			'accessibility'      => array( 'Wheelchair accessible', 'Audio descriptions', 'Sign language tours by appointment' ),
		),
		'reviews'    => array(
			array( 5, 'Always worth it', 'Even after 20 visits I still discover something new. The European Paintings rooms are unreal.' ),
		),
	),
	array(
		'title'      => 'Coney Island Beach & Boardwalk',
		'type'       => 'place',
		'categories' => array( 'Beach', 'Amusement Park' ),
		'features'   => array( 'family-friendly', 'free-admission', 'paid-admission', 'wheelchair' ),
		'tags'       => array( 'beach', 'boardwalk', 'amusement-park', 'summer' ),
		'content'    => 'New York City’s favorite summer destination. Two miles of free public beach, a historic boardwalk, the Cyclone roller coaster, the Wonder Wheel, classic Nathan’s hot dogs, and the New York Aquarium. The beach is free; rides at Luna Park sold separately. Avoid summer Saturdays after noon if you want elbow room.',
		'address'    => array(
			'address' => 'Coney Island, Brooklyn, NY',
			'city'    => 'Brooklyn',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'            => array(
				'address' => '1208 Surf Ave, Brooklyn, NY 11224',
				'lat'     => 40.5749,
				'lng'     => -73.9786,
				'city'    => 'Brooklyn',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'              => '(718) 555-0265',
			'website'            => 'https://coneyisland.example.com',
			'admission_fee'      => 0,
			'business_hours'     => Demo_Seeder::make_hours( '06:00', '23:00', false ),
			'duration_suggested' => 'Half day',
			'best_time_to_visit' => 'Weekday afternoons in June and September — warm but uncrowded.',
			'accessibility'      => array( 'Boardwalk wheelchair accessible', 'Beach mats during summer' ),
		),
		'reviews'    => array(
			array( 4, 'Classic NYC summer', 'A bit chaotic on a hot Saturday but that is part of the charm. Cyclone is still a thrill.' ),
		),
	),
	array(
		'title'      => 'Central Park — The Mall & Bethesda Terrace',
		'type'       => 'place',
		'categories' => array( 'Park', 'Historic Site' ),
		'featured'   => true,
		'features'   => array( 'family-friendly', 'pet-friendly', 'free-admission', 'wheelchair', 'guided-tours' ),
		'tags'       => array( 'central-park', 'park', 'historic' ),
		'content'    => 'The grand pedestrian promenade and ornate terrace at the heart of Central Park. The American elm-lined Mall is one of the largest stands of remaining elms in North America. Bethesda Terrace and its Angel of the Waters statue feel like stepping into a film set. Live music and street performers most weekends.',
		'address'    => array(
			'address' => 'Central Park, Manhattan, NY',
			'city'    => 'Manhattan',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'            => array(
				'address' => 'Central Park, New York, NY 10024',
				'lat'     => 40.7728,
				'lng'     => -73.9712,
				'city'    => 'Manhattan',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'              => '(212) 555-6600',
			'website'            => 'https://centralparknyc.example.com',
			'admission_fee'      => 0,
			'business_hours'     => Demo_Seeder::make_hours( '06:00', '01:00', false ),
			'duration_suggested' => '1-2 hours',
			'best_time_to_visit' => 'Spring afternoons and golden-hour winter walks.',
			'accessibility'      => array( 'Mostly wheelchair accessible', 'Some cobblestone areas near Bethesda' ),
		),
		'reviews'    => array(
			array( 5, 'Magical every season', 'I have walked here hundreds of times — it never feels old. Cherry blossoms in April are unreal.' ),
		),
	),
	array(
		'title'      => 'Brooklyn Botanic Garden',
		'type'       => 'place',
		'categories' => array( 'Garden' ),
		'features'   => array( 'family-friendly', 'paid-admission', 'wheelchair', 'gift-shop', 'cafe' ),
		'tags'       => array( 'garden', 'cherry-blossoms', 'family' ),
		'content'    => 'A 52-acre botanic garden with over 14,000 plant varieties from around the world. The Cherry Esplanade in late April draws thousands; the Japanese Hill-and-Pond Garden is a year-round highlight. Special exhibitions in the conservatory each season. NYC residents free on Friday mornings.',
		'address'    => array(
			'address' => 'Crown Heights, Brooklyn, NY',
			'city'    => 'Brooklyn',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'            => array(
				'address' => '990 Washington Ave, Brooklyn, NY 11225',
				'lat'     => 40.6699,
				'lng'     => -73.9627,
				'city'    => 'Brooklyn',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'              => '(718) 555-7700',
			'website'            => 'https://bbg.example.com',
			'admission_fee'      => 18,
			'business_hours'     => Demo_Seeder::make_hours( '10:00', '18:00', false ),
			'duration_suggested' => '2-3 hours',
			'best_time_to_visit' => 'Cherry Blossom Festival, last weekend of April.',
			'accessibility'      => array( 'Wheelchair accessible paths', 'Wheelchair loans at entry' ),
		),
		'reviews'    => array(
			array( 5, 'A peaceful escape', 'A breath of fresh air in the middle of Brooklyn. The conservatory is gorgeous in winter.' ),
		),
	),
	array(
		'title'      => 'Statue of Liberty & Ellis Island',
		'type'       => 'place',
		'categories' => array( 'Monument', 'Historic Site' ),
		'features'   => array( 'family-friendly', 'paid-admission', 'wheelchair', 'audio-tours', 'gift-shop' ),
		'tags'       => array( 'statue-of-liberty', 'monument', 'history', 'ellis-island' ),
		'content'    => 'The most iconic monument in the world, plus the deeply moving Ellis Island Immigration Museum where over 12 million immigrants entered the United States. Ferry from Battery Park. Tickets include both islands. Plan at least 4 hours total. Crown access requires a separate, very-limited reservation booked months in advance.',
		'address'    => array(
			'address' => 'Liberty Island, NY',
			'city'    => 'New York',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'            => array(
				'address' => 'Liberty Island, New York, NY 10004',
				'lat'     => 40.6892,
				'lng'     => -74.0445,
				'city'    => 'New York',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'              => '(212) 555-0364',
			'website'            => 'https://statueofliberty.example.com',
			'admission_fee'      => 24,
			'business_hours'     => Demo_Seeder::make_hours( '09:00', '17:00', false ),
			'duration_suggested' => '4-5 hours including ferry',
			'best_time_to_visit' => 'Mornings on weekdays — the first ferry has the shortest lines.',
			'accessibility'      => array( 'Pedestal accessible by elevator', 'Crown not wheelchair accessible' ),
		),
		'reviews'    => array(
			array( 5, 'Powerful and moving', 'Ellis Island was the unexpected highlight — researching family records was incredibly emotional.' ),
		),
	),
	array(
		'title'      => 'High Line — Elevated Linear Park',
		'type'       => 'place',
		'categories' => array( 'Park', 'Viewpoint' ),
		'features'   => array( 'family-friendly', 'free-admission', 'wheelchair', 'guided-tours' ),
		'tags'       => array( 'high-line', 'park', 'urban-design' ),
		'content'    => 'A 1.5-mile elevated public park built on a former freight rail line. Wild gardens, sculptures, and unique vantage points over the Hudson Yards, the West Side, and Chelsea. Free, year-round. Elevators at major access points for wheelchair access. Best entered at the Gansevoort end and walked north.',
		'address'    => array(
			'address' => 'Chelsea, Manhattan, NY',
			'city'    => 'Manhattan',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'            => array(
				'address' => 'Gansevoort St, New York, NY 10014',
				'lat'     => 40.7400,
				'lng'     => -74.0080,
				'city'    => 'Manhattan',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'              => '(212) 555-9100',
			'website'            => 'https://thehighline.example.com',
			'admission_fee'      => 0,
			'business_hours'     => Demo_Seeder::make_hours( '07:00', '22:00', false ),
			'duration_suggested' => '1.5 hours end to end',
			'best_time_to_visit' => 'Weekday mornings in late spring.',
			'accessibility'      => array( 'Elevators at most access points', 'Smooth pavers throughout' ),
		),
		'reviews'    => array(
			array( 5, 'Unique experience', 'Where else can you walk a wild garden 30 feet above traffic? Magical at golden hour.' ),
		),
	),
	array(
		'title'      => 'Union Square Greenmarket',
		'type'       => 'place',
		'categories' => array( 'Market' ),
		'features'   => array( 'family-friendly', 'pet-friendly', 'free-admission', 'wheelchair' ),
		'tags'       => array( 'farmers-market', 'food', 'local' ),
		'content'    => 'Manhattan’s flagship farmers market, open year-round Mondays, Wednesdays, Fridays, and Saturdays. Over 140 regional farmers, fishers, and bakers. A fantastic stop for a snack, fresh flowers, or just to wander. Live cooking demos most Saturdays. Watch for the famous winter mushroom guy and the apple cider donut line.',
		'address'    => array(
			'address' => 'Union Square, Manhattan, NY',
			'city'    => 'Manhattan',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'            => array(
				'address' => 'E 17th St & Broadway, New York, NY 10003',
				'lat'     => 40.7359,
				'lng'     => -73.9911,
				'city'    => 'Manhattan',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'              => '(212) 555-1010',
			'website'            => 'https://grownyc.example.com',
			'admission_fee'      => 0,
			'business_hours'     => array(
				array(
					'day'   => 1,
					'open'  => '08:00',
					'close' => '18:00',
				),
				array(
					'day'    => 2,
					'closed' => true,
				),
				array(
					'day'   => 3,
					'open'  => '08:00',
					'close' => '18:00',
				),
				array(
					'day'    => 4,
					'closed' => true,
				),
				array(
					'day'   => 5,
					'open'  => '08:00',
					'close' => '18:00',
				),
				array(
					'day'   => 6,
					'open'  => '08:00',
					'close' => '18:00',
				),
				array(
					'day'    => 0,
					'closed' => true,
				),
			),
			'duration_suggested' => '45-60 minutes',
			'best_time_to_visit' => 'Saturday mornings before noon — peak energy and freshest produce.',
			'accessibility'      => array( 'Open plaza, wheelchair accessible' ),
		),
		'reviews'    => array(
			array( 5, 'My weekly ritual', 'I have been coming here every Saturday for 8 years. The community of farmers is what makes it special.' ),
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

	// Place-style services: guided tours, photo experience.
	$services = array(
		array( 'Guided Walking Tour', 35, 90, '90-minute small-group tour led by a local guide. Skip the lines and learn the story behind every corner.', 'Tours' ),
		array( 'Sunset Photo Experience', 89, 75, 'Joinable group session with a pro photographer at golden hour. Includes 10 edited photos.', 'Tours' ),
	);

	Demo_Seeder::seed_pack_extras( $post_id, 'place', $idx, $services );
}
