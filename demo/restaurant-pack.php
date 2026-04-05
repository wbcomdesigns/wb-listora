<?php
/**
 * Restaurant Demo Pack — 20 restaurant listings across 8 cuisines.
 *
 * @package WBListora\Demo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-demo-seeder.php';

use WBListora\Demo\Demo_Seeder;

// ── Categories ──

Demo_Seeder::ensure_categories(
	array(
		'fine-dining' => 'Fine Dining',
		'casual'      => 'Casual',
		'fast-food'   => 'Fast Food',
		'cafe'        => 'Cafe',
		'bar-grill'   => 'Bar & Grill',
	)
);

Demo_Seeder::ensure_features(
	array(
		'wifi'         => 'WiFi',
		'parking'      => 'Parking',
		'outdoor'      => 'Outdoor Seating',
		'credit-cards' => 'Credit Cards',
		'live-music'   => 'Live Music',
		'pet-friendly' => 'Pet Friendly',
		'wheelchair'   => 'Wheelchair Accessible',
		'delivery'     => 'Delivery',
		'takeout'      => 'Takeout',
		'reservations' => 'Reservations',
	)
);

// ── Listings ──

$listings = array(
	array(
		'title'      => 'The Golden Fork',
		'type'       => 'restaurant',
		'categories' => array( 'Fine Dining' ),
		'featured'   => true,
		'features'   => array( 'wifi', 'parking', 'outdoor', 'credit-cards', 'reservations' ),
		'tags'       => array( 'italian', 'pasta', 'wine bar', 'date night' ),
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
		'reviews'    => array(
			array( 5, 'Absolutely stunning!', 'The handmade pappardelle with wild boar ragu was the best pasta I have ever had. Wine pairing was excellent.' ),
			array( 4, 'Great food, pricey', 'Food quality is outstanding. The truffle risotto melts in your mouth. Only reason for 4 stars is the price.' ),
			array( 5, 'Our go-to Italian', 'We have been coming here monthly for 2 years. Consistency is remarkable. Outdoor patio in summer is magical.' ),
		),
	),
	array(
		'title'      => 'Sakura House',
		'type'       => 'restaurant',
		'categories' => array( 'Fine Dining' ),
		'features'   => array( 'wifi', 'credit-cards', 'reservations' ),
		'tags'       => array( 'japanese', 'sushi', 'ramen', 'omakase' ),
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
			'email'          => 'hello@sakurahouse-nyc.com',
			'cuisine'        => array( 'japanese' ),
			'price_range'    => '$$$',
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
		'reviews'    => array(
			array( 5, 'Best sushi in Midtown', 'The omakase experience was incredible. Chef prepared each piece with precision. Toro was perfection.' ),
			array( 4, 'Fresh and delicious', 'Lunch bento box is unbeatable value. Generous portions. Miso ramen on a cold day is pure comfort.' ),
		),
	),
	array(
		'title'      => 'Casa Miguel Cantina',
		'type'       => 'restaurant',
		'categories' => array( 'Casual' ),
		'featured'   => true,
		'features'   => array( 'parking', 'outdoor', 'live-music', 'credit-cards' ),
		'tags'       => array( 'mexican', 'margaritas', 'tacos', 'live music' ),
		'content'    => 'Vibrant, colorful, and bursting with flavor. Casa Miguel brings the soul of Oaxaca to NYC. Recipes passed down through three generations. Slow-smoked barbacoa, hand-pressed tortillas, and seven house-made salsas. Live mariachi music every Friday and Saturday night. Our margarita menu features 15 unique creations.',
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
			'email'          => 'hola@casamiguel.nyc',
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
		'reviews'    => array(
			array( 5, 'Incredible atmosphere!', 'Mariachi band on Friday made our birthday unforgettable. Mole negro is the best outside Mexico.' ),
			array( 4, 'Authentic and fun', 'Great food, great vibes. Street corn and churros are must-orders. Gets loud on weekends but that is the charm.' ),
		),
	),
	array(
		'title'      => 'Spice Route Kitchen',
		'type'       => 'restaurant',
		'categories' => array( 'Casual' ),
		'features'   => array( 'wifi', 'parking', 'credit-cards', 'delivery' ),
		'tags'       => array( 'indian', 'curry', 'tandoori', 'vegetarian' ),
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
			'website'        => 'https://spiceroutekitchen.com',
			'email'          => 'info@spiceroutekitchen.com',
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
		'reviews'    => array(
			array( 5, 'Best Indian in NYC', 'Butter chicken is life-changing. Garlic naan fresh from tandoor is addictive. Weekend brunch thali is incredible value.' ),
		),
	),
	array(
		'title'      => 'Le Petit Bistro',
		'type'       => 'restaurant',
		'categories' => array( 'Fine Dining' ),
		'features'   => array( 'wifi', 'outdoor', 'credit-cards', 'reservations' ),
		'tags'       => array( 'french', 'bistro', 'brunch', 'wine' ),
		'content'    => 'A slice of Paris on the Upper East Side. Le Petit Bistro serves classic French cuisine in an intimate setting with checkered tablecloths and vintage posters. Our duck confit, steak frites, and creme brulee transport you straight to the Left Bank. Weekend brunch with bottomless mimosas is a neighborhood institution.',
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
			'email'          => 'bonjour@lepetitbistro.nyc',
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
		'reviews'    => array(
			array( 5, 'French perfection', 'The duck confit is crispy outside, tender inside. Creme brulee is the best in the city.' ),
			array( 4, 'Charming spot', 'Feels like dining in a real Parisian bistro. The onion soup gratin is soul-warming.' ),
		),
	),
	array(
		'title'      => 'Bangkok Street Kitchen',
		'type'       => 'restaurant',
		'categories' => array( 'Casual' ),
		'features'   => array( 'wifi', 'credit-cards', 'delivery', 'takeout' ),
		'tags'       => array( 'thai', 'pad thai', 'curry', 'street food' ),
		'content'    => 'Authentic Thai street food brought indoors. Our chefs hail from Bangkok and bring the bold, complex flavors of Thai cooking to every plate. From fiery som tam salad to creamy massaman curry, every dish balances sweet, sour, salty, and spicy. Our pad thai is made in a searing-hot wok the traditional way.',
		'meta'       => array(
			'address'        => array(
				'address'     => '415 9th Avenue, Hell\'s Kitchen, NY 10001',
				'lat'         => 40.7555,
				'lng'         => -73.9970,
				'city'        => 'Hell\'s Kitchen',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10001',
			),
			'phone'          => '(212) 555-0537',
			'website'        => 'https://bangkokstreetkitchen.com',
			'email'          => 'eat@bangkokstreetkitchen.com',
			'cuisine'        => array( 'thai' ),
			'price_range'    => '$$',
			'delivery'       => true,
			'takeout'        => true,
			'reservations'   => 'no',
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
					'close' => '21:30',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Best Thai in the city', 'The green curry is incredible. Perfectly spiced, coconut-rich, and packed with fresh basil. Mango sticky rice is a must.' ),
			array( 4, 'Authentic flavors', 'Pad see ew was exactly like what I had in Bangkok. Fast service, generous portions. Will return.' ),
		),
	),
	array(
		'title'      => 'Flames & Smoke BBQ',
		'type'       => 'restaurant',
		'categories' => array( 'Bar & Grill' ),
		'featured'   => true,
		'features'   => array( 'parking', 'outdoor', 'pet-friendly', 'live-music', 'credit-cards' ),
		'tags'       => array( 'bbq', 'ribs', 'brisket', 'craft beer' ),
		'content'    => 'Low and slow is our motto. Flames & Smoke serves authentic Southern BBQ smoked for up to 18 hours over hickory and applewood. Our pitmaster hails from Austin, Texas and brings true Texas-style brisket to Brooklyn. 24 craft beers on tap and a bourbon selection that would make any Texan proud. Live blues music every Thursday.',
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
			'email'          => 'bbq@flamesandsmoke.com',
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
		'reviews'    => array(
			array( 5, 'Real deal BBQ!', 'As a Texan in NYC, I was skeptical. But this brisket is the real thing. Perfect smoke ring, melt-in-your-mouth tender.' ),
			array( 5, 'Best ribs ever', 'Baby back ribs fall right off the bone. Homemade BBQ sauce is smoky, sweet, and tangy.' ),
		),
	),
	array(
		'title'      => 'Olive & Thyme Mediterranean',
		'type'       => 'restaurant',
		'categories' => array( 'Casual' ),
		'features'   => array( 'wifi', 'outdoor', 'credit-cards' ),
		'tags'       => array( 'mediterranean', 'healthy', 'hummus', 'grilled' ),
		'content'    => 'Fresh, vibrant Mediterranean cuisine celebrating the sun-drenched flavors of Greece, Turkey, and Lebanon. Our house-made hummus, falafel, and shawarma platters are legendary. Everything grilled over charcoal. The rooftop garden provides fresh herbs for every dish. Perfect for health-conscious diners who refuse to compromise on flavor.',
		'meta'       => array(
			'address'        => array(
				'address'     => '198 Smith Street, Carroll Gardens, Brooklyn, NY 11201',
				'lat'         => 40.6835,
				'lng'         => -73.9930,
				'city'        => 'Carroll Gardens',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11201',
			),
			'phone'          => '(718) 555-0289',
			'website'        => 'https://oliveandthyme.nyc',
			'email'          => 'info@oliveandthyme.nyc',
			'cuisine'        => array( 'other' ),
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
			array( 5, 'Heavenly hummus', 'Best hummus in Brooklyn, hands down. The lamb shawarma plate is perfection. Outdoor garden seating is lovely.' ),
			array( 4, 'Fresh and flavorful', 'Great for a healthy lunch. The falafel wrap is crispy and loaded with tahini. Portions are generous.' ),
		),
	),
	array(
		'title'      => 'The Velvet Lounge',
		'type'       => 'restaurant',
		'categories' => array( 'Bar & Grill' ),
		'features'   => array( 'wifi', 'live-music', 'credit-cards' ),
		'tags'       => array( 'cocktails', 'tapas', 'jazz', 'late night' ),
		'content'    => 'Where craft cocktails meet creative tapas. The Velvet Lounge is a speakeasy-inspired gem hidden behind an unmarked door in the East Village. Master mixologists craft bespoke cocktails using house-made syrups and bitters. The tapas menu features globally-inspired small plates perfect for sharing. Live jazz Wednesday through Saturday.',
		'meta'       => array(
			'address'        => array(
				'address'     => '72 East 7th Street, East Village, NY 10003',
				'lat'         => 40.7270,
				'lng'         => -73.9858,
				'city'        => 'East Village',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10003',
			),
			'phone'          => '(212) 555-0741',
			'website'        => 'https://thevelvetlounge.nyc',
			'email'          => 'reservations@thevelvetlounge.nyc',
			'cuisine'        => array( 'american' ),
			'price_range'    => '$$$',
			'delivery'       => false,
			'takeout'        => false,
			'reservations'   => 'online',
			'business_hours' => array(
				array(
					'day'    => 1,
					'closed' => true,
				),
				array(
					'day'   => 2,
					'open'  => '17:00',
					'close' => '01:00',
				),
				array(
					'day'   => 3,
					'open'  => '17:00',
					'close' => '01:00',
				),
				array(
					'day'   => 4,
					'open'  => '17:00',
					'close' => '02:00',
				),
				array(
					'day'   => 5,
					'open'  => '17:00',
					'close' => '02:00',
				),
				array(
					'day'   => 6,
					'open'  => '16:00',
					'close' => '02:00',
				),
				array(
					'day'   => 0,
					'open'  => '16:00',
					'close' => '00:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Hidden gem!', 'The best speakeasy vibe in NYC. Old Fashioned was perfectly crafted. Jazz trio on Saturday was incredible.' ),
			array( 4, 'Excellent cocktails', 'Every drink feels like a masterpiece. Truffle mac and cheese bites were addictive. Hard to find but worth it.' ),
			array( 4, 'Great date spot', 'Dark, moody, romantic. The cocktail menu changes seasonally and always impresses. Tapas portions could be bigger.' ),
		),
	),
	array(
		'title'      => 'Noodle Kingdom',
		'type'       => 'restaurant',
		'categories' => array( 'Casual' ),
		'features'   => array( 'wifi', 'credit-cards', 'delivery', 'takeout' ),
		'tags'       => array( 'chinese', 'noodles', 'dim sum', 'dumplings' ),
		'content'    => 'Hand-pulled noodles made fresh every hour. Watch our noodle masters stretch and fold dough into silky strands right before your eyes. Our Sichuan dan dan noodles have a cult following, and the soup dumplings are legendary. Family recipes from four provinces of China, all under one roof in Chinatown.',
		'meta'       => array(
			'address'        => array(
				'address'     => '56 Mott Street, Chinatown, NY 10013',
				'lat'         => 40.7155,
				'lng'         => -73.9995,
				'city'        => 'Chinatown',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10013',
			),
			'phone'          => '(212) 555-0188',
			'website'        => 'https://noodlekingdom.nyc',
			'email'          => 'eat@noodlekingdom.nyc',
			'cuisine'        => array( 'other' ),
			'price_range'    => '$',
			'delivery'       => true,
			'takeout'        => true,
			'reservations'   => 'no',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '10:30',
					'close' => '22:00',
				),
				array(
					'day'   => 2,
					'open'  => '10:30',
					'close' => '22:00',
				),
				array(
					'day'   => 3,
					'open'  => '10:30',
					'close' => '22:00',
				),
				array(
					'day'   => 4,
					'open'  => '10:30',
					'close' => '22:00',
				),
				array(
					'day'   => 5,
					'open'  => '10:30',
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
					'close' => '22:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Noodle heaven', 'The hand-pulled noodles are mesmerizing to watch and even better to eat. Soup dumplings are perfection.' ),
			array( 5, 'Best value in Chinatown', 'Massive bowls of noodle soup for under $12. Dan dan noodles are addictively spicy. Cash only heads up.' ),
		),
	),
	array(
		'title'      => 'Blue Harbor Seafood',
		'type'       => 'restaurant',
		'categories' => array( 'Fine Dining' ),
		'featured'   => true,
		'features'   => array( 'parking', 'outdoor', 'wheelchair', 'credit-cards', 'reservations' ),
		'tags'       => array( 'seafood', 'waterfront', 'oysters', 'views' ),
		'content'    => 'Overlooking the Hudson River, Blue Harbor delivers the finest ocean-to-table dining. Fish arrives daily from Montauk and Maine. Raw bar features East and West Coast oysters, jumbo shrimp, and our famous lobster tower. Floor-to-ceiling windows provide stunning river views at sunset.',
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
			'email'          => 'dine@blueharborseafood.com',
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
		'reviews'    => array(
			array( 5, 'Sunset dinner was magical', 'Window table, sunset over the river, freshest oysters and perfectly seared sea bass. Worth every penny.' ),
			array( 4, 'Excellent seafood', 'Lobster tower is spectacular for sharing. Service is impeccable. Expensive but quality and setting justify it.' ),
		),
	),
	array(
		'title'      => 'Pho Saigon',
		'type'       => 'restaurant',
		'categories' => array( 'Casual' ),
		'features'   => array( 'wifi', 'credit-cards', 'delivery', 'takeout' ),
		'tags'       => array( 'vietnamese', 'pho', 'banh mi', 'soup' ),
		'content'    => 'Authentic Vietnamese cuisine in the heart of the Lower East Side. Our pho broth simmers for 24 hours, producing a rich, aromatic soup that warms the soul. Banh mi sandwiches on house-baked baguettes. Summer rolls with peanut sauce, crispy spring rolls, and lemongrass chicken are crowd favorites.',
		'meta'       => array(
			'address'        => array(
				'address'     => '148 Orchard Street, Lower East Side, NY 10002',
				'lat'         => 40.7195,
				'lng'         => -73.9890,
				'city'        => 'Lower East Side',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10002',
			),
			'phone'          => '(212) 555-0366',
			'website'        => 'https://phosaigon.nyc',
			'email'          => 'hello@phosaigon.nyc',
			'cuisine'        => array( 'other' ),
			'price_range'    => '$',
			'delivery'       => true,
			'takeout'        => true,
			'reservations'   => 'no',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '10:00',
					'close' => '22:00',
				),
				array(
					'day'   => 2,
					'open'  => '10:00',
					'close' => '22:00',
				),
				array(
					'day'   => 3,
					'open'  => '10:00',
					'close' => '22:00',
				),
				array(
					'day'   => 4,
					'open'  => '10:00',
					'close' => '22:00',
				),
				array(
					'day'   => 5,
					'open'  => '10:00',
					'close' => '23:00',
				),
				array(
					'day'   => 6,
					'open'  => '10:00',
					'close' => '23:00',
				),
				array(
					'day'   => 0,
					'open'  => '11:00',
					'close' => '21:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Soul-warming pho', 'The rare beef pho is absolutely perfect. Rich broth, tender meat, fresh herbs. Best comfort food in the neighborhood.' ),
		),
	),
	array(
		'title'      => 'Bella Luna Pizzeria',
		'type'       => 'restaurant',
		'categories' => array( 'Fast Food' ),
		'features'   => array( 'wifi', 'credit-cards', 'delivery', 'takeout' ),
		'tags'       => array( 'pizza', 'italian', 'slice', 'late night' ),
		'content'    => 'New York-style pizza at its finest. Our dough ferments for 72 hours, creating that perfect chewy-crispy crust. Hand-crushed San Marzano tomatoes, fresh mozzarella, and creative toppings. Open until 3 AM on weekends for the late-night crowd. By the slice or whole pie, this is the pizza you have been craving.',
		'meta'       => array(
			'address'        => array(
				'address'     => '301 Bleecker Street, West Village, NY 10014',
				'lat'         => 40.7320,
				'lng'         => -74.0038,
				'city'        => 'West Village',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10014',
			),
			'phone'          => '(212) 555-0924',
			'website'        => 'https://bellaluna.pizza',
			'email'          => 'order@bellaluna.pizza',
			'cuisine'        => array( 'italian' ),
			'price_range'    => '$',
			'delivery'       => true,
			'takeout'        => true,
			'reservations'   => 'no',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '11:00',
					'close' => '23:00',
				),
				array(
					'day'   => 2,
					'open'  => '11:00',
					'close' => '23:00',
				),
				array(
					'day'   => 3,
					'open'  => '11:00',
					'close' => '23:00',
				),
				array(
					'day'   => 4,
					'open'  => '11:00',
					'close' => '00:00',
				),
				array(
					'day'   => 5,
					'open'  => '11:00',
					'close' => '03:00',
				),
				array(
					'day'   => 6,
					'open'  => '11:00',
					'close' => '03:00',
				),
				array(
					'day'   => 0,
					'open'  => '12:00',
					'close' => '22:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Best slice in the Village', 'Perfectly charred crust, tangy sauce, gooey cheese. The white pie with truffle oil is next level.' ),
			array( 4, 'Great late-night option', 'Nothing beats a hot slice at 2 AM. Consistently good quality. Margherita is classic perfection.' ),
		),
	),
	array(
		'title'      => 'Seoul Garden',
		'type'       => 'restaurant',
		'categories' => array( 'Casual' ),
		'features'   => array( 'wifi', 'parking', 'credit-cards' ),
		'tags'       => array( 'korean', 'bbq', 'kimchi', 'bibimbap' ),
		'content'    => 'Authentic Korean BBQ where you are the chef. Premium USDA Prime beef, marinated short ribs, and pork belly cooked at your table over charcoal grills. Banchan spread features 12 housemade side dishes. Our stone pot bibimbap is a crowd favorite, and the soju cocktail menu adds a modern twist to Korean dining.',
		'meta'       => array(
			'address'        => array(
				'address'     => '329 West 32nd Street, Koreatown, NY 10001',
				'lat'         => 40.7490,
				'lng'         => -73.9920,
				'city'        => 'Koreatown',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10001',
			),
			'phone'          => '(212) 555-0815',
			'website'        => 'https://seoulgarden.nyc',
			'email'          => 'info@seoulgarden.nyc',
			'cuisine'        => array( 'other' ),
			'price_range'    => '$$',
			'delivery'       => false,
			'takeout'        => true,
			'reservations'   => 'yes',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '11:30',
					'close' => '23:00',
				),
				array(
					'day'   => 2,
					'open'  => '11:30',
					'close' => '23:00',
				),
				array(
					'day'   => 3,
					'open'  => '11:30',
					'close' => '23:00',
				),
				array(
					'day'   => 4,
					'open'  => '11:30',
					'close' => '23:00',
				),
				array(
					'day'   => 5,
					'open'  => '11:30',
					'close' => '01:00',
				),
				array(
					'day'   => 6,
					'open'  => '11:30',
					'close' => '01:00',
				),
				array(
					'day'   => 0,
					'open'  => '12:00',
					'close' => '22:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Best Korean BBQ!', 'The galbi is incredible. Perfectly marinated, melt-in-your-mouth tender. 12 banchan side dishes are all delicious.' ),
			array( 4, 'Fun dining experience', 'Cooking at your table is always a blast. Pork belly was crispy and flavorful. Great for groups.' ),
		),
	),
	array(
		'title'      => 'Sunrise Breakfast Club',
		'type'       => 'restaurant',
		'categories' => array( 'Cafe' ),
		'features'   => array( 'wifi', 'outdoor', 'pet-friendly', 'credit-cards' ),
		'tags'       => array( 'brunch', 'breakfast', 'pancakes', 'coffee' ),
		'content'    => 'The ultimate brunch destination. Sunrise Breakfast Club serves all-day breakfast with creative twists on classics. Our fluffy ricotta pancakes, avocado toast with poached eggs, and chicken and waffles have lines down the block on weekends. Locally roasted coffee, fresh-squeezed juices, and boozy brunch cocktails.',
		'meta'       => array(
			'address'        => array(
				'address'     => '88 Bedford Avenue, Williamsburg, Brooklyn, NY 11249',
				'lat'         => 40.7140,
				'lng'         => -73.9605,
				'city'        => 'Williamsburg',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11249',
			),
			'phone'          => '(718) 555-0177',
			'website'        => 'https://sunrisebreakfastclub.com',
			'email'          => 'morning@sunrisebreakfastclub.com',
			'cuisine'        => array( 'american' ),
			'price_range'    => '$$',
			'delivery'       => true,
			'takeout'        => true,
			'reservations'   => 'no',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '07:00',
					'close' => '16:00',
				),
				array(
					'day'   => 2,
					'open'  => '07:00',
					'close' => '16:00',
				),
				array(
					'day'   => 3,
					'open'  => '07:00',
					'close' => '16:00',
				),
				array(
					'day'   => 4,
					'open'  => '07:00',
					'close' => '16:00',
				),
				array(
					'day'   => 5,
					'open'  => '07:00',
					'close' => '16:00',
				),
				array(
					'day'   => 6,
					'open'  => '08:00',
					'close' => '17:00',
				),
				array(
					'day'   => 0,
					'open'  => '08:00',
					'close' => '17:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Brunch perfection', 'The ricotta pancakes are fluffy clouds of happiness. Avocado toast is Instagrammable and delicious. Worth the wait.' ),
			array( 4, 'Great coffee spot', 'Best latte in Williamsburg. Breakfast burrito is massive and packed with flavor. Outdoor seating is nice.' ),
		),
	),
	array(
		'title'      => 'Espresso & Co. Coffee House',
		'type'       => 'restaurant',
		'categories' => array( 'Cafe' ),
		'features'   => array( 'wifi', 'credit-cards', 'takeout' ),
		'tags'       => array( 'coffee', 'pastries', 'workspace', 'specialty' ),
		'content'    => 'Third-wave coffee done right. Espresso & Co. roasts beans in-house weekly, sourcing single-origin coffees from Ethiopia, Colombia, and Guatemala. Our baristas compete nationally and craft beautiful latte art on every cup. Freshly baked pastries from a local bakery, plus avocado toast, acai bowls, and sandwiches. The best spot to work or study in SoHo.',
		'meta'       => array(
			'address'        => array(
				'address'     => '145 Sullivan Street, SoHo, NY 10012',
				'lat'         => 40.7260,
				'lng'         => -74.0000,
				'city'        => 'SoHo',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10012',
			),
			'phone'          => '(212) 555-0433',
			'website'        => 'https://espressoandco.com',
			'email'          => 'hello@espressoandco.com',
			'cuisine'        => array( 'other' ),
			'price_range'    => '$$',
			'delivery'       => false,
			'takeout'        => true,
			'reservations'   => 'no',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '06:30',
					'close' => '19:00',
				),
				array(
					'day'   => 2,
					'open'  => '06:30',
					'close' => '19:00',
				),
				array(
					'day'   => 3,
					'open'  => '06:30',
					'close' => '19:00',
				),
				array(
					'day'   => 4,
					'open'  => '06:30',
					'close' => '19:00',
				),
				array(
					'day'   => 5,
					'open'  => '06:30',
					'close' => '19:00',
				),
				array(
					'day'   => 6,
					'open'  => '07:00',
					'close' => '18:00',
				),
				array(
					'day'   => 0,
					'open'  => '07:00',
					'close' => '18:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Best coffee in SoHo', 'The pour-over Ethiopian Yirgacheffe is exceptional. Great Wi-Fi, plenty of outlets. My go-to work spot.' ),
		),
	),
	array(
		'title'      => 'Taco Loco Express',
		'type'       => 'restaurant',
		'categories' => array( 'Fast Food' ),
		'features'   => array( 'credit-cards', 'delivery', 'takeout' ),
		'tags'       => array( 'tacos', 'burritos', 'quick', 'affordable' ),
		'content'    => 'Fast, fresh, and insanely flavorful. Taco Loco serves street-style tacos, loaded burritos, and crispy quesadillas made to order in under 5 minutes. Choice of carne asada, al pastor, carnitas, chicken, or veggie. House-made guacamole and three hot sauces. The best quick meal in Midtown for under ten dollars.',
		'meta'       => array(
			'address'        => array(
				'address'     => '478 7th Avenue, Midtown, NY 10018',
				'lat'         => 40.7520,
				'lng'         => -73.9895,
				'city'        => 'Midtown',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10018',
			),
			'phone'          => '(212) 555-0602',
			'email'          => 'order@tacoloco.nyc',
			'cuisine'        => array( 'mexican' ),
			'price_range'    => '$',
			'delivery'       => true,
			'takeout'        => true,
			'reservations'   => 'no',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '10:00',
					'close' => '23:00',
				),
				array(
					'day'   => 2,
					'open'  => '10:00',
					'close' => '23:00',
				),
				array(
					'day'   => 3,
					'open'  => '10:00',
					'close' => '23:00',
				),
				array(
					'day'   => 4,
					'open'  => '10:00',
					'close' => '23:00',
				),
				array(
					'day'   => 5,
					'open'  => '10:00',
					'close' => '02:00',
				),
				array(
					'day'   => 6,
					'open'  => '10:00',
					'close' => '02:00',
				),
				array(
					'day'   => 0,
					'open'  => '11:00',
					'close' => '22:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Best quick tacos!', 'Al pastor tacos are loaded and only $3 each. Fresh guac is a must-add. Perfect lunch break spot.' ),
			array( 4, 'Great value', 'Massive burrito for $8. The carnitas are perfectly seasoned. Fast service even during lunch rush.' ),
		),
	),
	array(
		'title'      => 'Kyoto Ramen Bar',
		'type'       => 'restaurant',
		'categories' => array( 'Casual' ),
		'features'   => array( 'credit-cards', 'takeout' ),
		'tags'       => array( 'ramen', 'japanese', 'noodles', 'broth' ),
		'content'    => 'Rich, soul-warming ramen crafted with obsessive attention to detail. Our tonkotsu broth simmers for 18 hours, extracting every ounce of flavor from premium pork bones. Each bowl is assembled with precision: perfectly springy noodles, chashu pork sliced to order, marinated soft-boiled egg, and crispy garlic chips. Vegetarian miso option available.',
		'meta'       => array(
			'address'        => array(
				'address'     => '234 East 9th Street, East Village, NY 10003',
				'lat'         => 40.7290,
				'lng'         => -73.9870,
				'city'        => 'East Village',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10003',
			),
			'phone'          => '(212) 555-0876',
			'website'        => 'https://kyotoramenbar.com',
			'email'          => 'slurp@kyotoramenbar.com',
			'cuisine'        => array( 'japanese' ),
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
					'close' => '22:30',
				),
				array(
					'day'   => 5,
					'open'  => '11:30',
					'close' => '23:00',
				),
				array(
					'day'   => 6,
					'open'  => '11:30',
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
			array( 5, 'Ramen perfection', 'The tonkotsu broth is incredibly rich and creamy. Chashu pork melts in your mouth. Best ramen I have had outside Japan.' ),
			array( 4, 'Cozy noodle spot', 'Spicy miso ramen with extra noodles is my go-to. Small space so expect a wait on weekends.' ),
		),
	),
	array(
		'title'      => 'Amalfi Coast Italian',
		'type'       => 'restaurant',
		'categories' => array( 'Fine Dining' ),
		'featured'   => true,
		'features'   => array( 'wifi', 'outdoor', 'parking', 'credit-cards', 'reservations' ),
		'tags'       => array( 'italian', 'seafood', 'pasta', 'wine' ),
		'content'    => 'Transport yourself to the Italian coastline. Amalfi Coast brings the fresh seafood and sun-drenched flavors of southern Italy to Manhattan. Our chef trained in Positano and creates stunning dishes like branzino al limone, linguine alle vongole, and osso buco. The wine cellar houses over 400 Italian labels. Private dining room available for events.',
		'meta'       => array(
			'address'        => array(
				'address'     => '654 Columbus Avenue, Upper West Side, NY 10025',
				'lat'         => 40.7920,
				'lng'         => -73.9680,
				'city'        => 'Upper West Side',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10025',
			),
			'phone'          => '(212) 555-0755',
			'website'        => 'https://amalficoastnyc.com',
			'email'          => 'reservations@amalficoastnyc.com',
			'cuisine'        => array( 'italian' ),
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
					'open'  => '17:00',
					'close' => '23:00',
				),
				array(
					'day'   => 6,
					'open'  => '16:00',
					'close' => '23:00',
				),
				array(
					'day'   => 0,
					'open'  => '16:00',
					'close' => '21:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Stunning Italian', 'The branzino was the best fish I have ever had. Wine list is extraordinary. Special occasion worthy.' ),
			array( 5, 'Unforgettable dinner', 'Linguine alle vongole was packed with fresh clams. Tiramisu is a dream. Impeccable service.' ),
			array( 4, 'Beautiful restaurant', 'The ambiance is incredible. Food is excellent but on the pricey side. Reserve the outdoor terrace in summer.' ),
		),
	),
	array(
		'title'      => 'Havana Social Club',
		'type'       => 'restaurant',
		'categories' => array( 'Bar & Grill' ),
		'features'   => array( 'wifi', 'outdoor', 'live-music', 'credit-cards' ),
		'tags'       => array( 'cuban', 'mojitos', 'salsa', 'latin' ),
		'content'    => 'A slice of Old Havana in the heart of Greenwich Village. Havana Social Club serves authentic Cuban cuisine with live salsa music every weekend. Our famous Cubano sandwich, ropa vieja, and tostones transport you straight to the Malecon. The mojito bar features 12 rum varieties and handmade cocktails. Outdoor patio with tropical plants and string lights.',
		'meta'       => array(
			'address'        => array(
				'address'     => '182 Bleecker Street, Greenwich Village, NY 10012',
				'lat'         => 40.7290,
				'lng'         => -74.0000,
				'city'        => 'Greenwich Village',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10012',
			),
			'phone'          => '(212) 555-0694',
			'website'        => 'https://havanasocialclub.nyc',
			'email'          => 'hola@havanasocialclub.nyc',
			'cuisine'        => array( 'other' ),
			'price_range'    => '$$',
			'delivery'       => false,
			'takeout'        => true,
			'reservations'   => 'yes',
			'business_hours' => array(
				array(
					'day'   => 1,
					'open'  => '16:00',
					'close' => '23:00',
				),
				array(
					'day'   => 2,
					'open'  => '16:00',
					'close' => '23:00',
				),
				array(
					'day'   => 3,
					'open'  => '16:00',
					'close' => '23:00',
				),
				array(
					'day'   => 4,
					'open'  => '16:00',
					'close' => '00:00',
				),
				array(
					'day'   => 5,
					'open'  => '16:00',
					'close' => '01:00',
				),
				array(
					'day'   => 6,
					'open'  => '12:00',
					'close' => '01:00',
				),
				array(
					'day'   => 0,
					'open'  => '12:00',
					'close' => '22:00',
				),
			),
		),
		'reviews'    => array(
			array( 5, 'Incredible vibe!', 'The live salsa band had everyone dancing. Mojitos are the real deal. Ropa vieja was melt-in-your-mouth perfection.' ),
			array( 4, 'Fun night out', 'Great atmosphere with the Cuban music and tropical decor. Cubano sandwich is massive and delicious. Patio is lovely.' ),
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
