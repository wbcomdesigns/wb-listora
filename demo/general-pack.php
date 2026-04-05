<?php
/**
 * General Demo Pack — 20 mixed listings (business, restaurant, real estate, hotel, events).
 *
 * This is the fallback "General Directory" demo for sites that do not match a specific type.
 *
 * @package WBListora\Demo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-demo-seeder.php';

use WBListora\Demo\Demo_Seeder;

// ── Categories ──

Demo_Seeder::ensure_categories(
	array(
		'health-wellness' => 'Health & Wellness',
		'services'        => 'Services',
		'retail'          => 'Retail',
		'food-dining'     => 'Food & Dining',
		'accommodation'   => 'Accommodation',
	)
);

Demo_Seeder::ensure_features(
	array(
		'wifi'         => 'WiFi',
		'parking'      => 'Parking',
		'outdoor'      => 'Outdoor Seating',
		'credit-cards' => 'Credit Cards',
		'wheelchair'   => 'Wheelchair Accessible',
		'pet-friendly' => 'Pet Friendly',
		'ac'           => 'Air Conditioning',
		'delivery'     => 'Delivery',
		'live-music'   => 'Live Music',
		'pool'         => 'Pool',
	)
);

// ── Listings ──

$listings = array(
	// ── Restaurants ──
	array(
		'title'      => 'The Golden Fork',
		'type'       => 'restaurant',
		'categories' => array( 'Food & Dining' ),
		'featured'   => true,
		'features'   => array( 'wifi', 'parking', 'outdoor', 'credit-cards' ),
		'tags'       => array( 'italian', 'pasta', 'fine dining' ),
		'content'    => 'Experience authentic Italian dining at The Golden Fork, where every dish tells a story of tradition and passion. Our chef brings 20 years of experience from Naples, crafting handmade pasta daily and firing pizzas in our imported wood-burning oven.',
		'meta'       => array(
			'address'        => array(
				'address'     => '247 West Broadway, Manhattan, NY 10013',
				'lat'         => 40.7210,
				'lng'         => -74.0050,
				'city'        => 'Manhattan',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10013',
			),
			'phone'          => '(212) 555-0147',
			'website'        => 'https://thegoldenfork.com',
			'email'          => 'info@goldenfork.com',
			'cuisine'        => array( 'italian' ),
			'price_range'    => '$$$',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '11:30',
					'close' => '22:00',
				),
				array(
					'day'   => 2,
					'open'  => '11:30',
					'close' => '22:00',
				),
				array(
					'day'   => 3,
					'open'  => '11:30',
					'close' => '22:00',
				),
				array(
					'day'   => 4,
					'open'  => '11:30',
					'close' => '23:00',
				),
				array(
					'day'   => 5,
					'open'  => '11:30',
					'close' => '23:30',
				),
				array(
					'day'   => 6,
					'open'  => '17:00',
					'close' => '23:30',
				),
				array(
					'day'    => 0,
					'closed' => true,
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Absolutely stunning!', 'The handmade pappardelle with wild boar ragu was the best pasta I have ever had.' ),
			array( 4, 'Great food, pricey', 'Food quality is outstanding. Truffle risotto melts in your mouth.' ),
		),
	),
	array(
		'title'      => 'Sakura House',
		'type'       => 'restaurant',
		'categories' => array( 'Food & Dining' ),
		'features'   => array( 'wifi', 'credit-cards' ),
		'tags'       => array( 'japanese', 'sushi', 'ramen' ),
		'content'    => 'Sakura House brings the authentic flavors of Tokyo to Manhattan. Our sushi chefs trained for over a decade in Japan, mastering the art of nigiri, sashimi, and creative maki rolls. We source the freshest fish daily.',
		'meta'       => array(
			'address'        => array(
				'address'     => '189 East 45th St, Midtown, NY 10017',
				'lat'         => 40.7527,
				'lng'         => -73.9730,
				'city'        => 'Midtown',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10017',
			),
			'phone'          => '(212) 555-0283',
			'website'        => 'https://sakurahouse-nyc.com',
			'cuisine'        => array( 'japanese' ),
			'price_range'    => '$$$',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '11:00',
					'close' => '22:00',
				),
				array(
					'day'   => 2,
					'open'  => '11:00',
					'close' => '22:00',
				),
				array(
					'day'   => 3,
					'open'  => '11:00',
					'close' => '22:00',
				),
				array(
					'day'   => 4,
					'open'  => '11:00',
					'close' => '22:30',
				),
				array(
					'day'   => 5,
					'open'  => '11:00',
					'close' => '23:00',
				),
				array(
					'day'   => 6,
					'open'  => '12:00',
					'close' => '23:00',
				),
				array(
					'day'   => 0,
					'open'  => '12:00',
					'close' => '21:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Best sushi in Midtown', 'The omakase experience was incredible. Each piece crafted with precision.' ),
		),
	),
	array(
		'title'      => 'Casa Miguel Cantina',
		'type'       => 'restaurant',
		'categories' => array( 'Food & Dining' ),
		'features'   => array( 'parking', 'outdoor', 'live-music', 'credit-cards' ),
		'tags'       => array( 'mexican', 'margaritas', 'tacos' ),
		'content'    => 'Vibrant, colorful, and bursting with flavor. Casa Miguel brings the soul of Oaxaca to NYC. Recipes passed down through three generations. Slow-smoked barbacoa, hand-pressed tortillas, and seven house-made salsas.',
		'meta'       => array(
			'address'        => array(
				'address'     => '523 Amsterdam Ave, Upper West Side, NY 10024',
				'lat'         => 40.7875,
				'lng'         => -73.9735,
				'city'        => 'Upper West Side',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10024',
			),
			'phone'          => '(212) 555-0391',
			'website'        => 'https://casamiguel.nyc',
			'cuisine'        => array( 'mexican' ),
			'price_range'    => '$$',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '12:00',
					'close' => '23:00',
				),
				array(
					'day'   => 2,
					'open'  => '12:00',
					'close' => '23:00',
				),
				array(
					'day'   => 3,
					'open'  => '12:00',
					'close' => '23:00',
				),
				array(
					'day'   => 4,
					'open'  => '12:00',
					'close' => '23:30',
				),
				array(
					'day'   => 5,
					'open'  => '12:00',
					'close' => '00:30',
				),
				array(
					'day'   => 6,
					'open'  => '11:00',
					'close' => '00:30',
				),
				array(
					'day'   => 0,
					'open'  => '11:00',
					'close' => '22:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Incredible atmosphere!', 'Mariachi band on Friday made our birthday unforgettable.' ),
			array( 4, 'Authentic and fun', 'Great food, great vibes. Street corn and churros are must-orders.' ),
		),
	),
	array(
		'title'      => 'Spice Route Kitchen',
		'type'       => 'restaurant',
		'categories' => array( 'Food & Dining' ),
		'features'   => array( 'wifi', 'parking', 'credit-cards' ),
		'tags'       => array( 'indian', 'curry', 'tandoori' ),
		'content'    => 'A culinary journey through India. Our tandoor oven burns 24 hours producing flavorful naan and succulent kebabs. Each curry built from scratch using freshly ground spices. Vegetarian and vegan options abundant.',
		'meta'       => array(
			'address'        => array(
				'address'     => '312 Lexington Ave, Murray Hill, NY 10016',
				'lat'         => 40.7485,
				'lng'         => -73.9780,
				'city'        => 'Murray Hill',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10016',
			),
			'phone'          => '(212) 555-0472',
			'cuisine'        => array( 'indian' ),
			'price_range'    => '$$',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '11:30',
					'close' => '22:30',
				),
				array(
					'day'   => 2,
					'open'  => '11:30',
					'close' => '22:30',
				),
				array(
					'day'   => 3,
					'open'  => '11:30',
					'close' => '22:30',
				),
				array(
					'day'   => 4,
					'open'  => '11:30',
					'close' => '22:30',
				),
				array(
					'day'   => 5,
					'open'  => '11:30',
					'close' => '23:00',
				),
				array(
					'day'   => 6,
					'open'  => '11:00',
					'close' => '23:00',
				),
				array(
					'day'   => 0,
					'open'  => '11:00',
					'close' => '22:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Best Indian in NYC', 'Butter chicken is life-changing. Garlic naan fresh from tandoor is addictive.' ),
		),
	),
	// ── Real Estate ──
	array(
		'title'      => 'Sunny 2BR Loft in SoHo',
		'type'       => 'real-estate',
		'categories' => array( 'Accommodation' ),
		'featured'   => true,
		'features'   => array( 'ac', 'wifi' ),
		'tags'       => array( 'loft', 'soho', 'luxury' ),
		'content'    => 'Stunning sun-drenched loft in the heart of SoHo. 2-bedroom with 12-foot ceilings, exposed brick, and hardwood floors. Open-concept kitchen with quartz countertops. Doorman building with rooftop deck and gym.',
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
		),
	),
	array(
		'title'      => 'Modern Family Townhouse — Park Slope',
		'type'       => 'real-estate',
		'categories' => array( 'Accommodation' ),
		'features'   => array( 'parking', 'ac' ),
		'tags'       => array( 'brownstone', 'park slope', 'family' ),
		'content'    => 'Elegant 4-bedroom brownstone on a tree-lined block in Park Slope. Completely renovated with chef kitchen, marble counters, and Wolf appliances. Private garden. Walk to Prospect Park.',
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
		),
	),
	array(
		'title'      => 'Luxury Penthouse with Skyline Views',
		'type'       => 'real-estate',
		'categories' => array( 'Accommodation' ),
		'featured'   => true,
		'features'   => array( 'pool', 'parking', 'ac' ),
		'tags'       => array( 'penthouse', 'luxury', 'skyline' ),
		'content'    => 'Breathtaking full-floor penthouse with 360-degree Manhattan skyline views. 3-bedroom masterpiece with floor-to-ceiling windows. Private rooftop terrace with outdoor kitchen and hot tub. Full-service building.',
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
		),
	),
	// ── Hotels ──
	array(
		'title'      => 'The Greenwich Hotel',
		'type'       => 'hotel',
		'categories' => array( 'Accommodation' ),
		'featured'   => true,
		'features'   => array( 'wifi', 'pool', 'parking', 'wheelchair' ),
		'tags'       => array( 'boutique', 'tribeca', 'luxury', 'spa' ),
		'content'    => 'A sanctuary of calm in Tribeca combining old-world charm with modern luxury. 88 individually designed rooms. The Shibui Spa features a 250-year-old Japanese farmhouse. Complimentary town car service within Manhattan.',
		'meta'       => array(
			'address'         => array(
				'address'     => '377 Greenwich Street, Tribeca, NY 10013',
				'lat'         => 40.7205,
				'lng'         => -74.0100,
				'city'        => 'Tribeca',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10013',
			),
			'phone'           => '(212) 555-0622',
			'star_rating'     => '5',
			'price_per_night' => array(
				'amount'   => 895,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '12:00',
			'rooms'           => 88,
		),
		'reviews'    => array(
			array( 5, 'Pure luxury', 'Every detail is thoughtfully considered. Shibui Spa pool is otherworldly.' ),
			array( 5, 'Perfection', 'The town car, the welcome champagne, the rooftop yoga. Elegance without pretension.' ),
		),
	),
	array(
		'title'      => 'Brooklyn Bridge Inn',
		'type'       => 'hotel',
		'categories' => array( 'Accommodation' ),
		'features'   => array( 'wifi', 'credit-cards' ),
		'tags'       => array( 'bed and breakfast', 'dumbo', 'views' ),
		'content'    => 'Charming boutique B&B in DUMBO with stunning Brooklyn Bridge and Manhattan skyline views. 12 rooms with exposed brick and local artwork. Famous homemade breakfast. Rooftop terrace with incredible views.',
		'meta'       => array(
			'address'         => array(
				'address'     => '68 Jay Street, DUMBO, Brooklyn, NY 11201',
				'lat'         => 40.7024,
				'lng'         => -73.9867,
				'city'        => 'DUMBO',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11201',
			),
			'phone'           => '(718) 555-0834',
			'star_rating'     => '4',
			'price_per_night' => array(
				'amount'   => 275,
				'currency' => 'USD',
			),
			'check_in_time'   => '14:00',
			'check_out_time'  => '11:00',
			'rooms'           => 12,
		),
		'reviews'    => array(
			array( 5, 'Most charming B&B', 'Rooftop view of Brooklyn Bridge at sunset. Homemade breakfast was delicious.' ),
			array( 4, 'Great location', 'Perfect base for exploring Brooklyn and Manhattan. Fresh croissants at breakfast.' ),
		),
	),
	array(
		'title'      => 'The Williamsburg Hotel',
		'type'       => 'hotel',
		'categories' => array( 'Accommodation' ),
		'features'   => array( 'wifi', 'pool', 'credit-cards' ),
		'tags'       => array( 'rooftop pool', 'trendy', 'brooklyn' ),
		'content'    => 'The epicenter of Brooklyn cool. Industrial-chic design with floor-to-ceiling windows overlooking Manhattan. Rooftop pool and bar. Harvey restaurant serves seasonal New American cuisine. 150 rooms blend raw concrete and warm wood.',
		'meta'       => array(
			'address'         => array(
				'address'     => '96 Wythe Avenue, Williamsburg, Brooklyn, NY 11249',
				'lat'         => 40.7225,
				'lng'         => -73.9580,
				'city'        => 'Williamsburg',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11249',
			),
			'phone'           => '(718) 555-0321',
			'star_rating'     => '4',
			'price_per_night' => array(
				'amount'   => 425,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '11:00',
			'rooms'           => 150,
		),
		'reviews'    => array(
			array( 4, 'Rooftop pool is amazing', 'The pool overlooking Manhattan at sunset is worth the stay alone.' ),
		),
	),
	// ── Businesses ──
	array(
		'title'      => 'FitLab Performance Gym',
		'type'       => 'business',
		'categories' => array( 'Health & Wellness' ),
		'features'   => array( 'wifi', 'parking', 'ac', 'wheelchair' ),
		'tags'       => array( 'gym', 'fitness', 'personal training' ),
		'content'    => 'Not just a gym, a performance center. 15,000 sq ft with Technogym equipment, Olympic lifting platform, and recovery area with infrared saunas and cold plunge pools. Classes include spin, yoga, boxing, and MetCon.',
		'meta'       => array(
			'address'          => array(
				'address'     => '455 West 23rd St, Chelsea, NY 10011',
				'lat'         => 40.7465,
				'lng'         => -74.0015,
				'city'        => 'Chelsea',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10011',
			),
			'phone'            => '(212) 555-0756',
			'website'          => 'https://fitlabnyc.com',
			'email'            => 'hello@fitlabnyc.com',
			'price_range'      => '$$$',
			'year_established' => 2019,
			'business_hours'   => array(
				array(
					'day'   => 1,
					'open'  => '05:00',
					'close' => '23:00',
				),
				array(
					'day'   => 2,
					'open'  => '05:00',
					'close' => '23:00',
				),
				array(
					'day'   => 3,
					'open'  => '05:00',
					'close' => '23:00',
				),
				array(
					'day'   => 4,
					'open'  => '05:00',
					'close' => '23:00',
				),
				array(
					'day'   => 5,
					'open'  => '05:00',
					'close' => '23:00',
				),
				array(
					'day'   => 6,
					'open'  => '07:00',
					'close' => '22:00',
				),
				array(
					'day'   => 0,
					'open'  => '07:00',
					'close' => '20:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Best gym in Chelsea', 'Incredible facility. Cold plunge after a hard workout is game-changing.' ),
		),
	),
	array(
		'title'      => 'Pixel & Code Design Studio',
		'type'       => 'business',
		'categories' => array( 'Services' ),
		'features'   => array( 'wifi', 'credit-cards' ),
		'tags'       => array( 'web design', 'branding', 'digital agency' ),
		'content'    => 'Boutique web design and branding agency helping small businesses look big. WordPress development, brand identity, logo design, and digital marketing. Portfolio includes restaurants, startups, and e-commerce brands.',
		'meta'       => array(
			'address'          => array(
				'address'     => '127 Kent Ave, Williamsburg, Brooklyn, NY 11249',
				'lat'         => 40.7155,
				'lng'         => -73.9614,
				'city'        => 'Williamsburg',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11249',
			),
			'phone'            => '(347) 555-0912',
			'website'          => 'https://pixelandcode.studio',
			'email'            => 'hello@pixelandcode.studio',
			'price_range'      => '$$',
			'year_established' => 2017,
			'business_hours'   => array(
				array(
					'day'    => 1,
					'closed' => true,
				),
				array(
					'day'   => 2,
					'open'  => '09:00',
					'close' => '18:00',
				),
				array(
					'day'   => 3,
					'open'  => '09:00',
					'close' => '18:00',
				),
				array(
					'day'   => 4,
					'open'  => '09:00',
					'close' => '18:00',
				),
				array(
					'day'   => 5,
					'open'  => '09:00',
					'close' => '18:00',
				),
				array(
					'day'   => 6,
					'open'  => '10:00',
					'close' => '15:00',
				),
				array(
					'day'    => 0,
					'closed' => true,
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Transformed our brand', 'Redesigned our website and created a stunning logo. Traffic doubled after launch.' ),
		),
	),
	array(
		'title'      => 'Greenleaf Plant Shop',
		'type'       => 'business',
		'categories' => array( 'Retail' ),
		'features'   => array( 'wifi', 'wheelchair', 'credit-cards', 'pet-friendly' ),
		'tags'       => array( 'plants', 'garden', 'houseplants' ),
		'content'    => 'Transform your home into a jungle paradise. Over 200 varieties of indoor plants from rare aroids to hardy succulents. Free care consultations and repotting services. Workshops on propagation every Saturday.',
		'meta'       => array(
			'address'          => array(
				'address'     => '558 Atlantic Avenue, Boerum Hill, Brooklyn, NY 11217',
				'lat'         => 40.6845,
				'lng'         => -73.9780,
				'city'        => 'Boerum Hill',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11217',
			),
			'phone'            => '(718) 555-0267',
			'website'          => 'https://greenleafplants.co',
			'price_range'      => '$$',
			'year_established' => 2020,
			'business_hours'   => array(
				array(
					'day'   => 1,
					'open'  => '10:00',
					'close' => '19:00',
				),
				array(
					'day'   => 2,
					'open'  => '10:00',
					'close' => '19:00',
				),
				array(
					'day'   => 3,
					'open'  => '10:00',
					'close' => '19:00',
				),
				array(
					'day'   => 4,
					'open'  => '10:00',
					'close' => '19:00',
				),
				array(
					'day'   => 5,
					'open'  => '10:00',
					'close' => '19:00',
				),
				array(
					'day'   => 6,
					'open'  => '09:00',
					'close' => '20:00',
				),
				array(
					'day'   => 0,
					'open'  => '10:00',
					'close' => '17:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Plant lover paradise!', 'Found a rare philodendron I searched for months. Staff are super knowledgeable.' ),
		),
	),
	array(
		'title'      => 'Brooklyn Barber Club',
		'type'       => 'business',
		'categories' => array( 'Services' ),
		'features'   => array( 'wifi', 'ac', 'credit-cards' ),
		'tags'       => array( 'barber', 'haircut', 'grooming' ),
		'content'    => 'Old-school barbershop with new-school style. Premium cuts, hot towel shaves, beard trims, and scalp treatments. Complimentary whiskey or craft beer with every service. Walk-ins welcome.',
		'meta'       => array(
			'address'          => array(
				'address'     => '92 North 6th Street, Williamsburg, Brooklyn, NY 11249',
				'lat'         => 40.7180,
				'lng'         => -73.9600,
				'city'        => 'Williamsburg',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11249',
			),
			'phone'            => '(347) 555-0578',
			'website'          => 'https://brooklynbarberclub.com',
			'price_range'      => '$$',
			'year_established' => 2018,
			'business_hours'   => array(
				array(
					'day'   => 1,
					'open'  => '09:00',
					'close' => '20:00',
				),
				array(
					'day'   => 2,
					'open'  => '09:00',
					'close' => '20:00',
				),
				array(
					'day'   => 3,
					'open'  => '09:00',
					'close' => '20:00',
				),
				array(
					'day'   => 4,
					'open'  => '09:00',
					'close' => '20:00',
				),
				array(
					'day'   => 5,
					'open'  => '09:00',
					'close' => '21:00',
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
		),
		'reviews'    => array(
			array( 5, 'Best haircut in Brooklyn', 'Carlos gave me the best fade ever. Hot towel shave was luxurious.' ),
			array( 4, 'Great vibes', 'Cool vintage decor, good music, excellent cuts.' ),
		),
	),
	array(
		'title'      => 'Sunrise Yoga & Wellness',
		'type'       => 'business',
		'categories' => array( 'Health & Wellness' ),
		'features'   => array( 'wifi', 'ac', 'wheelchair' ),
		'tags'       => array( 'yoga', 'meditation', 'wellness' ),
		'content'    => 'Find your balance at Sunrise Yoga & Wellness. 40+ classes per week including Vinyasa, Hatha, Yin, Hot Yoga, and Meditation. 3,000 sq ft studio with radiant heated floors and tea lounge. First class is always free.',
		'meta'       => array(
			'address'          => array(
				'address'     => '178 Grand Street, Lower East Side, NY 10013',
				'lat'         => 40.7190,
				'lng'         => -73.9960,
				'city'        => 'Lower East Side',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10013',
			),
			'phone'            => '(212) 555-0845',
			'website'          => 'https://sunriseyoganyc.com',
			'email'            => 'namaste@sunriseyoganyc.com',
			'price_range'      => '$$',
			'year_established' => 2016,
			'business_hours'   => array(
				array(
					'day'   => 1,
					'open'  => '06:00',
					'close' => '21:00',
				),
				array(
					'day'   => 2,
					'open'  => '06:00',
					'close' => '21:00',
				),
				array(
					'day'   => 3,
					'open'  => '06:00',
					'close' => '21:00',
				),
				array(
					'day'   => 4,
					'open'  => '06:00',
					'close' => '21:00',
				),
				array(
					'day'   => 5,
					'open'  => '06:00',
					'close' => '21:00',
				),
				array(
					'day'   => 6,
					'open'  => '07:00',
					'close' => '19:00',
				),
				array(
					'day'   => 0,
					'open'  => '08:00',
					'close' => '18:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'My happy place', 'The morning Vinyasa with Sarah is the best way to start the day. Beautiful studio.' ),
		),
	),
	// ── More restaurants ──
	array(
		'title'      => 'Le Petit Bistro',
		'type'       => 'restaurant',
		'categories' => array( 'Food & Dining' ),
		'features'   => array( 'wifi', 'outdoor', 'credit-cards' ),
		'tags'       => array( 'french', 'bistro', 'brunch' ),
		'content'    => 'A slice of Paris on the Upper East Side. Classic French cuisine in an intimate setting. Duck confit, steak frites, and creme brulee. Weekend brunch with bottomless mimosas.',
		'meta'       => array(
			'address'        => array(
				'address'     => '876 Madison Avenue, Upper East Side, NY 10021',
				'lat'         => 40.7735,
				'lng'         => -73.9640,
				'city'        => 'Upper East Side',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10021',
			),
			'phone'          => '(212) 555-0629',
			'website'        => 'https://lepetitbistro.nyc',
			'cuisine'        => array( 'french' ),
			'price_range'    => '$$$',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '11:00',
					'close' => '22:00',
				),
				array(
					'day'   => 2,
					'open'  => '11:00',
					'close' => '22:00',
				),
				array(
					'day'   => 3,
					'open'  => '11:00',
					'close' => '22:00',
				),
				array(
					'day'   => 4,
					'open'  => '11:00',
					'close' => '22:00',
				),
				array(
					'day'   => 5,
					'open'  => '11:00',
					'close' => '23:00',
				),
				array(
					'day'   => 6,
					'open'  => '10:00',
					'close' => '23:00',
				),
				array(
					'day'   => 0,
					'open'  => '10:00',
					'close' => '21:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'French perfection', 'Duck confit is crispy outside, tender inside. Creme brulee is the best in the city.' ),
		),
	),
	array(
		'title'      => 'Flames & Smoke BBQ',
		'type'       => 'restaurant',
		'categories' => array( 'Food & Dining' ),
		'features'   => array( 'parking', 'outdoor', 'pet-friendly', 'live-music' ),
		'tags'       => array( 'bbq', 'ribs', 'craft beer' ),
		'content'    => 'Low and slow is our motto. Authentic Southern BBQ smoked for up to 18 hours over hickory and applewood. Texas-style brisket, baby back ribs, and smoked wings. 24 craft beers on tap. Live blues on Thursdays.',
		'meta'       => array(
			'address'        => array(
				'address'     => '215 Flatbush Avenue, Brooklyn, NY 11217',
				'lat'         => 40.6815,
				'lng'         => -73.9735,
				'city'        => 'Brooklyn',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11217',
			),
			'phone'          => '(718) 555-0443',
			'website'        => 'https://flamesandsmoke.com',
			'cuisine'        => array( 'american' ),
			'price_range'    => '$$',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '11:30',
					'close' => '22:00',
				),
				array(
					'day'   => 2,
					'open'  => '11:30',
					'close' => '22:00',
				),
				array(
					'day'   => 3,
					'open'  => '11:30',
					'close' => '22:00',
				),
				array(
					'day'   => 4,
					'open'  => '11:30',
					'close' => '23:00',
				),
				array(
					'day'   => 5,
					'open'  => '11:30',
					'close' => '00:00',
				),
				array(
					'day'   => 6,
					'open'  => '11:00',
					'close' => '00:00',
				),
				array(
					'day'   => 0,
					'open'  => '11:00',
					'close' => '21:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Real deal BBQ!', 'Brisket is the real thing. Perfect smoke ring, melt-in-your-mouth tender.' ),
			array( 5, 'Best ribs ever', 'Baby back ribs fall right off the bone. Homemade sauce is perfection.' ),
		),
	),
	array(
		'title'      => 'Cozy Studio Near Central Park',
		'type'       => 'real-estate',
		'categories' => array( 'Accommodation' ),
		'features'   => array( 'ac' ),
		'tags'       => array( 'studio', 'central park', 'affordable' ),
		'content'    => 'Perfect starter apartment just two blocks from Central Park. Bright studio with renovated kitchen, good closet space, and hardwood floors. Building has laundry room and shared roof deck with park views.',
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
		),
	),
	array(
		'title'      => 'Quick Print & Ship',
		'type'       => 'business',
		'categories' => array( 'Services' ),
		'features'   => array( 'wifi', 'credit-cards', 'wheelchair' ),
		'tags'       => array( 'printing', 'shipping', 'notary' ),
		'content'    => 'Your one-stop shop for printing, copying, binding, shipping, and notary services. We handle everything from business cards to large-format posters. FedEx, UPS, and USPS shipping. Passport photos while you wait. Serving the Midtown business community since 2005.',
		'meta'       => array(
			'address'          => array(
				'address'     => '244 West 35th Street, Midtown, NY 10001',
				'lat'         => 40.7530,
				'lng'         => -73.9915,
				'city'        => 'Midtown',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10001',
			),
			'phone'            => '(212) 555-0332',
			'website'          => 'https://quickprintship.com',
			'email'            => 'orders@quickprintship.com',
			'price_range'      => '$',
			'year_established' => 2005,
			'business_hours'   => array(
				array(
					'day'   => 1,
					'open'  => '08:00',
					'close' => '19:00',
				),
				array(
					'day'   => 2,
					'open'  => '08:00',
					'close' => '19:00',
				),
				array(
					'day'   => 3,
					'open'  => '08:00',
					'close' => '19:00',
				),
				array(
					'day'   => 4,
					'open'  => '08:00',
					'close' => '19:00',
				),
				array(
					'day'   => 5,
					'open'  => '08:00',
					'close' => '19:00',
				),
				array(
					'day'   => 6,
					'open'  => '09:00',
					'close' => '15:00',
				),
				array(
					'day'    => 0,
					'closed' => true,
				),
			),
		),
		'reviews'    => array(
			array( 4, 'Reliable and fast', 'Printed 500 brochures overnight. Quality was great and price was fair. My go-to print shop.' ),
		),
	),
	array(
		'title'      => 'Blue Harbor Seafood',
		'type'       => 'restaurant',
		'categories' => array( 'Food & Dining' ),
		'featured'   => true,
		'features'   => array( 'parking', 'outdoor', 'wheelchair', 'credit-cards' ),
		'tags'       => array( 'seafood', 'waterfront', 'oysters' ),
		'content'    => 'Overlooking the Hudson River, Blue Harbor delivers the finest ocean-to-table dining. Fish arrives daily from Montauk and Maine. Raw bar features East and West Coast oysters. Floor-to-ceiling windows provide stunning river views at sunset.',
		'meta'       => array(
			'address'        => array(
				'address'     => '88 Pier 17, South Street Seaport, NY 10038',
				'lat'         => 40.7063,
				'lng'         => -74.0032,
				'city'        => 'Financial District',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10038',
			),
			'phone'          => '(212) 555-0518',
			'website'        => 'https://blueharborseafood.com',
			'cuisine'        => array( 'other' ),
			'price_range'    => '$$$$',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '17:00',
					'close' => '22:00',
				),
				array(
					'day'   => 2,
					'open'  => '17:00',
					'close' => '22:00',
				),
				array(
					'day'   => 3,
					'open'  => '17:00',
					'close' => '22:00',
				),
				array(
					'day'   => 4,
					'open'  => '17:00',
					'close' => '22:30',
				),
				array(
					'day'   => 5,
					'open'  => '16:00',
					'close' => '23:00',
				),
				array(
					'day'   => 6,
					'open'  => '12:00',
					'close' => '23:00',
				),
				array(
					'day'   => 0,
					'open'  => '12:00',
					'close' => '21:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Sunset dinner was magical', 'Window table, sunset over the river, freshest oysters. Worth every penny.' ),
			array( 4, 'Excellent seafood', 'Lobster tower is spectacular for sharing. Service is impeccable.' ),
		),
	),
);

// ── Seed all listings ──

foreach ( $listings as $listing_data ) {
	$reviews = $listing_data['reviews'] ?? array();
	unset( $listing_data['reviews'] );

	$post_id = Demo_Seeder::seed_listing( $listing_data );

	if ( $post_id && ! empty( $reviews ) ) {
		foreach ( $reviews as $review ) {
			Demo_Seeder::seed_review( $post_id, $review[0], $review[1], $review[2] );
		}
	}
}
