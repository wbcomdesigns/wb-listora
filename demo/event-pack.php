<?php
/**
 * Event Demo Pack - 8 upcoming events in and around New York.
 *
 * Dates are relative to the import day so the calendar and event pages always
 * show upcoming events, however long after release the pack is imported.
 *
 * @package WBListora\Demo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-demo-seeder.php';

use WBListora\Demo\Demo_Seeder;

// ── Categories ──

Demo_Seeder::ensure_categories(
	array(
		// slug => array( name, Lucide icon, colour ).
		'concert'    => array( 'Concert', 'music', '#DB2777' ),
		'conference' => array( 'Conference', 'presentation', '#2563EB' ),
		'workshop'   => array( 'Workshop', 'pencil-ruler', '#0D9488' ),
		'festival'   => array( 'Festival', 'party-popper', '#EA580C' ),
		'sports'     => array( 'Sports', 'trophy', '#16A34A' ),
		'networking' => array( 'Networking', 'users', '#7C3AED' ),
		'charity'    => array( 'Charity', 'heart-handshake', '#DC2626' ),
		'comedy'     => array( 'Comedy', 'laugh', '#CA8A04' ),
	)
);

Demo_Seeder::ensure_features(
	array(
		'free-entry'            => 'Free Entry',
		'family-friendly'       => 'Family Friendly',
		'outdoor'               => 'Outdoor',
		'wheelchair-accessible' => 'Wheelchair Accessible',
		'food-drinks'           => 'Food & Drinks',
		'21-plus'               => '21+',
	)
);

/**
 * A local date-time N days from the import day, in the stored `Y-m-d\TH:i` shape.
 *
 * @param int    $days Days from today.
 * @param string $time 24-hour time, e.g. '19:30'.
 * @return string
 */
$listora_event_at = static function ( $days, $time ) {
	return wp_date( 'Y-m-d', strtotime( '+' . (int) $days . ' days' ) ) . 'T' . $time;
};

// ── Listings ──

