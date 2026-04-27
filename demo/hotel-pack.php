<?php
/**
 * Hotel Demo Pack — 20 hotel listings from budget to luxury.
 *
 * @package WBListora\Demo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-demo-seeder.php';

use WBListora\Demo\Demo_Seeder;

// ── Categories ──

Demo_Seeder::ensure_categories(
	array(
		'luxury'   => 'Luxury',
		'boutique' => 'Boutique',
		'business' => 'Business',
		'budget'   => 'Budget',
		'resort'   => 'Resort',
	)
);

Demo_Seeder::ensure_features(
	array(
		'wifi'       => 'WiFi',
		'pool'       => 'Pool',
		'spa'        => 'Spa',
		'gym'        => 'Gym',
		'restaurant' => 'Restaurant',
		'bar'        => 'Bar',
		'parking'    => 'Parking',
		'ac'         => 'Air Conditioning',
		'concierge'  => 'Concierge',
		'wheelchair' => 'Wheelchair Accessible',
	)
);

// ── Listings ──

$listings = array(
	// ── Luxury ──
	array(
		'title'      => 'The Greenwich Hotel',
		'type'       => 'hotel',
		'categories' => array( 'Luxury' ),
		'featured'   => true,
		'features'   => array( 'wifi', 'pool', 'spa', 'gym', 'restaurant', 'bar', 'concierge', 'parking' ),
		'tags'       => array( 'tribeca', 'luxury', 'spa', '5-star' ),
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
			'website'         => 'https://greenwichhotel.com',
			'email'           => 'reservations@greenwichhotel.com',
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
		'reviews'    => array(
			array( 5, 'Pure luxury', 'Every detail is thoughtfully considered. Hand-painted ceiling, most comfortable bed ever. Shibui Spa pool is otherworldly.' ),
			array( 5, 'Perfection', 'The town car to dinner, the welcome champagne, the rooftop yoga. Everything whispers elegance without pretension.' ),
			array( 5, 'Best hotel in NYC', 'Third stay and it only gets better. Staff remembers your preferences. The courtyard at sunset is magical.' ),
		),
	),
	array(
		'title'      => 'The Ritz-Carlton Central Park',
		'type'       => 'hotel',
		'categories' => array( 'Luxury' ),
		'featured'   => true,
		'features'   => array( 'wifi', 'spa', 'gym', 'restaurant', 'bar', 'concierge', 'wheelchair' ),
		'tags'       => array( 'central park', 'luxury', 'iconic', 'views' ),
		'content'    => 'Commanding views of Central Park from one of Manhattan\'s most prestigious addresses. 253 elegantly appointed rooms and suites with marble bathrooms, Egyptian cotton linens, and Ritz-Carlton signature service. The Club Lounge offers five daily culinary presentations. La Prairie spa treatments and a state-of-the-art fitness center complete the experience.',
		'meta'       => array(
			'address'         => array(
				'address'     => '50 Central Park South, Midtown, NY 10019',
				'lat'         => 40.7659,
				'lng'         => -73.9755,
				'city'        => 'Midtown',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10019',
			),
			'phone'           => '(212) 555-0340',
			'website'         => 'https://ritzcarltoncp.com',
			'email'           => 'stay@ritzcarltoncp.com',
			'star_rating'     => '5',
			'price_per_night' => array(
				'amount'   => 1250,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '12:00',
			'rooms'           => 253,
			'booking_url'     => 'https://ritzcarltoncp.com/reservations',
		),
		'reviews'    => array(
			array( 5, 'Iconic luxury', 'Park view suite was breathtaking. Service is in a class of its own. Club Lounge evening cocktails were superb.' ),
			array( 4, 'World-class service', 'Staff anticipated every need before we even asked. The only downside is you never want to leave.' ),
		),
	),
	array(
		'title'      => 'The Plaza Hotel',
		'type'       => 'hotel',
		'categories' => array( 'Luxury' ),
		'features'   => array( 'wifi', 'spa', 'gym', 'restaurant', 'bar', 'concierge', 'parking', 'wheelchair' ),
		'tags'       => array( 'historic', 'landmark', 'fifth avenue', 'grand' ),
		'content'    => 'An iconic New York landmark since 1907. The Plaza offers 282 luxuriously appointed rooms and suites with 24-karat gold-plated fixtures, hand-woven carpets, and crystal chandeliers. Home to The Palm Court, The Todd English Food Hall, and the legendary Oak Room Bar. Guerlain Spa provides bespoke treatments. The ultimate address in New York City.',
		'meta'       => array(
			'address'         => array(
				'address'     => '768 5th Avenue, Midtown, NY 10019',
				'lat'         => 40.7645,
				'lng'         => -73.9745,
				'city'        => 'Midtown',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10019',
			),
			'phone'           => '(212) 555-0159',
			'website'         => 'https://theplazany.com',
			'email'           => 'info@theplazany.com',
			'star_rating'     => '5',
			'price_per_night' => array(
				'amount'   => 1100,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '12:00',
			'rooms'           => 282,
			'booking_url'     => 'https://theplazany.com/book',
		),
		'reviews'    => array(
			array( 5, 'Living history', 'Staying at The Plaza is a bucket-list experience. The grandeur is unmatched. Palm Court afternoon tea is a must.' ),
			array( 4, 'Iconic but showing age', 'The lobby and public spaces are stunning. Room was beautifully decorated. Some fixtures could use updating but the charm compensates.' ),
		),
	),
	array(
		'title'      => 'Four Seasons Downtown',
		'type'       => 'hotel',
		'categories' => array( 'Luxury' ),
		'features'   => array( 'wifi', 'pool', 'spa', 'gym', 'restaurant', 'bar', 'concierge' ),
		'tags'       => array( 'downtown', 'modern luxury', 'rooftop', 'contemporary' ),
		'content'    => 'Sleek, modern luxury in the heart of Tribeca. 189 rooms designed by Yabu Pushelberg with custom furnishings and floor-to-ceiling windows. CUT by Wolfgang Puck serves prime steaks with skyline views. The rooftop bar offers craft cocktails and panoramic city vistas. Indoor pool with underwater music system.',
		'meta'       => array(
			'address'         => array(
				'address'     => '27 Barclay Street, Tribeca, NY 10007',
				'lat'         => 40.7118,
				'lng'         => -74.0078,
				'city'        => 'Tribeca',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10007',
			),
			'phone'           => '(212) 555-0488',
			'website'         => 'https://fsdowntown.com',
			'email'           => 'concierge@fsdowntown.com',
			'star_rating'     => '5',
			'price_per_night' => array(
				'amount'   => 950,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '12:00',
			'rooms'           => 189,
			'booking_url'     => 'https://fsdowntown.com/reservations',
		),
		'reviews'    => array(
			array( 5, 'Modern excellence', 'The pool with underwater music is an experience. CUT steakhouse is phenomenal. Room was immaculate.' ),
		),
	),
	// ── Boutique ──
	array(
		'title'      => 'Brooklyn Bridge Inn',
		'type'       => 'hotel',
		'categories' => array( 'Boutique' ),
		'features'   => array( 'wifi', 'restaurant', 'bar' ),
		'tags'       => array( 'dumbo', 'brooklyn bridge', 'charming', 'b&b' ),
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
			'website'         => 'https://brooklynbridgeinn.com',
			'email'           => 'stay@brooklynbridgeinn.com',
			'star_rating'     => '4',
			'price_per_night' => array(
				'amount'   => 275,
				'currency' => 'USD',
			),
			'check_in_time'   => '14:00',
			'check_out_time'  => '11:00',
			'rooms'           => 12,
			'booking_url'     => 'https://brooklynbridgeinn.com/book',
		),
		'reviews'    => array(
			array( 5, 'Most charming B&B', 'Rooftop view of Brooklyn Bridge at sunset took my breath away. Homemade breakfast was delicious. Hosts made us feel like family.' ),
			array( 4, 'Great location', 'Perfect base for exploring Brooklyn and Manhattan. Rooms are small but beautifully decorated. Fresh croissants at breakfast were heavenly.' ),
		),
	),
	array(
		'title'      => 'The Williamsburg Hotel',
		'type'       => 'hotel',
		'categories' => array( 'Boutique' ),
		'features'   => array( 'wifi', 'pool', 'gym', 'restaurant', 'bar', 'ac' ),
		'tags'       => array( 'rooftop pool', 'williamsburg', 'trendy', 'nightlife' ),
		'content'    => 'The epicenter of Brooklyn cool. The Williamsburg Hotel features industrial-chic design with floor-to-ceiling windows overlooking the Manhattan skyline. The stunning rooftop pool and bar is the hottest summer destination in the borough. Harvey restaurant serves seasonal New American cuisine. 150 rooms blend raw concrete and warm wood.',
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
			'website'         => 'https://thewilliamsburghotel.com',
			'email'           => 'hello@thewilliamsburghotel.com',
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
		'reviews'    => array(
			array( 4, 'Rooftop pool is amazing', 'The pool overlooking Manhattan at sunset is worth the stay alone. Room was stylish. Great location for Williamsburg nightlife.' ),
			array( 4, 'Trendy and fun', 'Perfect for a weekend getaway. The bar scene is vibrant. Room had great design details. Shower was incredible.' ),
		),
	),
	array(
		'title'      => 'The Nolitan Hotel',
		'type'       => 'hotel',
		'categories' => array( 'Boutique' ),
		'features'   => array( 'wifi', 'gym', 'restaurant', 'ac' ),
		'tags'       => array( 'nolita', 'intimate', 'neighborhood', 'design' ),
		'content'    => 'An intimate 57-room boutique hotel in the heart of NoLIta, one of Manhattan\'s most charming neighborhoods. Rooms feature floor-to-ceiling windows, Italian linens, and custom Malin+Goetz amenities. The lobby cafe serves locally roasted coffee and light bites. Walk to the best shopping, galleries, and restaurants in downtown Manhattan.',
		'meta'       => array(
			'address'         => array(
				'address'     => '30 Kenmare Street, NoLIta, NY 10012',
				'lat'         => 40.7218,
				'lng'         => -73.9955,
				'city'        => 'NoLIta',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10012',
			),
			'phone'           => '(212) 555-0743',
			'website'         => 'https://nolitanhotel.com',
			'email'           => 'info@nolitanhotel.com',
			'star_rating'     => '4',
			'price_per_night' => array(
				'amount'   => 320,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '11:00',
			'rooms'           => 57,
			'booking_url'     => 'https://nolitanhotel.com/reserve',
		),
		'reviews'    => array(
			array( 5, 'Perfect neighborhood hotel', 'Location is unbeatable for shopping and dining. Room was small but beautifully designed. Staff gave amazing restaurant recommendations.' ),
			array( 4, 'Charming and chic', 'Love the NoLIta vibe. Clean, stylish rooms. The lobby coffee is excellent. Wish there was a rooftop.' ),
		),
	),
	array(
		'title'      => 'Hotel Indigo Lower East Side',
		'type'       => 'hotel',
		'categories' => array( 'Boutique' ),
		'features'   => array( 'wifi', 'gym', 'bar', 'ac', 'restaurant' ),
		'tags'       => array( 'lower east side', 'rooftop bar', 'artistic', 'neighborhood' ),
		'content'    => 'A boutique hotel that captures the artistic energy of the Lower East Side. 293 rooms decorated with neighborhood-inspired artwork and photography. The rooftop bar, Mr. Purple, is one of the hottest nightlife destinations in Manhattan with sweeping skyline views. The building itself is a striking modern tower designed by a Pritzker Prize-winning architect.',
		'meta'       => array(
			'address'         => array(
				'address'     => '171 Ludlow Street, Lower East Side, NY 10002',
				'lat'         => 40.7210,
				'lng'         => -73.9880,
				'city'        => 'Lower East Side',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10002',
			),
			'phone'           => '(212) 555-0592',
			'website'         => 'https://hotelindigoles.com',
			'email'           => 'info@hotelindigoles.com',
			'star_rating'     => '4',
			'price_per_night' => array(
				'amount'   => 285,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '11:00',
			'rooms'           => 293,
			'booking_url'     => 'https://hotelindigoles.com/book',
		),
		'reviews'    => array(
			array( 4, 'Rooftop bar is a must', 'Mr. Purple rooftop was the highlight of our trip. Room was comfortable with cool artwork. Great neighborhood for food.' ),
		),
	),
	// ── Business ──
	array(
		'title'      => 'Manhattan Business Suites',
		'type'       => 'hotel',
		'categories' => array( 'Business' ),
		'features'   => array( 'wifi', 'gym', 'restaurant', 'ac', 'concierge', 'wheelchair' ),
		'tags'       => array( 'business', 'midtown', 'conference', 'corporate' ),
		'content'    => 'Purpose-built for the business traveler. 320 spacious suites with separate living and work areas, ergonomic desks, dual monitors, and high-speed internet. 8 meeting rooms with full AV equipment. Executive lounge with complimentary breakfast and evening cocktails. Located two blocks from Grand Central Terminal with easy access to all major business districts.',
		'meta'       => array(
			'address'         => array(
				'address'     => '150 East 42nd Street, Midtown, NY 10017',
				'lat'         => 40.7522,
				'lng'         => -73.9760,
				'city'        => 'Midtown',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10017',
			),
			'phone'           => '(212) 555-0267',
			'website'         => 'https://manhattanbusinesssuites.com',
			'email'           => 'corporate@manhattanbusinesssuites.com',
			'star_rating'     => '4',
			'price_per_night' => array(
				'amount'   => 385,
				'currency' => 'USD',
			),
			'check_in_time'   => '14:00',
			'check_out_time'  => '12:00',
			'rooms'           => 320,
			'booking_url'     => 'https://manhattanbusinesssuites.com/book',
		),
		'reviews'    => array(
			array( 4, 'Perfect for business trips', 'The work desk setup is the best I have seen in any hotel. Meeting rooms are well-equipped. Executive lounge saves time on breakfast.' ),
			array( 4, 'Reliable and professional', 'Everything works as it should. Great WiFi, quiet rooms, efficient staff. My go-to hotel for NYC business trips.' ),
		),
	),
	array(
		'title'      => 'Financial District Hotel & Conference Center',
		'type'       => 'hotel',
		'categories' => array( 'Business' ),
		'features'   => array( 'wifi', 'gym', 'restaurant', 'bar', 'ac', 'concierge', 'wheelchair', 'parking' ),
		'tags'       => array( 'financial district', 'conference center', 'corporate events', 'wall street' ),
		'content'    => 'The premier conference hotel in lower Manhattan. 450 rooms with modern business amenities. 25,000 sq ft conference center with a 500-seat ballroom, 12 breakout rooms, and state-of-the-art AV. Business center open 24 hours. Two restaurants, a lobby bar, and a rooftop terrace. Walking distance to Wall Street, World Trade Center, and the Oculus.',
		'meta'       => array(
			'address'         => array(
				'address'     => '8 Stone Street, Financial District, NY 10004',
				'lat'         => 40.7045,
				'lng'         => -74.0110,
				'city'        => 'Financial District',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10004',
			),
			'phone'           => '(212) 555-0418',
			'website'         => 'https://fidihotel.com',
			'email'           => 'events@fidihotel.com',
			'star_rating'     => '4',
			'price_per_night' => array(
				'amount'   => 350,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '12:00',
			'rooms'           => 450,
			'booking_url'     => 'https://fidihotel.com/reservations',
		),
		'reviews'    => array(
			array( 4, 'Great for conferences', 'Hosted our company retreat here. Conference facilities are excellent. Breakout rooms had everything we needed. Food was surprisingly good.' ),
			array( 3.5, 'Good business hotel', 'Rooms are functional if not exciting. Location is convenient for Wall Street meetings. Lobby bar is nice for after-work drinks.' ),
		),
	),
	array(
		'title'      => 'Times Square Executive Hotel',
		'type'       => 'hotel',
		'categories' => array( 'Business' ),
		'features'   => array( 'wifi', 'gym', 'restaurant', 'ac', 'wheelchair' ),
		'tags'       => array( 'times square', 'central', 'theater district', 'convenient' ),
		'content'    => 'Centrally located business hotel in the heart of Times Square. 280 rooms with soundproofed windows, high-speed WiFi, and in-room Keurig machines. Ideal for business travelers who need to be in the center of everything. Walking distance to Penn Station, Port Authority, and the Theater District. Corporate rates available for extended stays.',
		'meta'       => array(
			'address'         => array(
				'address'     => '234 West 42nd Street, Times Square, NY 10036',
				'lat'         => 40.7572,
				'lng'         => -73.9890,
				'city'        => 'Times Square',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10036',
			),
			'phone'           => '(212) 555-0533',
			'website'         => 'https://tsexecutivehotel.com',
			'email'           => 'info@tsexecutivehotel.com',
			'star_rating'     => '3',
			'price_per_night' => array(
				'amount'   => 245,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '11:00',
			'rooms'           => 280,
			'booking_url'     => 'https://tsexecutivehotel.com/book',
		),
		'reviews'    => array(
			array( 3.5, 'Central but noisy', 'Location cannot be beaten. Soundproofing helps but Times Square is Times Square. Room was clean and functional.' ),
			array( 4, 'Great value for location', 'Compared to other Times Square hotels, this is a great deal. Rooms are modern and comfortable. Gym is small but adequate.' ),
		),
	),
	array(
		'title'      => 'Hudson Yards Business Hotel',
		'type'       => 'hotel',
		'categories' => array( 'Business' ),
		'featured'   => true,
		'features'   => array( 'wifi', 'gym', 'spa', 'restaurant', 'bar', 'ac', 'concierge', 'parking' ),
		'tags'       => array( 'hudson yards', 'modern', 'new', 'tech forward' ),
		'content'    => 'The newest business hotel in Manhattan\'s most exciting neighborhood. 200 tech-forward rooms with voice-controlled lighting, smart mirrors, and keyless entry via app. Coworking lounge on the 3rd floor with complimentary coffee. Located in Hudson Yards with direct access to The Vessel, The Shed, and world-class shopping and dining.',
		'meta'       => array(
			'address'         => array(
				'address'     => '30 Hudson Yards, NY 10001',
				'lat'         => 40.7535,
				'lng'         => -74.0005,
				'city'        => 'Hudson Yards',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10001',
			),
			'phone'           => '(212) 555-0911',
			'website'         => 'https://hudsonbiz.hotel',
			'email'           => 'stay@hudsonbiz.hotel',
			'star_rating'     => '4',
			'price_per_night' => array(
				'amount'   => 475,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '12:00',
			'rooms'           => 200,
			'booking_url'     => 'https://hudsonbiz.hotel/reserve',
		),
		'reviews'    => array(
			array( 5, 'Tech-forward and sleek', 'Loved the app check-in and voice-controlled room. Coworking space is perfect for meetings. The Vessel views from the room are stunning.' ),
			array( 4, 'Best new business hotel', 'Everything feels brand new and well thought out. The restaurant is excellent. Location in Hudson Yards is ideal for West Side clients.' ),
		),
	),
	// ── Budget ──
	array(
		'title'      => 'NYC Pod Hotel — Midtown',
		'type'       => 'hotel',
		'categories' => array( 'Budget' ),
		'features'   => array( 'wifi', 'ac' ),
		'tags'       => array( 'pod', 'affordable', 'compact', 'solo traveler' ),
		'content'    => 'Smart, compact rooms designed for travelers who want a clean, comfortable bed in the best location without breaking the bank. Rooms range from 100 to 150 sq ft with memory foam beds, rain showers, and free WiFi. Common areas include a rooftop bar with skyline views and a communal kitchen. Perfect for solo travelers and adventurers.',
		'meta'       => array(
			'address'         => array(
				'address'     => '230 East 51st Street, Midtown East, NY 10022',
				'lat'         => 40.7555,
				'lng'         => -73.9715,
				'city'        => 'Midtown East',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10022',
			),
			'phone'           => '(212) 555-0118',
			'website'         => 'https://podhotels.com/midtown',
			'email'           => 'info@podhotels.com',
			'star_rating'     => '2',
			'price_per_night' => array(
				'amount'   => 99,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '11:00',
			'rooms'           => 665,
			'booking_url'     => 'https://podhotels.com/midtown/book',
		),
		'reviews'    => array(
			array( 4, 'Best budget option in NYC', 'Room is tiny but has everything you need. Bed was surprisingly comfortable. Rooftop bar is a huge bonus. Best value in Midtown.' ),
			array( 3.5, 'Compact but clean', 'You get what you pay for. Room is small but well-designed. Bathroom is tight. Location is excellent for sightseeing.' ),
		),
	),
	array(
		'title'      => 'Chelsea Hostel & Inn',
		'type'       => 'hotel',
		'categories' => array( 'Budget' ),
		'features'   => array( 'wifi', 'ac' ),
		'tags'       => array( 'hostel', 'backpacker', 'social', 'chelsea' ),
		'content'    => 'The friendliest budget accommodation in Manhattan. Choose from private rooms or shared dorms with comfortable beds and personal lockers. Communal kitchen saves money on dining out. Social areas include a TV lounge, game room, and outdoor courtyard. Walking distance to the High Line, Chelsea Market, and the Meatpacking District.',
		'meta'       => array(
			'address'         => array(
				'address'     => '251 West 20th Street, Chelsea, NY 10011',
				'lat'         => 40.7425,
				'lng'         => -73.9990,
				'city'        => 'Chelsea',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10011',
			),
			'phone'           => '(212) 555-0677',
			'website'         => 'https://chelseahostel.nyc',
			'email'           => 'hello@chelseahostel.nyc',
			'star_rating'     => '2',
			'price_per_night' => array(
				'amount'   => 65,
				'currency' => 'USD',
			),
			'check_in_time'   => '14:00',
			'check_out_time'  => '10:00',
			'rooms'           => 80,
			'booking_url'     => 'https://chelseahostel.nyc/book',
		),
		'reviews'    => array(
			array( 4, 'Great social hostel', 'Met amazing people from around the world. Kitchen saved me hundreds on food. Private room was clean and quiet enough to sleep.' ),
			array( 3.5, 'Good value for NYC', 'Dorm beds are comfortable with curtains for privacy. Bathrooms are clean. Location near the High Line is perfect.' ),
		),
	),
	array(
		'title'      => 'Budget Express Inn — Queens',
		'type'       => 'hotel',
		'categories' => array( 'Budget' ),
		'features'   => array( 'wifi', 'ac', 'parking' ),
		'tags'       => array( 'queens', 'airport', 'jfk', 'shuttle' ),
		'content'    => 'Clean, no-frills accommodation near JFK Airport. Recently renovated rooms with firm beds, flat-screen TVs, and free WiFi. Complimentary airport shuttle runs every 30 minutes. Free parking for guests driving in. Continental breakfast included. Ideal for early flights, long layovers, or budget-conscious travelers who want to save on accommodation.',
		'meta'       => array(
			'address'         => array(
				'address'     => '14415 Conduit Avenue, South Ozone Park, Queens, NY 11436',
				'lat'         => 40.6755,
				'lng'         => -73.8245,
				'city'        => 'Queens',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11436',
			),
			'phone'           => '(718) 555-0299',
			'website'         => 'https://budgetexpressinn.com',
			'email'           => 'stay@budgetexpressinn.com',
			'star_rating'     => '2',
			'price_per_night' => array(
				'amount'   => 85,
				'currency' => 'USD',
			),
			'check_in_time'   => '14:00',
			'check_out_time'  => '11:00',
			'rooms'           => 120,
			'booking_url'     => 'https://budgetexpressinn.com/book',
		),
		'reviews'    => array(
			array( 3.5, 'Great for airport stays', 'Free shuttle to JFK is a lifesaver. Room was basic but clean. Breakfast was simple but included. Perfect for an early flight.' ),
		),
	),
	array(
		'title'      => 'Harlem Heritage Inn',
		'type'       => 'hotel',
		'categories' => array( 'Budget' ),
		'features'   => array( 'wifi', 'ac' ),
		'tags'       => array( 'harlem', 'heritage', 'jazz', 'culture' ),
		'content'    => 'Experience the soul of Harlem at this affordable boutique inn. 24 rooms decorated with jazz-era artwork and Harlem Renaissance photography. Located on the famous 125th Street corridor near the Apollo Theater, Sylvia\'s Restaurant, and Studio Museum. A cultural guide in the lobby helps guests discover the neighborhood\'s rich heritage.',
		'meta'       => array(
			'address'         => array(
				'address'     => '242 West 123rd Street, Harlem, NY 10027',
				'lat'         => 40.8080,
				'lng'         => -73.9505,
				'city'        => 'Harlem',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10027',
			),
			'phone'           => '(212) 555-0862',
			'website'         => 'https://harlemheritageinn.com',
			'email'           => 'welcome@harlemheritageinn.com',
			'star_rating'     => '3',
			'price_per_night' => array(
				'amount'   => 145,
				'currency' => 'USD',
			),
			'check_in_time'   => '14:00',
			'check_out_time'  => '11:00',
			'rooms'           => 24,
			'booking_url'     => 'https://harlemheritageinn.com/reserve',
		),
		'reviews'    => array(
			array( 4, 'Cultural treasure', 'The jazz artwork in the room was beautiful. Staff recommended an amazing gospel brunch. Best way to experience real Harlem.' ),
			array( 4, 'Hidden gem', 'Much nicer than expected at this price. Room was clean and had character. Neighborhood is vibrant and friendly.' ),
		),
	),
	// ── Resort ──
	array(
		'title'      => 'Governors Island Resort & Spa',
		'type'       => 'hotel',
		'categories' => array( 'Resort' ),
		'featured'   => true,
		'features'   => array( 'wifi', 'pool', 'spa', 'gym', 'restaurant', 'bar', 'concierge' ),
		'tags'       => array( 'island', 'escape', 'spa', 'resort' ),
		'content'    => 'Escape Manhattan without leaving New York City. This exclusive island resort on Governors Island offers a tranquil retreat just a 7-minute ferry ride from Lower Manhattan. 60 glamping-inspired suites with harbor views. Full-service spa with hydrotherapy circuit. Infinity pool overlooking the Statue of Liberty. Farm-to-table restaurant using ingredients from the island\'s urban farm.',
		'meta'       => array(
			'address'         => array(
				'address'     => '10 South Street, Governors Island, NY 10004',
				'lat'         => 40.6893,
				'lng'         => -74.0168,
				'city'        => 'Governors Island',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10004',
			),
			'phone'           => '(212) 555-0955',
			'website'         => 'https://giresort.com',
			'email'           => 'escape@giresort.com',
			'star_rating'     => '5',
			'price_per_night' => array(
				'amount'   => 750,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '11:00',
			'rooms'           => 60,
			'booking_url'     => 'https://giresort.com/book',
		),
		'reviews'    => array(
			array( 5, 'Urban paradise', 'Watching the sunset over the Statue of Liberty from the infinity pool was surreal. Farm-to-table dinner was extraordinary. Felt a world away from Manhattan.' ),
			array( 5, 'Most unique NYC experience', 'The island setting is magical. Spa was heavenly. Woke up to the sound of birds instead of traffic. Pure bliss.' ),
		),
	),
	array(
		'title'      => 'Rockaway Beach Hotel & Surf Club',
		'type'       => 'hotel',
		'categories' => array( 'Resort' ),
		'features'   => array( 'wifi', 'pool', 'restaurant', 'bar', 'parking' ),
		'tags'       => array( 'beach', 'surfing', 'rockaway', 'summer' ),
		'content'    => 'The ultimate beach getaway without leaving New York City. Located steps from the Rockaway Beach boardwalk, this surf-inspired resort features 45 rooms with ocean views, a heated outdoor pool, and a beach bar serving tropical cocktails. Surfboard rentals and lessons available. The beachside restaurant serves fresh seafood and wood-fired pizza.',
		'meta'       => array(
			'address'         => array(
				'address'     => '108-10 Shore Front Parkway, Rockaway Beach, NY 11694',
				'lat'         => 40.5835,
				'lng'         => -73.8160,
				'city'        => 'Rockaway Beach',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11694',
			),
			'phone'           => '(718) 555-0433',
			'website'         => 'https://rockawaybeachhotel.com',
			'email'           => 'surf@rockawaybeachhotel.com',
			'star_rating'     => '4',
			'price_per_night' => array(
				'amount'   => 350,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '11:00',
			'rooms'           => 45,
			'booking_url'     => 'https://rockawaybeachhotel.com/book',
		),
		'reviews'    => array(
			array( 5, 'Beach paradise in NYC', 'Fell asleep to the sound of waves. Surfing in the morning, pool in the afternoon. Best summer weekend ever.' ),
			array( 4, 'Fun and relaxed', 'Great vibe, friendly staff. Pool area is the place to be. Wood-fired pizza at the beach restaurant was delicious.' ),
		),
	),
	array(
		'title'      => 'SoHo Grand Hotel',
		'type'       => 'hotel',
		'categories' => array( 'Boutique' ),
		'featured'   => true,
		'features'   => array( 'wifi', 'gym', 'restaurant', 'bar', 'ac', 'concierge', 'pet-friendly' ),
		'tags'       => array( 'soho', 'pet friendly', 'celebrity', 'nightlife' ),
		'content'    => 'A downtown institution and celebrity favorite. The SoHo Grand pioneered the boutique hotel movement in lower Manhattan. 353 rooms with custom furnishings, luxurious linens, and curated minibars. The Club Room hosts live DJs on weekends. One of the most pet-friendly hotels in the city with no size or breed restrictions.',
		'meta'       => array(
			'address'         => array(
				'address'     => '310 West Broadway, SoHo, NY 10013',
				'lat'         => 40.7225,
				'lng'         => -74.0030,
				'city'        => 'SoHo',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '10013',
			),
			'phone'           => '(212) 555-0388',
			'website'         => 'https://sohogrand.com',
			'email'           => 'reservations@sohogrand.com',
			'star_rating'     => '4',
			'price_per_night' => array(
				'amount'   => 450,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '12:00',
			'rooms'           => 353,
			'booking_url'     => 'https://sohogrand.com/book',
		),
		'reviews'    => array(
			array( 5, 'Downtown icon', 'The lobby atmosphere is electric. Room was spacious for SoHo. Brought my dog and staff treated him like royalty.' ),
			array( 4, 'Great location and style', 'Perfect for a SoHo shopping weekend. Club Room DJ night was a blast. Room design is cool and comfortable.' ),
		),
	),
	array(
		'title'      => 'LaGuardia Airport Comfort Suites',
		'type'       => 'hotel',
		'categories' => array( 'Budget' ),
		'features'   => array( 'wifi', 'ac', 'parking', 'gym' ),
		'tags'       => array( 'airport', 'laguardia', 'shuttle', 'convenient' ),
		'content'    => 'Convenient and affordable accommodations near LaGuardia Airport. All-suite hotel with separate living and sleeping areas, perfect for early morning flights or long layovers. Complimentary airport shuttle, free parking, and hot breakfast buffet included. Business center and small fitness room available 24 hours.',
		'meta'       => array(
			'address'         => array(
				'address'     => '38-50 College Point Boulevard, Flushing, NY 11354',
				'lat'         => 40.7635,
				'lng'         => -73.8310,
				'city'        => 'Flushing',
				'state'       => 'NY',
				'country'     => 'US',
				'postal_code' => '11354',
			),
			'phone'           => '(718) 555-0177',
			'website'         => 'https://lgacomfortsuites.com',
			'email'           => 'stay@lgacomfortsuites.com',
			'star_rating'     => '2',
			'price_per_night' => array(
				'amount'   => 110,
				'currency' => 'USD',
			),
			'check_in_time'   => '15:00',
			'check_out_time'  => '11:00',
			'rooms'           => 95,
			'booking_url'     => 'https://lgacomfortsuites.com/book',
		),
		'reviews'    => array(
			array( 4, 'Perfect airport hotel', 'Shuttle was right on time. Suite had plenty of space. Hot breakfast before a 6 AM flight was a lifesaver.' ),
			array( 3.5, 'Good for the price', 'Clean, functional, and convenient. Not fancy but everything you need near LGA. Free parking is a big plus.' ),
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

	// Hotel services: airport transfers, spa, late checkout.
	$services = array(
		array( 'Airport Shuttle Transfer', 35, 45, 'One-way airport pickup or drop-off in a private vehicle. Add to your stay during booking.', 'Transport' ),
		array( '90-min Spa Massage', 145, 90, 'Full-body massage with a choice of Swedish, deep tissue, or hot stone. Robe and slippers provided.', 'Wellness' ),
		array( 'Late Checkout (until 4pm)', 25, 0, 'Extend your stay until 4pm without a full extra night charge — subject to availability.', 'Stay Add-ons' ),
	);

	Demo_Seeder::seed_pack_extras( $post_id, 'hotel', $idx, $services );
}
