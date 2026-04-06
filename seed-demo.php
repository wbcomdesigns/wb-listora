<?php
/**
 * Demo Content Seeder — run via: wp eval-file wp-content/plugins/wb-listora/seed-demo.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_set_current_user( 1 );

global $wpdb;
$prefix = $wpdb->prefix . 'listora_';

// Delete old listings.
$old = get_posts(
	array(
		'post_type'      => 'listora_listing',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $old as $id ) {
	wp_delete_post( $id, true );
}
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "TRUNCATE TABLE {$prefix}reviews" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "TRUNCATE TABLE {$prefix}search_index" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "TRUNCATE TABLE {$prefix}field_index" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "TRUNCATE TABLE {$prefix}geo" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "TRUNCATE TABLE {$prefix}hours" );
echo "Cleaned old data.\n";

// ─── Helpers ───

function wb_listora_wb_listora_seed_listing( $data ) {
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'listora_listing',
			'post_title'   => $data['title'],
			'post_content' => $data['content'],
			'post_status'  => 'draft',
			'post_author'  => 1,
		)
	);

	wp_set_object_terms( $post_id, $data['type'], 'listora_listing_type' );

	if ( ! empty( $data['categories'] ) ) {
		wp_set_object_terms( $post_id, $data['categories'], 'listora_listing_cat' );
	}
	if ( ! empty( $data['features'] ) ) {
		wp_set_object_terms( $post_id, $data['features'], 'listora_listing_feature' );
	}
	if ( ! empty( $data['tags'] ) ) {
		wp_set_object_terms( $post_id, $data['tags'], 'listora_listing_tag' );
	}

	foreach ( $data['meta'] as $key => $value ) {
		\WBListora\Core\Meta_Handler::set_value( $post_id, $key, $value );
	}

	if ( ! empty( $data['featured'] ) ) {
		update_post_meta( $post_id, '_listora_is_featured', true );
	}

	update_post_meta( $post_id, '_listora_demo_content', true );
	update_post_meta( $post_id, '_listora_timezone', 'America/New_York' );

	wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => 'publish',
		)
	);

	return $post_id;
}

function wb_listora_wb_listora_seed_review( $listing_id, $rating, $title, $content ) {
	global $wpdb;
	$prefix = $wpdb->prefix . 'listora_';

	static $rid = 100;
	++$rid;

	$wpdb->insert(
		"{$prefix}reviews", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		array(
			'listing_id'     => $listing_id,
			'user_id'        => $rid, // Fake unique users.
			'overall_rating' => $rating,
			'title'          => $title,
			'content'        => $content,
			'status'         => 'approved',
			'helpful_count'  => wp_rand( 0, 15 ),
			'created_at'     => gmdate( 'Y-m-d H:i:s', strtotime( '-' . wp_rand( 1, 90 ) . ' days' ) ),
			'updated_at'     => current_time( 'mysql', true ),
		)
	);

	// Update rating in search_index.
	$stats = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT AVG(overall_rating) as avg_r, COUNT(*) as cnt FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$listing_id
		),
		ARRAY_A
	);

	$wpdb->update(
		"{$prefix}search_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		array(
			'avg_rating'   => round( (float) $stats['avg_r'], 2 ),
			'review_count' => (int) $stats['cnt'],
		),
		array( 'listing_id' => $listing_id )
	);
}

$hours_standard = array(
	array(
		'day'   => 1,
		'open'  => '09:00',
		'close' => '21:00',
	),
	array(
		'day'   => 2,
		'open'  => '09:00',
		'close' => '21:00',
	),
	array(
		'day'   => 3,
		'open'  => '09:00',
		'close' => '21:00',
	),
	array(
		'day'   => 4,
		'open'  => '09:00',
		'close' => '21:00',
	),
	array(
		'day'   => 5,
		'open'  => '09:00',
		'close' => '22:00',
	),
	array(
		'day'   => 6,
		'open'  => '10:00',
		'close' => '22:00',
	),
	array(
		'day'    => 0,
		'closed' => true,
	),
);

// ══════════════════════════════════════
// RESTAURANTS
// ══════════════════════════════════════

$r1 = wb_listora_seed_listing(
	array(
		'title'      => 'The Golden Fork',
		'type'       => 'restaurant',
		'categories' => array( 'Italian' ),
		'featured'   => true,
		'features'   => array( 'wifi', 'parking', 'outdoor', 'credit-cards' ),
		'tags'       => array( 'fine dining', 'pasta', 'wine bar', 'date night' ),
		'content'    => 'Experience authentic Italian dining at The Golden Fork, where every dish tells a story of tradition and passion. Our chef brings 20 years of experience from Naples, crafting handmade pasta daily and firing pizzas in our imported wood-burning oven. The warm, rustic ambiance with exposed brick walls and soft candlelight makes it perfect for romantic dinners or family celebrations.',
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
			'delivery'       => false,
			'takeout'        => true,
			'reservations'   => 'online',
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
	)
);
wb_listora_seed_review( $r1, 5, 'Absolutely stunning!', 'The handmade pappardelle with wild boar ragu was the best pasta I have ever had. Wine pairing was excellent.' );
wb_listora_seed_review( $r1, 4, 'Great food, pricey', 'Food quality is outstanding. The truffle risotto melts in your mouth. Only reason for 4 stars is the price.' );
wb_listora_seed_review( $r1, 5, 'Our go-to Italian', 'We have been coming here monthly for 2 years. Consistency is remarkable. Outdoor patio in summer is magical.' );
echo "✓ The Golden Fork (Restaurant, Italian, Featured, 3 reviews)\n";

$r2 = wb_listora_seed_listing(
	array(
		'title'      => 'Sakura House',
		'type'       => 'restaurant',
		'categories' => array( 'Japanese' ),
		'features'   => array( 'wifi', 'credit-cards' ),
		'tags'       => array( 'sushi', 'ramen', 'fresh fish' ),
		'content'    => 'Sakura House brings the authentic flavors of Tokyo to Manhattan. Our sushi chefs trained for over a decade in Japan, mastering the art of nigiri, sashimi, and creative maki rolls. We source the freshest fish daily from Fulton Fish Market. The minimalist Japanese interior creates a serene dining experience.',
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
			'price_range'    => '$$',
			'delivery'       => true,
			'takeout'        => true,
			'reservations'   => 'yes',
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
	)
);
wb_listora_seed_review( $r2, 5, 'Best sushi in Midtown', 'The omakase experience was incredible. Chef prepared each piece with precision. Toro was perfection.' );
wb_listora_seed_review( $r2, 4, 'Fresh and delicious', 'Lunch bento box is unbeatable value. Generous portions. Miso ramen on a cold day is pure comfort.' );
echo "✓ Sakura House (Restaurant, Japanese, 2 reviews)\n";

$r3 = wb_listora_seed_listing(
	array(
		'title'      => 'Casa Miguel Cantina',
		'type'       => 'restaurant',
		'categories' => array( 'Mexican' ),
		'features'   => array( 'parking', 'outdoor', 'live-music', 'credit-cards' ),
		'tags'       => array( 'margaritas', 'tacos', 'live music' ),
		'content'    => 'Vibrant, colorful, and bursting with flavor — Casa Miguel brings the soul of Oaxaca to NYC. Recipes passed down through three generations. Slow-smoked barbacoa, hand-pressed tortillas, and seven house-made salsas. Live mariachi music every Friday and Saturday night. Our margarita menu features 15 unique creations.',
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
			'delivery'       => true,
			'takeout'        => true,
			'reservations'   => 'yes',
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
	)
);
wb_listora_seed_review( $r3, 5, 'Incredible atmosphere!', 'Mariachi band on Friday made our birthday unforgettable. Mole negro is the best outside Mexico. Spicy mango margarita is dangerously good.' );
wb_listora_seed_review( $r3, 4, 'Authentic and fun', 'Great food, great vibes. Street corn and churros are must-orders. Gets loud on weekends but that is the charm.' );
echo "✓ Casa Miguel Cantina (Restaurant, Mexican, 2 reviews)\n";

$r4 = wb_listora_seed_listing(
	array(
		'title'      => 'Spice Route Kitchen',
		'type'       => 'restaurant',
		'categories' => array( 'Indian' ),
		'features'   => array( 'wifi', 'parking', 'credit-cards' ),
		'tags'       => array( 'curry', 'tandoori', 'vegetarian', 'brunch' ),
		'content'    => 'A culinary journey through India. Our tandoor oven burns 24 hours producing flavorful naan and succulent kebabs. Each curry built from scratch using freshly ground spices. Weekend brunch features all-you-can-eat thali with 12 dishes rotating weekly. Vegetarian and vegan options abundant.',
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
			'delivery'       => true,
			'takeout'        => true,
			'reservations'   => 'online',
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
	)
);
wb_listora_seed_review( $r4, 5, 'Best Indian in NYC', 'Butter chicken is life-changing. Garlic naan fresh from tandoor is addictive. Weekend brunch thali is incredible value.' );
echo "✓ Spice Route Kitchen (Restaurant, Indian, 1 review)\n";

$r5 = wb_listora_seed_listing(
	array(
		'title'      => 'Blue Harbor Seafood',
		'type'       => 'restaurant',
		'categories' => array( 'Seafood' ),
		'featured'   => true,
		'features'   => array( 'parking', 'outdoor', 'wheelchair', 'credit-cards' ),
		'tags'       => array( 'seafood', 'waterfront', 'oysters', 'views' ),
		'content'    => 'Overlooking the Hudson River, Blue Harbor delivers the finest ocean-to-table dining. Fish arrives daily from Montauk and Maine. Raw bar features East and West Coast oysters, jumbo shrimp, and our famous lobster tower. Pan-seared Chilean sea bass and New England clam chowder are legendary. Floor-to-ceiling windows provide stunning river views at sunset.',
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
			'delivery'       => false,
			'takeout'        => false,
			'reservations'   => 'online',
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
	)
);
wb_listora_seed_review( $r5, 5, 'Sunset dinner was magical', 'Window table, sunset over the river, freshest oysters and perfectly seared sea bass. Worth every penny.' );
wb_listora_seed_review( $r5, 4, 'Excellent seafood', 'Lobster tower is spectacular for sharing. Service is impeccable. Expensive but quality and setting justify it.' );
echo "✓ Blue Harbor Seafood (Restaurant, Seafood, Featured, 2 reviews)\n";

// ══════════════════════════════════════
// REAL ESTATE
// ══════════════════════════════════════

$re1 = wb_listora_seed_listing(
	array(
		'title'      => 'Sunny 2BR Loft in SoHo',
		'type'       => 'real-estate',
		'categories' => array( 'Apartment' ),
		'featured'   => true,
		'features'   => array( 'elevator', 'gym', 'ac' ),
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
	)
);
echo "✓ Sunny 2BR Loft (Real Estate, Rent \$6,500/mo, Featured)\n";

$re2 = wb_listora_seed_listing(
	array(
		'title'      => 'Modern Family Townhouse — Park Slope',
		'type'       => 'real-estate',
		'categories' => array( 'Townhouse' ),
		'features'   => array( 'parking', 'ac' ),
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
	)
);
echo "✓ Modern Family Townhouse (Real Estate, Sale \$2.85M)\n";

$re3 = wb_listora_seed_listing(
	array(
		'title'      => 'Luxury Penthouse with Skyline Views',
		'type'       => 'real-estate',
		'categories' => array( 'Condo' ),
		'featured'   => true,
		'features'   => array( 'elevator', 'gym', 'pool', 'ac', '24-hour' ),
		'tags'       => array( 'penthouse', 'luxury', 'skyline views' ),
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
	)
);
echo "✓ Luxury Penthouse (Real Estate, Sale \$8.75M, Featured)\n";

// ══════════════════════════════════════
// HOTELS
// ══════════════════════════════════════

$h1 = wb_listora_seed_listing(
	array(
		'title'      => 'The Greenwich Hotel',
		'type'       => 'hotel',
		'categories' => array( 'Boutique Hotel' ),
		'featured'   => true,
		'features'   => array( 'wifi', 'pool', 'gym', 'ac', '24-hour', 'wheelchair', 'parking' ),
		'tags'       => array( 'boutique', 'tribeca', 'luxury', 'spa' ),
		'content'    => 'A sanctuary of calm in Tribeca combining old-world charm with modern luxury. 88 individually designed rooms with antique furnishings, Moroccan tile, and Tibetan silk rugs. The Shibui Spa features a 250-year-old Japanese farmhouse reconstructed within a lantern-lit pool. Complimentary town car service within Manhattan.',
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
			'booking_url'     => 'https://greenwichhotel.com/book',
		),
	)
);
wb_listora_seed_review( $h1, 5, 'Pure luxury', 'Every detail is thoughtfully considered. Hand-painted ceiling, most comfortable bed ever. Shibui Spa pool is otherworldly.' );
wb_listora_seed_review( $h1, 5, 'Perfection', 'The town car to dinner, the welcome champagne, the rooftop yoga — everything about this hotel whispers elegance without pretension.' );
echo "✓ The Greenwich Hotel (Hotel, 5-star, \$895/night, Featured, 2 reviews)\n";

$h2 = wb_listora_seed_listing(
	array(
		'title'      => 'Brooklyn Bridge Inn',
		'type'       => 'hotel',
		'categories' => array( 'B&B' ),
		'features'   => array( 'wifi', 'credit-cards' ),
		'tags'       => array( 'bed and breakfast', 'dumbo', 'brooklyn bridge views' ),
		'content'    => 'Charming boutique B&B in DUMBO with stunning Brooklyn Bridge and Manhattan skyline views. 12 rooms with exposed brick, reclaimed wood furniture, and local artwork. Famous homemade breakfast with fresh pastries, seasonal fruit, and specialty coffee. Rooftop terrace has one of the best views in Brooklyn.',
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
	)
);
wb_listora_seed_review( $h2, 5, 'Most charming B&B', 'Rooftop view of Brooklyn Bridge at sunset took my breath away. Homemade breakfast was delicious. Hosts made us feel like family.' );
wb_listora_seed_review( $h2, 4, 'Great location', 'Perfect base for exploring Brooklyn and Manhattan. Rooms are small but beautifully decorated. Fresh croissants at breakfast were heavenly.' );
echo "✓ Brooklyn Bridge Inn (Hotel, 4-star, \$275/night, 2 reviews)\n";

// ══════════════════════════════════════
// BUSINESSES
// ══════════════════════════════════════

$b1 = wb_listora_seed_listing(
	array(
		'title'      => 'FitLab Performance Gym',
		'type'       => 'business',
		'categories' => array( 'Health & Wellness' ),
		'features'   => array( 'wifi', 'parking', 'ac', 'wheelchair' ),
		'tags'       => array( 'gym', 'fitness', 'personal training', 'yoga' ),
		'content'    => 'Not just a gym — a performance center designed for results. 15,000 sq ft with Technogym equipment, Olympic lifting platform, functional training zone, and recovery area with infrared saunas and cold plunge pools. Certified trainers specialize in strength, HIIT, mobility, and sport-specific conditioning. Classes include spin, yoga, boxing, and our signature MetCon.',
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
					'close' => '23:59',
				),
				array(
					'day'   => 2,
					'open'  => '05:00',
					'close' => '23:59',
				),
				array(
					'day'   => 3,
					'open'  => '05:00',
					'close' => '23:59',
				),
				array(
					'day'   => 4,
					'open'  => '05:00',
					'close' => '23:59',
				),
				array(
					'day'   => 5,
					'open'  => '05:00',
					'close' => '23:59',
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
	)
);
wb_listora_seed_review( $b1, 5, 'Best gym in Chelsea', 'Incredible facility, top-notch equipment. Coaches are knowledgeable and push you. Cold plunge after a hard workout is game-changing.' );
echo "✓ FitLab Performance Gym (Business, 1 review)\n";

$b2 = wb_listora_seed_listing(
	array(
		'title'      => 'Pixel & Code Design Studio',
		'type'       => 'business',
		'categories' => array( 'Services' ),
		'features'   => array( 'wifi', 'credit-cards' ),
		'tags'       => array( 'web design', 'branding', 'wordpress', 'digital agency' ),
		'content'    => 'Boutique web design and branding agency helping small businesses look big. WordPress development, brand identity, logo design, and digital marketing. Collaborative approach from mood boards to launch. Portfolio includes restaurants, startups, law firms, and e-commerce brands.',
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
	)
);
wb_listora_seed_review( $b2, 5, 'Transformed our brand', 'Redesigned our restaurant website and created a stunning logo. Process was smooth, exceeded expectations. Traffic doubled after launch.' );
echo "✓ Pixel & Code Design Studio (Business, 1 review)\n";

// ══════════════════════════════════════
// MORE RESTAURANTS
// ══════════════════════════════════════

$r6 = wb_listora_seed_listing(
	array(
		'title'      => 'Le Petit Bistro',
		'type'       => 'restaurant',
		'categories' => array( 'French' ),
		'features'   => array( 'wifi', 'outdoor', 'credit-cards' ),
		'tags'       => array( 'french', 'bistro', 'brunch', 'wine' ),
		'content'    => 'A slice of Paris on the Upper East Side. Le Petit Bistro serves classic French cuisine in an intimate setting with checkered tablecloths and vintage posters. Our duck confit, steak frites, and crème brûlée transport you straight to the Left Bank. Weekend brunch with bottomless mimosas is a neighborhood institution.',
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
			'delivery'       => false,
			'takeout'        => true,
			'reservations'   => 'online',
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
	)
);
wb_listora_seed_review( $r6, 5, 'French perfection', 'The duck confit is crispy outside, tender inside — exactly as it should be. Crème brûlée is the best in the city. Tres magnifique!' );
wb_listora_seed_review( $r6, 4, 'Charming spot', 'Feels like dining in a real Parisian bistro. The onion soup gratin is soul-warming. Small space so reserve ahead.' );
echo "✓ Le Petit Bistro (Restaurant, French, 2 reviews)\n";

$r7 = wb_listora_seed_listing(
	array(
		'title'      => 'Flames & Smoke BBQ',
		'type'       => 'restaurant',
		'categories' => array( 'American' ),
		'features'   => array( 'parking', 'outdoor', 'pet-friendly', 'live-music', 'credit-cards' ),
		'tags'       => array( 'bbq', 'smoked meat', 'ribs', 'craft beer' ),
		'content'    => 'Low and slow is our motto. Flames & Smoke serves authentic Southern BBQ smoked for up to 18 hours over hickory and applewood. Our pitmaster hails from Austin, Texas and brings true Texas-style brisket to Brooklyn. The pulled pork, baby back ribs, and smoked wings are legendary. 24 craft beers on tap and a bourbon selection that would make any Texan proud. Live blues music every Thursday.',
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
			'delivery'       => true,
			'takeout'        => true,
			'reservations'   => 'no',
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
	)
);
wb_listora_seed_review( $r7, 5, 'Real deal BBQ!', 'As a Texan living in NYC, I was skeptical. But this brisket is the real thing — perfect smoke ring, melt-in-your-mouth tender. The mac and cheese is addictive.' );
wb_listora_seed_review( $r7, 5, 'Best ribs ever', 'The baby back ribs fall right off the bone. The homemade BBQ sauce is smoky, sweet, and tangy. We waited 45 minutes but it was 100% worth it.' );
echo "✓ Flames & Smoke BBQ (Restaurant, American, 2 reviews)\n";

// ══════════════════════════════════════
// MORE REAL ESTATE
// ══════════════════════════════════════

$re4 = wb_listora_seed_listing(
	array(
		'title'      => 'Cozy Studio Near Central Park',
		'type'       => 'real-estate',
		'categories' => array( 'Apartment' ),
		'features'   => array( 'elevator', 'ac' ),
		'tags'       => array( 'studio', 'central park', 'starter apartment' ),
		'content'    => 'Perfect starter apartment just two blocks from Central Park. This bright studio features a renovated kitchen with granite countertops, good closet space, and hardwood floors throughout. The building has a live-in super, laundry room, and a shared roof deck with park views. Ideal for young professionals who want to be in the heart of the action.',
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
	)
);
echo "✓ Cozy Studio Near Central Park (Real Estate, Rent \$2,350/mo)\n";

$re5 = wb_listora_seed_listing(
	array(
		'title'      => 'Waterfront Condo — Long Island City',
		'type'       => 'real-estate',
		'categories' => array( 'Condo' ),
		'features'   => array( 'elevator', 'gym', 'pool', 'parking', 'ac', '24-hour' ),
		'tags'       => array( 'waterfront', 'lic', 'skyline views', 'new construction' ),
		'content'    => 'Brand-new waterfront condominium in the booming Long Island City neighborhood. This 2-bedroom, 2-bath unit offers breathtaking Manhattan skyline views from every room. Floor-to-ceiling windows, European oak flooring, Italian marble bathrooms, and Bosch appliances. Resort-style amenities including infinity pool, sky lounge, co-working space, children play area, and 24/7 concierge. Steps from the 7 train — just one stop to Grand Central.',
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
	)
);
echo "✓ Waterfront Condo LIC (Real Estate, Sale \$1.295M)\n";

// ══════════════════════════════════════
// MORE BUSINESSES
// ══════════════════════════════════════

$b3 = wb_listora_seed_listing(
	array(
		'title'      => 'Greenleaf Plant Shop',
		'type'       => 'business',
		'categories' => array( 'Retail' ),
		'features'   => array( 'wifi', 'wheelchair', 'credit-cards', 'pet-friendly' ),
		'tags'       => array( 'plants', 'garden', 'houseplants', 'gift shop' ),
		'content'    => 'Transform your home into a jungle paradise. Greenleaf carries over 200 varieties of indoor plants from rare aroids to hardy succulents. Our plant experts offer free care consultations and repotting services. We also carry handmade ceramic pots, macrame hangers, soil mixes, and plant care tools. Workshops on propagation and terrarium building every Saturday.',
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
			'email'            => 'hello@greenleafplants.co',
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
	)
);
wb_listora_seed_review( $b3, 5, 'Plant lover paradise!', 'The selection is incredible — found a rare philodendron I have been searching for months. Staff are super knowledgeable and helped me pick the perfect plants for my low-light apartment.' );
echo "✓ Greenleaf Plant Shop (Business, Retail, 1 review)\n";

$b4 = wb_listora_seed_listing(
	array(
		'title'      => 'Brooklyn Barber Club',
		'type'       => 'business',
		'categories' => array( 'Services' ),
		'features'   => array( 'wifi', 'ac', 'credit-cards' ),
		'tags'       => array( 'barber', 'haircut', 'grooming', 'mens' ),
		'content'    => 'Old-school barbershop with new-school style. Brooklyn Barber Club offers premium cuts, hot towel shaves, beard trims, and scalp treatments in a classic barbershop atmosphere. Complimentary whiskey or craft beer with every service. Our barbers are trained in all styles from classic fades to modern textured cuts. Walk-ins welcome, appointments recommended on weekends.',
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
	)
);
wb_listora_seed_review( $b4, 5, 'Best haircut in Brooklyn', 'Carlos gave me the best fade I have ever had. The hot towel shave was luxurious. And a free bourbon? Yes please. My new regular spot.' );
wb_listora_seed_review( $b4, 4, 'Great vibes', 'Cool vintage decor, good music, excellent cuts. Only minor wait on a Saturday. The beard trim was clean and precise.' );
echo "✓ Brooklyn Barber Club (Business, Services, 2 reviews)\n";

$b5 = wb_listora_seed_listing(
	array(
		'title'      => 'Sunrise Yoga & Wellness',
		'type'       => 'business',
		'categories' => array( 'Health & Wellness' ),
		'features'   => array( 'wifi', 'ac', 'wheelchair' ),
		'tags'       => array( 'yoga', 'meditation', 'wellness', 'pilates' ),
		'content'    => 'Find your balance at Sunrise Yoga & Wellness. We offer 40+ classes per week including Vinyasa, Hatha, Yin, Hot Yoga, and Meditation. Our 3,000 sq ft studio features radiant heated floors, natural lighting, and a tea lounge. First class is always free for new students. Teacher training programs available. Private sessions and corporate wellness packages upon request.',
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
	)
);
wb_listora_seed_review( $b5, 5, 'My happy place', 'The morning Vinyasa flow with Sarah is the best way to start the day. Beautiful studio with amazing energy. Tea lounge is a nice touch.' );
echo "✓ Sunrise Yoga & Wellness (Business, Health, 1 review)\n";

// ══════════════════════════════════════
// MORE HOTELS
// ══════════════════════════════════════

$h3 = wb_listora_seed_listing(
	array(
		'title'      => 'The Williamsburg Hotel',
		'type'       => 'hotel',
		'categories' => array( 'Boutique Hotel' ),
		'features'   => array( 'wifi', 'pool', 'gym', 'ac', 'credit-cards' ),
		'tags'       => array( 'rooftop pool', 'williamsburg', 'trendy', 'nightlife' ),
		'content'    => 'The epicenter of Brooklyn cool. The Williamsburg Hotel features industrial-chic design with floor-to-ceiling windows overlooking the Manhattan skyline. The stunning rooftop pool and bar is the hottest summer destination in the borough. Harvey restaurant serves seasonal New American cuisine. 150 rooms blend raw concrete and warm wood for a distinctly Brooklyn aesthetic.',
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
			'booking_url'     => 'https://thewilliamsburghotel.com/book',
		),
	)
);
wb_listora_seed_review( $h3, 4, 'Rooftop pool is amazing', 'The pool overlooking Manhattan at sunset is worth the stay alone. Room was stylish and comfortable. Great location for exploring Williamsburg bars and restaurants.' );
echo "✓ The Williamsburg Hotel (Hotel, 4-star, \$425/night, 1 review)\n";

// ══════════════════════════════════════
// SUMMARY
// ══════════════════════════════════════

$total = wp_count_posts( 'listora_listing' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$reviews = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$featured = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}search_index WHERE is_featured = 1" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$indexed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}search_index" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$geo = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}geo" );

echo "\n══════════════════════════════════\n";
echo "Demo Content Summary:\n";
echo "  Restaurants: 7\n";
echo "  Real Estate: 5\n";
echo "  Hotels:      3\n";
echo "  Businesses:  5\n";
echo "  ─────────────\n";
echo '  Total:       ' . esc_html( $total->publish ) . " listings\n";
echo '  Reviews:     ' . esc_html( $reviews ) . "\n";
echo '  Featured:    ' . esc_html( $featured ) . "\n";
echo '  Indexed:     ' . esc_html( $indexed ) . "\n";
echo '  Geo:         ' . esc_html( $geo ) . "\n";
echo "══════════════════════════════════\n";
