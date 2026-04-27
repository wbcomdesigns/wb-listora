<?php
/**
 * Classified Demo Pack — 8 classified listings (used items, services for hire).
 *
 * @package WBListora\Demo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-demo-seeder.php';

use WBListora\Demo\Demo_Seeder;

// ── Categories ──

Demo_Seeder::ensure_categories(
	array(
		'electronics' => 'Electronics',
		'furniture'   => 'Furniture',
		'vehicles'    => 'Vehicles',
		'services'    => 'Services for Hire',
		'home-garden' => 'Home & Garden',
		'fashion'     => 'Fashion',
		'sports'      => 'Sports & Outdoors',
		'baby-kids'   => 'Baby & Kids',
	)
);

Demo_Seeder::ensure_features(
	array(
		'negotiable'      => 'Negotiable',
		'firm-price'      => 'Firm Price',
		'pickup-only'     => 'Pickup Only',
		'delivery'        => 'Delivery Available',
		'cash-only'       => 'Cash Only',
		'online-payment'  => 'Online Payment',
		'warranty'        => 'Warranty Included',
		'original-box'    => 'Original Box / Receipt',
	)
);

// ── Listings ──

$listings = array(
	array(
		'title'      => 'Mint-condition iPhone 14 Pro 256GB',
		'type'       => 'classified',
		'categories' => array( 'Electronics' ),
		'featured'   => true,
		'features'   => array( 'firm-price', 'pickup-only', 'cash-only', 'original-box' ),
		'tags'       => array( 'iphone', 'apple', 'unlocked', 'phone' ),
		'content'    => 'Selling my iPhone 14 Pro (Deep Purple, 256GB) in mint condition — used with a case + screen protector since day one. Battery health 96%. Comes with original box, unused EarPods, charging cable, and AppleCare+ active until next May. Unlocked, no carrier ties. Pickup in Brooklyn — bring cash, please. No trades.',
		'address'    => array(
			'address' => 'Williamsburg, Brooklyn, NY',
			'city'    => 'Brooklyn',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'   => array(
				'address' => 'Williamsburg, Brooklyn, NY 11211',
				'lat'     => 40.7081,
				'lng'     => -73.9571,
				'city'    => 'Brooklyn',
				'state'   => 'NY',
				'country' => 'US',
			),
			'price'     => 720,
			'condition' => 'like-new',
		),
	),
	array(
		'title'      => 'Mid-Century Walnut Sideboard',
		'type'       => 'classified',
		'categories' => array( 'Furniture' ),
		'features'   => array( 'negotiable', 'pickup-only' ),
		'tags'       => array( 'mid-century', 'vintage', 'walnut', 'sideboard' ),
		'content'    => 'Authentic 1960s walnut sideboard, 72 inches wide. Three drawers (felt-lined for silver) and two cabinet doors with original sliding glass. Solid construction — only minor surface scratches from normal use. Beautiful piece for a dining room or entryway. Must pickup with a truck — second-floor walkup, we can help carry down.',
		'address'    => array(
			'address' => 'Park Slope, Brooklyn, NY',
			'city'    => 'Brooklyn',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'   => array(
				'address' => 'Park Slope, Brooklyn, NY 11215',
				'lat'     => 40.6710,
				'lng'     => -73.9814,
				'city'    => 'Brooklyn',
				'state'   => 'NY',
				'country' => 'US',
			),
			'price'     => 850,
			'condition' => 'used',
		),
	),
	array(
		'title'      => '2018 Honda Civic LX — 42k miles, single owner',
		'type'       => 'classified',
		'categories' => array( 'Vehicles' ),
		'featured'   => true,
		'features'   => array( 'negotiable', 'cash-only' ),
		'tags'       => array( 'honda', 'civic', 'sedan', 'single-owner' ),
		'content'    => '2018 Honda Civic LX sedan, single owner since new. 42,000 miles, all maintenance records available. Clean Carfax, no accidents. New tires last summer, fresh oil change last month. Bluetooth, backup camera, very fuel efficient. Looking to upgrade to an SUV — that is the only reason to sell. Garage-kept. Available for test drive on weekends.',
		'address'    => array(
			'address' => 'Astoria, Queens, NY',
			'city'    => 'Queens',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'   => array(
				'address' => 'Astoria, Queens, NY 11106',
				'lat'     => 40.7644,
				'lng'     => -73.9235,
				'city'    => 'Queens',
				'state'   => 'NY',
				'country' => 'US',
			),
			'price'     => 14500,
			'condition' => 'used',
		),
	),
	array(
		'title'      => 'Local Handyman — Hourly or Project-Based',
		'type'       => 'classified',
		'categories' => array( 'Services for Hire' ),
		'features'   => array( 'negotiable', 'online-payment' ),
		'tags'       => array( 'handyman', 'repair', 'home-services' ),
		'content'    => 'Reliable local handyman with 12 years experience — TV mounting, furniture assembly, drywall patches, faucet swaps, light electrical, painting, and minor carpentry. Insured and licensed. Most jobs same-week. References available on request. $65/hour with a 1-hour minimum, or flat rate for larger projects (free quote). Serving Manhattan and Brooklyn.',
		'address'    => array(
			'address' => 'Lower East Side, Manhattan, NY',
			'city'    => 'Manhattan',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'   => array(
				'address' => 'Lower East Side, Manhattan, NY 10002',
				'lat'     => 40.7150,
				'lng'     => -73.9843,
				'city'    => 'Manhattan',
				'state'   => 'NY',
				'country' => 'US',
			),
			'price'     => 65,
			'condition' => 'new',
		),
	),
	array(
		'title'      => 'IKEA MALM Dresser — White, 6 Drawers',
		'type'       => 'classified',
		'categories' => array( 'Furniture' ),
		'features'   => array( 'firm-price', 'pickup-only' ),
		'tags'       => array( 'ikea', 'dresser', 'malm', 'white' ),
		'content'    => 'IKEA MALM 6-drawer dresser in white, anchored to wall (per recall guidelines) since purchase. Drawers slide smoothly, all hardware tight, no chips or stains. Great starter dresser. Need it gone by end of next week — moving abroad. Pickup only, ground floor easy carry-out.',
		'address'    => array(
			'address' => 'Long Island City, NY',
			'city'    => 'Queens',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'   => array(
				'address' => 'Long Island City, NY 11101',
				'lat'     => 40.7447,
				'lng'     => -73.9485,
				'city'    => 'Queens',
				'state'   => 'NY',
				'country' => 'US',
			),
			'price'     => 90,
			'condition' => 'used',
		),
	),
	array(
		'title'      => 'Trek Domane SL5 Road Bike — 56cm',
		'type'       => 'classified',
		'categories' => array( 'Sports & Outdoors' ),
		'features'   => array( 'negotiable', 'pickup-only' ),
		'tags'       => array( 'bicycle', 'trek', 'road-bike' ),
		'content'    => 'Trek Domane SL5 carbon road bike, 56cm frame, Shimano 105 groupset. Bought new in 2022, less than 1,200 miles. Recently tuned — chain, cassette, brake pads all in great shape. Includes Bontrager bottle cages and a Wahoo computer mount. Riding less since I started running more. Great endurance bike for long rides.',
		'address'    => array(
			'address' => 'Upper West Side, Manhattan, NY',
			'city'    => 'Manhattan',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'   => array(
				'address' => 'Upper West Side, Manhattan, NY 10024',
				'lat'     => 40.7870,
				'lng'     => -73.9754,
				'city'    => 'Manhattan',
				'state'   => 'NY',
				'country' => 'US',
			),
			'price'     => 1850,
			'condition' => 'like-new',
		),
	),
	array(
		'title'      => 'BabyBjörn Bouncer + Travel Crib Light',
		'type'       => 'classified',
		'categories' => array( 'Baby & Kids' ),
		'features'   => array( 'negotiable', 'delivery' ),
		'tags'       => array( 'baby', 'babybjorn', 'crib', 'bouncer' ),
		'content'    => 'Two BabyBjörn essentials we have outgrown: the Bouncer Bliss (gray) and the Travel Crib Light. Both used gently and machine-washed. Bundled price below — willing to split if needed. Local delivery for $20. Sad to see them go but happy to pass them on to another family.',
		'address'    => array(
			'address' => 'Cobble Hill, Brooklyn, NY',
			'city'    => 'Brooklyn',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'   => array(
				'address' => 'Cobble Hill, Brooklyn, NY 11201',
				'lat'     => 40.6877,
				'lng'     => -73.9956,
				'city'    => 'Brooklyn',
				'state'   => 'NY',
				'country' => 'US',
			),
			'price'     => 240,
			'condition' => 'used',
		),
	),
	array(
		'title'      => 'Vintage Levi’s 501 Selvedge — 32x32',
		'type'       => 'classified',
		'categories' => array( 'Fashion' ),
		'features'   => array( 'firm-price', 'online-payment', 'delivery' ),
		'tags'       => array( 'levis', 'denim', 'vintage', '501' ),
		'content'    => 'Vintage Levi’s 501 selvedge, made in USA, size 32x32. Beautifully broken-in fades on the thighs and the back pockets. No holes, hems unaltered. Selling because I sized down. Photos show real fades, not editing. Shipping included anywhere in the US — no holds.',
		'address'    => array(
			'address' => 'Greenpoint, Brooklyn, NY',
			'city'    => 'Brooklyn',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'   => array(
				'address' => 'Greenpoint, Brooklyn, NY 11222',
				'lat'     => 40.7297,
				'lng'     => -73.9543,
				'city'    => 'Brooklyn',
				'state'   => 'NY',
				'country' => 'US',
			),
			'price'     => 180,
			'condition' => 'used',
		),
	),
);

// ── Seed all listings ──

foreach ( $listings as $idx => $listing_data ) {
	$post_id = Demo_Seeder::seed_listing( $listing_data );
	if ( ! $post_id ) {
		continue;
	}

	// Classified-style "services" — pickup help, shipping upgrade, inspection.
	$services = array(
		array( 'Local Pickup Help', 0, 30, 'I can help you carry the item to your car or van — just bring an extra set of hands or ask in advance.', 'Pickup' ),
		array( 'Shipping (US only)', 25, 0, 'Optional shipping anywhere in the lower 48 via USPS Priority. Tracking number provided.', 'Shipping' ),
	);

	Demo_Seeder::seed_pack_extras( $post_id, 'classified', $idx, $services, array( 'gallery_count' => 3 ) );
}