$listings = array(
	array(
		'title'      => 'Indie Night at Brooklyn Steel',
		'type'       => 'event',
		'categories' => array( 'Concert' ),
		'featured'   => true,
		'features'   => array( 'food-drinks', '21-plus', 'wheelchair-accessible' ),
		'tags'       => array( 'live-music', 'indie', 'brooklyn' ),
		'content'    => 'Three of New York\'s best new indie bands on one bill. Doors at 7, first set at 8, headliner at 10. Full bar and food trucks in the courtyard. Standing room; a small accessible platform is available on request.',
		'meta'       => array(
			'start_date'      => $listora_event_at( 5, '19:00' ),
			'end_date'        => $listora_event_at( 5, '23:30' ),
			'venue_name'      => 'Brooklyn Steel',
			'address'         => array(
				'address' => '319 Frost St, Brooklyn, NY 11222',
				'lat'     => 40.7193,
				'lng'     => -73.9387,
				'city'    => 'Brooklyn',
				'state'   => 'NY',
				'country' => 'US',
			),
			'ticket_price'    => array(
				'amount'   => 35,
				'currency' => 'USD',
			),
			'ticket_url'      => 'https://tickets.example.com/indie-night',
			'performers'      => 'The Lantern Club, Velvet Mile, Paper Harbors',
			'organizer'       => 'Northside Live',
			'capacity'        => 1800,
			'website'         => 'https://northsidelive.example.com',
			'recurrence_type' => 'none',
		),
	),
	array(
		'title'      => 'NYC Product Summit',
		'type'       => 'event',
		'categories' => array( 'Conference' ),
		'featured'   => true,
		'features'   => array( 'food-drinks', 'wheelchair-accessible' ),
		'tags'       => array( 'product', 'tech', 'startups' ),
		'content'    => 'A one-day conference for product managers and designers: 14 talks, 6 hands-on clinics and a closing panel on building with AI. Lunch, coffee and the evening reception are included in every pass.',
		'meta'       => array(
			'start_date'      => $listora_event_at( 21, '09:00' ),
			'end_date'        => $listora_event_at( 21, '18:00' ),
			'venue_name'      => 'Javits Center, Hall 1E',
			'address'         => array(
				'address' => '429 11th Ave, New York, NY 10001',
				'lat'     => 40.7578,
				'lng'     => -74.0026,
				'city'    => 'New York',
				'state'   => 'NY',
				'country' => 'US',
			),
			'ticket_price'    => array(
				'amount'   => 249,
				'currency' => 'USD',
			),
			'ticket_url'      => 'https://tickets.example.com/product-summit',
			'organizer'       => 'Product Guild NYC',
			'capacity'        => 1200,
			'email'           => 'hello@productsummit.example.com',
			'website'         => 'https://productsummit.example.com',
			'recurrence_type' => 'none',
		),
	),
	array(
		'title'      => 'Tuesday Open Mic Comedy',
		'type'       => 'event',
		'categories' => array( 'Comedy' ),
		'features'   => array( '21-plus', 'food-drinks' ),
		'tags'       => array( 'comedy', 'open-mic', 'weekly' ),
		'content'    => 'Every Tuesday: ten new comics, five minutes each, plus a surprise headliner. Two-drink minimum. Comics sign up at the door from 7pm.',
		'meta'       => array(
			'start_date'      => $listora_event_at( 3, '20:00' ),
			'end_date'        => $listora_event_at( 3, '22:00' ),
			'venue_name'      => 'The Cellar Room',
			'address'         => array(
				'address' => '117 MacDougal St, New York, NY 10012',
				'lat'     => 40.7302,
				'lng'     => -74.0005,
				'city'    => 'New York',
				'state'   => 'NY',
				'country' => 'US',
			),
			'ticket_price'    => array(
				'amount'   => 15,
				'currency' => 'USD',
			),
			'organizer'       => 'The Cellar Room',
			'capacity'        => 120,
			'phone'           => '(212) 555-0187',
			'recurrence_type' => 'weekly',
			'recurrence_end'  => wp_date( 'Y-m-d', strtotime( '+60 days' ) ),
		),
		'reviews'    => array(
			array( 5, 'Best cheap night out', 'Came for one set, stayed for the whole night. The surprise headliner was someone I had seen on TV.' ),
			array( 4, 'Fun, cramped', 'Great comics, tiny room. Get there early for a seat near the stage.' ),
		),
	),
	array(
		'title'      => 'Hudson River Summer Sounds Festival',
		'type'       => 'event',
		'categories' => array( 'Festival' ),
		'featured'   => true,
		'features'   => array( 'outdoor', 'family-friendly', 'food-drinks', 'wheelchair-accessible' ),
		'tags'       => array( 'festival', 'outdoor', 'live-music' ),
		'content'    => 'Two stages, twelve acts and a food market on the pier, from noon until the fireworks at 9:30. Children under 12 go free. Bring a blanket; no glass.',
		'meta'       => array(
			'start_date'      => $listora_event_at( 34, '12:00' ),
			'end_date'        => $listora_event_at( 35, '22:00' ),
			'venue_name'      => 'Pier 63, Hudson River Park',
			'address'         => array(
				'address' => 'Pier 63, W 23rd St, New York, NY 10011',
				'lat'     => 40.7495,
				'lng'     => -74.0098,
				'city'    => 'New York',
				'state'   => 'NY',
				'country' => 'US',
			),
			'ticket_price'    => array(
				'amount'   => 65,
				'currency' => 'USD',
			),
			'ticket_url'      => 'https://tickets.example.com/summer-sounds',
			'performers'      => 'Harbor Lights, DJ Mara, The Saltwater Six and more',
			'organizer'       => 'Hudson River Arts',
			'capacity'        => 6000,
			'website'         => 'https://summersounds.example.com',
			'recurrence_type' => 'none',
		),
	),
	array(
		'title'      => 'Founders & Coffee Networking Breakfast',
		'type'       => 'event',
		'categories' => array( 'Networking' ),
		'features'   => array( 'free-entry', 'food-drinks' ),
		'tags'       => array( 'startups', 'networking', 'breakfast' ),
		'content'    => 'An hour of coffee and introductions for founders, operators and investors, then three five-minute pitches from early-stage teams. Free, but places are limited: register to get in.',
		'meta'       => array(
			'start_date'      => $listora_event_at( 9, '08:00' ),
			'end_date'        => $listora_event_at( 9, '09:30' ),
			'venue_name'      => 'The Assemblage John Street',
			'address'         => array(
				'address' => '17 John St, New York, NY 10038',
				'lat'     => 40.7099,
				'lng'     => -74.0086,
				'city'    => 'New York',
				'state'   => 'NY',
				'country' => 'US',
			),
			'ticket_price'    => array(
				'amount'   => 0,
				'currency' => 'USD',
			),
			'ticket_url'      => 'https://tickets.example.com/founders-coffee',
			'organizer'       => 'Downtown Founders',
			'capacity'        => 80,
			'recurrence_type' => 'none',
		),
	),
	array(
		'title'      => 'Hands-on UX Research Workshop',
		'type'       => 'event',
		'categories' => array( 'Workshop' ),
		'features'   => array( 'wheelchair-accessible' ),
		'tags'       => array( 'ux', 'design', 'research' ),
		'content'    => 'A practical half-day workshop: plan a study, run two live interviews and turn the notes into decisions. Bring a laptop. Twenty seats so everyone gets feedback.',
		'meta'       => array(
			'start_date'      => $listora_event_at( 14, '13:00' ),
			'end_date'        => $listora_event_at( 14, '17:00' ),
			'venue_name'      => 'Flatiron Learning Studio',
			'address'         => array(
				'address' => '902 Broadway, New York, NY 10010',
				'lat'     => 40.7393,
				'lng'     => -73.9897,
				'city'    => 'New York',
				'state'   => 'NY',
				'country' => 'US',
			),
			'ticket_price'    => array(
				'amount'   => 120,
				'currency' => 'USD',
			),
			'ticket_url'      => 'https://tickets.example.com/ux-research',
			'performers'      => 'Priya Raman, Lead Researcher',
			'organizer'       => 'Flatiron Learning Studio',
			'capacity'        => 20,
			'email'           => 'workshops@flatironstudio.example.com',
			'recurrence_type' => 'none',
		),
	),
	array(
		'title'      => 'Central Park Charity 10K',
		'type'       => 'event',
		'categories' => array( 'Sports', 'Charity' ),
		'features'   => array( 'outdoor', 'family-friendly' ),
		'tags'       => array( 'running', 'charity', 'central-park' ),
		'content'    => 'One loop of Central Park for the city\'s food banks. Every runner gets a medal and a T-shirt; walkers and strollers welcome in the last wave. Bag check opens at 7am.',
		'meta'       => array(
			'start_date'      => $listora_event_at( 27, '08:00' ),
			'end_date'        => $listora_event_at( 27, '11:00' ),
			'venue_name'      => 'Naumburg Bandshell, Central Park',
			'address'         => array(
				'address' => 'Central Park Mall, New York, NY 10021',
				'lat'     => 40.7713,
				'lng'     => -73.9712,
				'city'    => 'New York',
				'state'   => 'NY',
				'country' => 'US',
			),
			'ticket_price'    => array(
				'amount'   => 40,
				'currency' => 'USD',
			),
			'ticket_url'      => 'https://tickets.example.com/charity-10k',
			'organizer'       => 'Run for Food NYC',
			'capacity'        => 3000,
			'recurrence_type' => 'none',
		),
		'reviews'    => array(
			array( 5, 'Well run, every year', 'Third year running it. Clear start waves, water every two kilometres and a great crowd at the finish.' ),
		),
	),
	array(
		'title'      => 'Harvest Table Charity Dinner',
		'type'       => 'event',
		'categories' => array( 'Charity' ),
		'features'   => array( 'food-drinks', 'outdoor' ),
		'tags'       => array( 'dinner', 'charity', 'garden' ),
		'content'    => 'A long-table, four-course dinner in the garden cooked by five Brooklyn chefs, with wine pairings and a silent auction. Every ticket funds a month of school meals.',
		'meta'       => array(
			'start_date'      => $listora_event_at( 48, '18:30' ),
			'end_date'        => $listora_event_at( 48, '22:30' ),
			'venue_name'      => 'Brooklyn Botanic Garden',
			'address'         => array(
				'address' => '990 Washington Ave, Brooklyn, NY 11225',
				'lat'     => 40.6694,
				'lng'     => -73.9624,
				'city'    => 'Brooklyn',
				'state'   => 'NY',
				'country' => 'US',
			),
			'ticket_price'    => array(
				'amount'   => 150,
				'currency' => 'USD',
			),
			'ticket_url'      => 'https://tickets.example.com/harvest-table',
			'organizer'       => 'Feed Brooklyn',
			'capacity'        => 160,
			'email'           => 'events@feedbrooklyn.example.com',
			'recurrence_type' => 'none',
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

	foreach ( $reviews as $review ) {
		Demo_Seeder::seed_review( $post_id, $review[0], $review[1], $review[2] );
	}

	// Events are not claimed businesses and sell tickets, not services.
	Demo_Seeder::seed_pack_extras( $post_id, $idx, array(), array( 'claim' => false ) );
}
