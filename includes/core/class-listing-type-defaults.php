<?php
/**
 * Default listing type definitions.
 *
 * Contains field groups, categories, and config for all 10 default listing types.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Provides default listing type data for initial population.
 */
class Listing_Type_Defaults {

	/**
	 * Get all default type definitions.
	 *
	 * @return array Slug => [props, field_groups, categories] map.
	 */
	public static function get_all() {
		return array(
			'business'    => self::business(),
			'restaurant'  => self::restaurant(),
			'real-estate' => self::real_estate(),
			'hotel'       => self::hotel(),
			'event'       => self::event(),
			'job'         => self::job(),
			'healthcare'  => self::healthcare(),
			'education'   => self::education(),
			'place'       => self::place(),
			'classified'  => self::classified(),
		);
	}

	/**
	 * Helper: create a field definition array.
	 */
	private static function f( $key, $label, $type, $extra = array() ) {
		return array_merge(
			array(
				'key'   => $key,
				'label' => $label,
				'type'  => $type,
			),
			$extra
		);
	}

	// ─── Type 1: Business ───

	private static function business() {
		return array(
			'props'        => array(
				'name'        => __( 'Business', 'wb-listora' ),
				'schema_type' => 'LocalBusiness',
				'icon'        => 'dashicons-building',
				'color'       => '#2563EB',
				'is_default'  => true,
			),
			'field_groups' => array(
				array(
					'key'    => 'contact',
					'label'  => __( 'Contact Information', 'wb-listora' ),
					'icon'   => 'phone',
					'order'  => 1,
					'fields' => array(
						self::f(
							'address',
							__( 'Address', 'wb-listora' ),
							'map_location',
							array(
								'required'     => true,
								'searchable'   => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'address',
							)
						),
						self::f(
							'phone',
							__( 'Phone', 'wb-listora' ),
							'phone',
							array(
								'show_in_card' => true,
								'schema_prop'  => 'telephone',
							)
						),
						self::f( 'email', __( 'Email', 'wb-listora' ), 'email', array( 'schema_prop' => 'email' ) ),
						self::f( 'website', __( 'Website', 'wb-listora' ), 'url', array( 'schema_prop' => 'url' ) ),
					),
				),
				array(
					'key'    => 'details',
					'label'  => __( 'Business Details', 'wb-listora' ),
					'icon'   => 'info',
					'order'  => 2,
					'fields' => array(
						self::f(
							'business_hours',
							__( 'Business Hours', 'wb-listora' ),
							'business_hours',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'openingHoursSpecification',
							)
						),
						self::f(
							'price_range',
							__( 'Price Range', 'wb-listora' ),
							'select',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'priceRange',
								'options'      => array(
									array(
										'value' => '$',
										'label' => __( '$ — Budget', 'wb-listora' ),
									),
									array(
										'value' => '$$',
										'label' => __( '$$ — Moderate', 'wb-listora' ),
									),
									array(
										'value' => '$$$',
										'label' => __( '$$$ — Upscale', 'wb-listora' ),
									),
									array(
										'value' => '$$$$',
										'label' => __( '$$$$ — Premium', 'wb-listora' ),
									),
								),
							)
						),
						self::f( 'year_established', __( 'Year Established', 'wb-listora' ), 'number', array( 'schema_prop' => 'foundingDate' ) ),
					),
				),
				array(
					'key'    => 'media',
					'label'  => __( 'Media', 'wb-listora' ),
					'icon'   => 'images',
					'order'  => 3,
					'fields' => array(
						self::f( 'gallery', __( 'Photo Gallery', 'wb-listora' ), 'gallery', array( 'schema_prop' => 'image' ) ),
						self::f( 'social_links', __( 'Social Links', 'wb-listora' ), 'social_links', array( 'schema_prop' => 'sameAs' ) ),
					),
				),
			),
			'categories'   => array(
				__( 'Retail', 'wb-listora' ),
				__( 'Services', 'wb-listora' ),
				__( 'Food & Drink', 'wb-listora' ),
				__( 'Health & Wellness', 'wb-listora' ),
				__( 'Automotive', 'wb-listora' ),
				__( 'Home & Garden', 'wb-listora' ),
				__( 'Finance', 'wb-listora' ),
				__( 'Legal', 'wb-listora' ),
				__( 'Education', 'wb-listora' ),
				__( 'Other', 'wb-listora' ),
			),
		);
	}

	// ─── Type 2: Restaurant ───

	private static function restaurant() {
		return array(
			'props'        => array(
				'name'        => __( 'Restaurant', 'wb-listora' ),
				'schema_type' => 'Restaurant',
				'icon'        => 'dashicons-food',
				'color'       => '#DC2626',
				'is_default'  => true,
			),
			'field_groups' => array(
				array(
					'key'    => 'contact',
					'label'  => __( 'Contact', 'wb-listora' ),
					'icon'   => 'phone',
					'order'  => 1,
					'fields' => array(
						self::f(
							'address',
							__( 'Address', 'wb-listora' ),
							'map_location',
							array(
								'required'     => true,
								'searchable'   => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'address',
							)
						),
						self::f(
							'phone',
							__( 'Phone', 'wb-listora' ),
							'phone',
							array(
								'show_in_card' => true,
								'schema_prop'  => 'telephone',
							)
						),
					),
				),
				array(
					'key'    => 'restaurant_info',
					'label'  => __( 'Restaurant Details', 'wb-listora' ),
					'icon'   => 'utensils',
					'order'  => 2,
					'fields' => array(
						self::f(
							'cuisine',
							__( 'Cuisine', 'wb-listora' ),
							'multiselect',
							array(
								'searchable'   => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'servesCuisine',
								'options'      => array(
									array(
										'value' => 'italian',
										'label' => __( 'Italian', 'wb-listora' ),
									),
									array(
										'value' => 'chinese',
										'label' => __( 'Chinese', 'wb-listora' ),
									),
									array(
										'value' => 'japanese',
										'label' => __( 'Japanese', 'wb-listora' ),
									),
									array(
										'value' => 'mexican',
										'label' => __( 'Mexican', 'wb-listora' ),
									),
									array(
										'value' => 'indian',
										'label' => __( 'Indian', 'wb-listora' ),
									),
									array(
										'value' => 'thai',
										'label' => __( 'Thai', 'wb-listora' ),
									),
									array(
										'value' => 'american',
										'label' => __( 'American', 'wb-listora' ),
									),
									array(
										'value' => 'french',
										'label' => __( 'French', 'wb-listora' ),
									),
									array(
										'value' => 'mediterranean',
										'label' => __( 'Mediterranean', 'wb-listora' ),
									),
									array(
										'value' => 'other',
										'label' => __( 'Other', 'wb-listora' ),
									),
								),
							)
						),
						self::f(
							'price_range',
							__( 'Price Range', 'wb-listora' ),
							'select',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'priceRange',
								'options'      => array(
									array(
										'value' => '$',
										'label' => __( '$ — Budget', 'wb-listora' ),
									),
									array(
										'value' => '$$',
										'label' => __( '$$ — Moderate', 'wb-listora' ),
									),
									array(
										'value' => '$$$',
										'label' => __( '$$$ — Upscale', 'wb-listora' ),
									),
									array(
										'value' => '$$$$',
										'label' => __( '$$$$ — Fine Dining', 'wb-listora' ),
									),
								),
							)
						),
						self::f(
							'business_hours',
							__( 'Business Hours', 'wb-listora' ),
							'business_hours',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'openingHoursSpecification',
							)
						),
						self::f(
							'reservations',
							__( 'Reservations', 'wb-listora' ),
							'select',
							array(
								'filterable'  => true,
								'schema_prop' => 'acceptsReservations',
								'options'     => array(
									array(
										'value' => 'yes',
										'label' => __( 'Yes', 'wb-listora' ),
									),
									array(
										'value' => 'no',
										'label' => __( 'No', 'wb-listora' ),
									),
									array(
										'value' => 'online',
										'label' => __( 'Online Only', 'wb-listora' ),
									),
								),
							)
						),
						self::f(
							'delivery',
							__( 'Delivery Available', 'wb-listora' ),
							'checkbox',
							array(
								'filterable'   => true,
								'show_in_card' => true,
							)
						),
						self::f( 'takeout', __( 'Takeout Available', 'wb-listora' ), 'checkbox', array( 'filterable' => true ) ),
						self::f( 'menu_url', __( 'Menu URL', 'wb-listora' ), 'url', array( 'schema_prop' => 'hasMenu' ) ),
					),
				),
				array(
					'key'    => 'media',
					'label'  => __( 'Media', 'wb-listora' ),
					'icon'   => 'images',
					'order'  => 3,
					'fields' => array(
						self::f( 'gallery', __( 'Photo Gallery', 'wb-listora' ), 'gallery', array( 'schema_prop' => 'image' ) ),
						self::f( 'social_links', __( 'Social Links', 'wb-listora' ), 'social_links', array( 'schema_prop' => 'sameAs' ) ),
					),
				),
			),
			'categories'   => array(
				__( 'Italian', 'wb-listora' ),
				__( 'Chinese', 'wb-listora' ),
				__( 'Japanese', 'wb-listora' ),
				__( 'Mexican', 'wb-listora' ),
				__( 'Indian', 'wb-listora' ),
				__( 'Thai', 'wb-listora' ),
				__( 'American', 'wb-listora' ),
				__( 'French', 'wb-listora' ),
				__( 'Mediterranean', 'wb-listora' ),
				__( 'Fast Food', 'wb-listora' ),
				__( 'Cafe', 'wb-listora' ),
				__( 'Bar', 'wb-listora' ),
				__( 'Bakery', 'wb-listora' ),
				__( 'Seafood', 'wb-listora' ),
				__( 'Vegan', 'wb-listora' ),
			),
		);
	}

	// ─── Type 3: Real Estate ───

	private static function real_estate() {
		return array(
			'props'        => array(
				'name'        => __( 'Real Estate', 'wb-listora' ),
				'schema_type' => 'RealEstateListing',
				'icon'        => 'dashicons-admin-home',
				'color'       => '#059669',
				'is_default'  => true,
			),
			'field_groups' => array(
				array(
					'key'    => 'location',
					'label'  => __( 'Location', 'wb-listora' ),
					'icon'   => 'location',
					'order'  => 1,
					'fields' => array(
						self::f(
							'address',
							__( 'Address', 'wb-listora' ),
							'map_location',
							array(
								'required'     => true,
								'searchable'   => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'address',
							)
						),
					),
				),
				array(
					'key'    => 'property_info',
					'label'  => __( 'Property Details', 'wb-listora' ),
					'icon'   => 'home',
					'order'  => 2,
					'fields' => array(
						self::f(
							'listing_action',
							__( 'For Sale / Rent', 'wb-listora' ),
							'select',
							array(
								'required'     => true,
								'filterable'   => true,
								'show_in_card' => true,
								'options'      => array(
									array(
										'value' => 'sale',
										'label' => __( 'For Sale', 'wb-listora' ),
									),
									array(
										'value' => 'rent',
										'label' => __( 'For Rent', 'wb-listora' ),
									),
								),
							)
						),
						self::f(
							'price',
							__( 'Price', 'wb-listora' ),
							'price',
							array(
								'required'     => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'price',
							)
						),
						self::f(
							'bedrooms',
							__( 'Bedrooms', 'wb-listora' ),
							'number',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'numberOfRooms',
								'min'          => 0,
								'max'          => 20,
							)
						),
						self::f(
							'bathrooms',
							__( 'Bathrooms', 'wb-listora' ),
							'number',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'min'          => 0,
								'max'          => 20,
							)
						),
						self::f(
							'area_sqft',
							__( 'Area (sqft)', 'wb-listora' ),
							'number',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'floorSize',
							)
						),
						self::f(
							'property_type',
							__( 'Property Type', 'wb-listora' ),
							'select',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'options'      => array(
									array(
										'value' => 'house',
										'label' => __( 'House', 'wb-listora' ),
									),
									array(
										'value' => 'apartment',
										'label' => __( 'Apartment', 'wb-listora' ),
									),
									array(
										'value' => 'condo',
										'label' => __( 'Condo', 'wb-listora' ),
									),
									array(
										'value' => 'townhouse',
										'label' => __( 'Townhouse', 'wb-listora' ),
									),
									array(
										'value' => 'villa',
										'label' => __( 'Villa', 'wb-listora' ),
									),
									array(
										'value' => 'land',
										'label' => __( 'Land', 'wb-listora' ),
									),
									array(
										'value' => 'commercial',
										'label' => __( 'Commercial', 'wb-listora' ),
									),
								),
							)
						),
						self::f(
							'year_built',
							__( 'Year Built', 'wb-listora' ),
							'number',
							array(
								'filterable'  => true,
								'schema_prop' => 'yearBuilt',
							)
						),
						self::f(
							'parking',
							__( 'Parking Spaces', 'wb-listora' ),
							'number',
							array(
								'filterable' => true,
								'min'        => 0,
							)
						),
					),
				),
				array(
					'key'    => 'media',
					'label'  => __( 'Media', 'wb-listora' ),
					'icon'   => 'images',
					'order'  => 3,
					'fields' => array(
						self::f( 'gallery', __( 'Photo Gallery', 'wb-listora' ), 'gallery', array( 'schema_prop' => 'image' ) ),
						self::f( 'virtual_tour_url', __( 'Virtual Tour URL', 'wb-listora' ), 'url' ),
					),
				),
			),
			'categories'   => array(
				__( 'House', 'wb-listora' ),
				__( 'Apartment', 'wb-listora' ),
				__( 'Condo', 'wb-listora' ),
				__( 'Townhouse', 'wb-listora' ),
				__( 'Villa', 'wb-listora' ),
				__( 'Land', 'wb-listora' ),
				__( 'Commercial', 'wb-listora' ),
				__( 'Industrial', 'wb-listora' ),
			),
		);
	}

	// ─── Type 4: Hotel ───

	private static function hotel() {
		return array(
			'props'        => array(
				'name'        => __( 'Hotel', 'wb-listora' ),
				'schema_type' => 'Hotel',
				'icon'        => 'dashicons-store',
				'color'       => '#7C3AED',
				'is_default'  => true,
			),
			'field_groups' => array(
				array(
					'key'    => 'contact',
					'label'  => __( 'Contact', 'wb-listora' ),
					'icon'   => 'phone',
					'order'  => 1,
					'fields' => array(
						self::f(
							'address',
							__( 'Address', 'wb-listora' ),
							'map_location',
							array(
								'required'     => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'address',
							)
						),
						self::f(
							'phone',
							__( 'Phone', 'wb-listora' ),
							'phone',
							array(
								'show_in_card' => true,
								'schema_prop'  => 'telephone',
							)
						),
					),
				),
				array(
					'key'    => 'hotel_info',
					'label'  => __( 'Hotel Details', 'wb-listora' ),
					'icon'   => 'bed',
					'order'  => 2,
					'fields' => array(
						self::f(
							'star_rating',
							__( 'Star Rating', 'wb-listora' ),
							'select',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'starRating',
								'options'      => array(
									array(
										'value' => '1',
										'label' => '1 Star',
									),
									array(
										'value' => '2',
										'label' => '2 Stars',
									),
									array(
										'value' => '3',
										'label' => '3 Stars',
									),
									array(
										'value' => '4',
										'label' => '4 Stars',
									),
									array(
										'value' => '5',
										'label' => '5 Stars',
									),
								),
							)
						),
						self::f(
							'price_per_night',
							__( 'Price Per Night', 'wb-listora' ),
							'price',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'priceRange',
							)
						),
						self::f( 'check_in_time', __( 'Check-in Time', 'wb-listora' ), 'time', array( 'schema_prop' => 'checkinTime' ) ),
						self::f( 'check_out_time', __( 'Check-out Time', 'wb-listora' ), 'time', array( 'schema_prop' => 'checkoutTime' ) ),
						self::f( 'rooms', __( 'Number of Rooms', 'wb-listora' ), 'number', array( 'schema_prop' => 'numberOfRooms' ) ),
						self::f( 'booking_url', __( 'Booking URL', 'wb-listora' ), 'url' ),
					),
				),
				array(
					'key'    => 'media',
					'label'  => __( 'Media', 'wb-listora' ),
					'icon'   => 'images',
					'order'  => 3,
					'fields' => array(
						self::f( 'gallery', __( 'Photo Gallery', 'wb-listora' ), 'gallery', array( 'schema_prop' => 'image' ) ),
						self::f( 'social_links', __( 'Social Links', 'wb-listora' ), 'social_links', array( 'schema_prop' => 'sameAs' ) ),
					),
				),
			),
			'categories'   => array(
				__( 'Hotel', 'wb-listora' ),
				__( 'Motel', 'wb-listora' ),
				__( 'Resort', 'wb-listora' ),
				__( 'B&B', 'wb-listora' ),
				__( 'Hostel', 'wb-listora' ),
				__( 'Boutique Hotel', 'wb-listora' ),
				__( 'Villa Rental', 'wb-listora' ),
				__( 'Guesthouse', 'wb-listora' ),
			),
		);
	}

	// ─── Type 5: Event ───

	private static function event() {
		return array(
			'props'        => array(
				'name'            => __( 'Event', 'wb-listora' ),
				'schema_type'     => 'Event',
				'icon'            => 'dashicons-calendar-alt',
				'color'           => '#D97706',
				'is_default'      => true,
				'expiration_days' => 0, // Events expire by end_date, not creation.
			),
			'field_groups' => array(
				array(
					'key'    => 'event_schedule',
					'label'  => __( 'Schedule', 'wb-listora' ),
					'icon'   => 'calendar',
					'order'  => 1,
					'fields' => array(
						self::f(
							'start_date',
							__( 'Start Date & Time', 'wb-listora' ),
							'datetime',
							array(
								'required'     => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'startDate',
							)
						),
						self::f(
							'end_date',
							__( 'End Date & Time', 'wb-listora' ),
							'datetime',
							array(
								'filterable'  => true,
								'schema_prop' => 'endDate',
							)
						),
						self::f(
							'venue_name',
							__( 'Venue Name', 'wb-listora' ),
							'text',
							array(
								'show_in_card' => true,
								'schema_prop'  => 'location.name',
							)
						),
						self::f(
							'address',
							__( 'Venue Address', 'wb-listora' ),
							'map_location',
							array(
								'required'     => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'location',
							)
						),
					),
				),
				array(
					'key'    => 'event_details',
					'label'  => __( 'Event Details', 'wb-listora' ),
					'icon'   => 'info',
					'order'  => 2,
					'fields' => array(
						self::f(
							'ticket_price',
							__( 'Ticket Price', 'wb-listora' ),
							'price',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'offers.price',
							)
						),
						self::f( 'ticket_url', __( 'Ticket URL', 'wb-listora' ), 'url', array( 'schema_prop' => 'offers.url' ) ),
						self::f(
							'performers',
							__( 'Performers / Speakers', 'wb-listora' ),
							'text',
							array(
								'searchable'  => true,
								'schema_prop' => 'performer',
							)
						),
						self::f( 'organizer', __( 'Organizer', 'wb-listora' ), 'text', array( 'schema_prop' => 'organizer' ) ),
						self::f( 'capacity', __( 'Capacity', 'wb-listora' ), 'number', array( 'schema_prop' => 'maximumAttendeeCapacity' ) ),
					),
				),
				array(
					'key'    => 'media',
					'label'  => __( 'Media', 'wb-listora' ),
					'icon'   => 'images',
					'order'  => 3,
					'fields' => array(
						self::f( 'gallery', __( 'Photo Gallery', 'wb-listora' ), 'gallery', array( 'schema_prop' => 'image' ) ),
					),
				),
			),
			'categories'   => array(
				__( 'Concert', 'wb-listora' ),
				__( 'Conference', 'wb-listora' ),
				__( 'Workshop', 'wb-listora' ),
				__( 'Festival', 'wb-listora' ),
				__( 'Sports', 'wb-listora' ),
				__( 'Networking', 'wb-listora' ),
				__( 'Charity', 'wb-listora' ),
				__( 'Exhibition', 'wb-listora' ),
				__( 'Comedy', 'wb-listora' ),
				__( 'Theater', 'wb-listora' ),
			),
		);
	}

	// ─── Type 6: Job ───

	private static function job() {
		return array(
			'props'        => array(
				'name'            => __( 'Job', 'wb-listora' ),
				'schema_type'     => 'JobPosting',
				'icon'            => 'dashicons-businessman',
				'color'           => '#0891B2',
				'is_default'      => true,
				'expiration_days' => 30,
				'review_enabled'  => false,
			),
			'field_groups' => array(
				array(
					'key'    => 'company',
					'label'  => __( 'Company', 'wb-listora' ),
					'icon'   => 'building',
					'order'  => 1,
					'fields' => array(
						self::f(
							'company_name',
							__( 'Company Name', 'wb-listora' ),
							'text',
							array(
								'required'     => true,
								'searchable'   => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'hiringOrganization.name',
							)
						),
						self::f(
							'company_logo',
							__( 'Company Logo', 'wb-listora' ),
							'file',
							array(
								'show_in_card' => true,
								'schema_prop'  => 'hiringOrganization.logo',
							)
						),
						self::f(
							'address',
							__( 'Job Location', 'wb-listora' ),
							'map_location',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'jobLocation',
							)
						),
					),
				),
				array(
					'key'    => 'job_details',
					'label'  => __( 'Job Details', 'wb-listora' ),
					'icon'   => 'clipboard',
					'order'  => 2,
					'fields' => array(
						self::f(
							'employment_type',
							__( 'Employment Type', 'wb-listora' ),
							'select',
							array(
								'required'     => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'employmentType',
								'options'      => array(
									array(
										'value' => 'full-time',
										'label' => __( 'Full Time', 'wb-listora' ),
									),
									array(
										'value' => 'part-time',
										'label' => __( 'Part Time', 'wb-listora' ),
									),
									array(
										'value' => 'contract',
										'label' => __( 'Contract', 'wb-listora' ),
									),
									array(
										'value' => 'freelance',
										'label' => __( 'Freelance', 'wb-listora' ),
									),
									array(
										'value' => 'internship',
										'label' => __( 'Internship', 'wb-listora' ),
									),
								),
							)
						),
						self::f(
							'salary_min',
							__( 'Salary Min', 'wb-listora' ),
							'number',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'baseSalary.minValue',
							)
						),
						self::f(
							'salary_max',
							__( 'Salary Max', 'wb-listora' ),
							'number',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'baseSalary.maxValue',
							)
						),
						self::f(
							'salary_type',
							__( 'Salary Period', 'wb-listora' ),
							'select',
							array(
								'filterable'  => true,
								'schema_prop' => 'baseSalary.unitText',
								'options'     => array(
									array(
										'value' => 'yearly',
										'label' => __( 'Per Year', 'wb-listora' ),
									),
									array(
										'value' => 'monthly',
										'label' => __( 'Per Month', 'wb-listora' ),
									),
									array(
										'value' => 'hourly',
										'label' => __( 'Per Hour', 'wb-listora' ),
									),
								),
							)
						),
						self::f(
							'experience_level',
							__( 'Experience Level', 'wb-listora' ),
							'select',
							array(
								'filterable'  => true,
								'schema_prop' => 'experienceRequirements',
								'options'     => array(
									array(
										'value' => 'entry',
										'label' => __( 'Entry Level', 'wb-listora' ),
									),
									array(
										'value' => 'mid',
										'label' => __( 'Mid Level', 'wb-listora' ),
									),
									array(
										'value' => 'senior',
										'label' => __( 'Senior', 'wb-listora' ),
									),
									array(
										'value' => 'lead',
										'label' => __( 'Lead / Manager', 'wb-listora' ),
									),
									array(
										'value' => 'executive',
										'label' => __( 'Executive', 'wb-listora' ),
									),
								),
							)
						),
						self::f(
							'remote_option',
							__( 'Remote Work', 'wb-listora' ),
							'select',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'jobLocationType',
								'options'      => array(
									array(
										'value' => 'onsite',
										'label' => __( 'On-site', 'wb-listora' ),
									),
									array(
										'value' => 'remote',
										'label' => __( 'Remote', 'wb-listora' ),
									),
									array(
										'value' => 'hybrid',
										'label' => __( 'Hybrid', 'wb-listora' ),
									),
								),
							)
						),
						self::f(
							'deadline',
							__( 'Application Deadline', 'wb-listora' ),
							'date',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'validThrough',
							)
						),
						self::f( 'apply_url', __( 'Apply URL', 'wb-listora' ), 'url', array( 'schema_prop' => 'url' ) ),
						self::f(
							'skills',
							__( 'Required Skills', 'wb-listora' ),
							'multiselect',
							array(
								'searchable'  => true,
								'filterable'  => true,
								'schema_prop' => 'skills',
								'options'     => array(), // Empty — user adds their own.
							)
						),
					),
				),
			),
			'categories'   => array(
				__( 'Technology', 'wb-listora' ),
				__( 'Healthcare', 'wb-listora' ),
				__( 'Finance', 'wb-listora' ),
				__( 'Marketing', 'wb-listora' ),
				__( 'Engineering', 'wb-listora' ),
				__( 'Education', 'wb-listora' ),
				__( 'Design', 'wb-listora' ),
				__( 'Sales', 'wb-listora' ),
				__( 'Admin', 'wb-listora' ),
				__( 'Legal', 'wb-listora' ),
				__( 'Hospitality', 'wb-listora' ),
				__( 'Manufacturing', 'wb-listora' ),
			),
		);
	}

	// ─── Type 7: Healthcare ───

	private static function healthcare() {
		return array(
			'props'        => array(
				'name'        => __( 'Healthcare', 'wb-listora' ),
				'schema_type' => 'Physician',
				'icon'        => 'dashicons-heart',
				'color'       => '#E11D48',
				'is_default'  => true,
			),
			'field_groups' => array(
				array(
					'key'    => 'contact',
					'label'  => __( 'Contact', 'wb-listora' ),
					'icon'   => 'phone',
					'order'  => 1,
					'fields' => array(
						self::f(
							'address',
							__( 'Address', 'wb-listora' ),
							'map_location',
							array(
								'required'     => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'address',
							)
						),
						self::f(
							'phone',
							__( 'Phone', 'wb-listora' ),
							'phone',
							array(
								'required'     => true,
								'show_in_card' => true,
								'schema_prop'  => 'telephone',
							)
						),
					),
				),
				array(
					'key'    => 'medical_info',
					'label'  => __( 'Medical Details', 'wb-listora' ),
					'icon'   => 'stethoscope',
					'order'  => 2,
					'fields' => array(
						self::f(
							'specialty',
							__( 'Specialty', 'wb-listora' ),
							'multiselect',
							array(
								'required'     => true,
								'searchable'   => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'medicalSpecialty',
								'options'      => array(), // Populated from categories.
							)
						),
						self::f( 'qualifications', __( 'Qualifications', 'wb-listora' ), 'text', array( 'schema_prop' => 'qualification' ) ),
						self::f(
							'insurance_accepted',
							__( 'Insurance Accepted', 'wb-listora' ),
							'multiselect',
							array(
								'filterable' => true,
								'options'    => array(),
							)
						),
						self::f(
							'hospital_affiliation',
							__( 'Hospital Affiliation', 'wb-listora' ),
							'text',
							array(
								'searchable'  => true,
								'filterable'  => true,
								'schema_prop' => 'hospitalAffiliation',
							)
						),
						self::f(
							'consultation_fee',
							__( 'Consultation Fee', 'wb-listora' ),
							'price',
							array(
								'filterable'   => true,
								'show_in_card' => true,
							)
						),
						self::f(
							'languages_spoken',
							__( 'Languages Spoken', 'wb-listora' ),
							'multiselect',
							array(
								'filterable'  => true,
								'schema_prop' => 'knowsLanguage',
								'options'     => array(),
							)
						),
						self::f( 'experience_years', __( 'Years of Experience', 'wb-listora' ), 'number', array( 'filterable' => true ) ),
						self::f( 'appointment_url', __( 'Appointment URL', 'wb-listora' ), 'url' ),
						self::f(
							'business_hours',
							__( 'Office Hours', 'wb-listora' ),
							'business_hours',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'openingHoursSpecification',
							)
						),
					),
				),
				array(
					'key'    => 'media',
					'label'  => __( 'Media', 'wb-listora' ),
					'icon'   => 'images',
					'order'  => 3,
					'fields' => array(
						self::f( 'gallery', __( 'Photo Gallery', 'wb-listora' ), 'gallery', array( 'schema_prop' => 'image' ) ),
					),
				),
			),
			'categories'   => array(
				__( 'General Practitioner', 'wb-listora' ),
				__( 'Dentist', 'wb-listora' ),
				__( 'Dermatologist', 'wb-listora' ),
				__( 'Cardiologist', 'wb-listora' ),
				__( 'Pediatrician', 'wb-listora' ),
				__( 'Orthopedist', 'wb-listora' ),
				__( 'Ophthalmologist', 'wb-listora' ),
				__( 'Psychiatrist', 'wb-listora' ),
				__( 'Neurologist', 'wb-listora' ),
				__( 'Gynecologist', 'wb-listora' ),
			),
		);
	}

	// ─── Type 8: Education ───

	private static function education() {
		return array(
			'props'        => array(
				'name'        => __( 'Education', 'wb-listora' ),
				'schema_type' => 'Course',
				'icon'        => 'dashicons-welcome-learn-more',
				'color'       => '#4F46E5',
				'is_default'  => true,
			),
			'field_groups' => array(
				array(
					'key'    => 'provider',
					'label'  => __( 'Provider', 'wb-listora' ),
					'icon'   => 'school',
					'order'  => 1,
					'fields' => array(
						self::f(
							'provider',
							__( 'Provider / Institution', 'wb-listora' ),
							'text',
							array(
								'required'     => true,
								'searchable'   => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'provider.name',
							)
						),
						self::f(
							'address',
							__( 'Location', 'wb-listora' ),
							'map_location',
							array(
								'filterable'  => true,
								'schema_prop' => 'location',
							)
						),
					),
				),
				array(
					'key'    => 'course_info',
					'label'  => __( 'Course Details', 'wb-listora' ),
					'icon'   => 'book',
					'order'  => 2,
					'fields' => array(
						self::f(
							'course_level',
							__( 'Level', 'wb-listora' ),
							'select',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'educationalLevel',
								'options'      => array(
									array(
										'value' => 'beginner',
										'label' => __( 'Beginner', 'wb-listora' ),
									),
									array(
										'value' => 'intermediate',
										'label' => __( 'Intermediate', 'wb-listora' ),
									),
									array(
										'value' => 'advanced',
										'label' => __( 'Advanced', 'wb-listora' ),
									),
									array(
										'value' => 'all',
										'label' => __( 'All Levels', 'wb-listora' ),
									),
								),
							)
						),
						self::f(
							'duration',
							__( 'Duration', 'wb-listora' ),
							'text',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'timeRequired',
							)
						),
						self::f(
							'price',
							__( 'Price', 'wb-listora' ),
							'price',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'offers.price',
							)
						),
						self::f(
							'format',
							__( 'Format', 'wb-listora' ),
							'select',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'deliveryMode',
								'options'      => array(
									array(
										'value' => 'online',
										'label' => __( 'Online', 'wb-listora' ),
									),
									array(
										'value' => 'in-person',
										'label' => __( 'In Person', 'wb-listora' ),
									),
									array(
										'value' => 'hybrid',
										'label' => __( 'Hybrid', 'wb-listora' ),
									),
								),
							)
						),
						self::f(
							'start_date',
							__( 'Start Date', 'wb-listora' ),
							'date',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'courseInstance.startDate',
							)
						),
						self::f(
							'certification',
							__( 'Certification Included', 'wb-listora' ),
							'checkbox',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'educationalCredentialAwarded',
							)
						),
						self::f( 'enrollment_url', __( 'Enrollment URL', 'wb-listora' ), 'url', array( 'schema_prop' => 'url' ) ),
						self::f( 'prerequisites', __( 'Prerequisites', 'wb-listora' ), 'textarea', array( 'schema_prop' => 'coursePrerequisites' ) ),
					),
				),
				array(
					'key'    => 'media',
					'label'  => __( 'Media', 'wb-listora' ),
					'icon'   => 'images',
					'order'  => 3,
					'fields' => array(
						self::f( 'gallery', __( 'Photo Gallery', 'wb-listora' ), 'gallery', array( 'schema_prop' => 'image' ) ),
					),
				),
			),
			'categories'   => array(
				__( 'Online Course', 'wb-listora' ),
				__( 'University', 'wb-listora' ),
				__( 'Tutoring', 'wb-listora' ),
				__( 'Language School', 'wb-listora' ),
				__( 'Coding Bootcamp', 'wb-listora' ),
				__( 'Professional Certification', 'wb-listora' ),
				__( 'K-12', 'wb-listora' ),
				__( 'Graduate', 'wb-listora' ),
				__( 'Vocational', 'wb-listora' ),
			),
		);
	}

	// ─── Type 9: Place / Attraction ───

	private static function place() {
		return array(
			'props'        => array(
				'name'        => __( 'Place', 'wb-listora' ),
				'schema_type' => 'TouristAttraction',
				'icon'        => 'dashicons-location',
				'color'       => '#0D9488',
				'is_default'  => true,
			),
			'field_groups' => array(
				array(
					'key'    => 'location',
					'label'  => __( 'Location', 'wb-listora' ),
					'icon'   => 'location',
					'order'  => 1,
					'fields' => array(
						self::f(
							'address',
							__( 'Address', 'wb-listora' ),
							'map_location',
							array(
								'required'     => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'address',
							)
						),
						self::f(
							'phone',
							__( 'Phone', 'wb-listora' ),
							'phone',
							array(
								'show_in_card' => true,
								'schema_prop'  => 'telephone',
							)
						),
					),
				),
				array(
					'key'    => 'place_info',
					'label'  => __( 'Place Details', 'wb-listora' ),
					'icon'   => 'info',
					'order'  => 2,
					'fields' => array(
						self::f(
							'admission_fee',
							__( 'Admission Fee', 'wb-listora' ),
							'price',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'isAccessibleForFree',
							)
						),
						self::f(
							'business_hours',
							__( 'Opening Hours', 'wb-listora' ),
							'business_hours',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'openingHoursSpecification',
							)
						),
						self::f(
							'duration_suggested',
							__( 'Suggested Duration', 'wb-listora' ),
							'text',
							array(
								'show_in_card' => true,
								'schema_prop'  => 'timeRequired',
							)
						),
						self::f( 'best_time_to_visit', __( 'Best Time to Visit', 'wb-listora' ), 'text' ),
						self::f(
							'accessibility',
							__( 'Accessibility', 'wb-listora' ),
							'multiselect',
							array(
								'filterable'  => true,
								'schema_prop' => 'accessibilityFeature',
								'options'     => array(),
							)
						),
						self::f( 'website', __( 'Website', 'wb-listora' ), 'url', array( 'schema_prop' => 'url' ) ),
					),
				),
				array(
					'key'    => 'media',
					'label'  => __( 'Media', 'wb-listora' ),
					'icon'   => 'images',
					'order'  => 3,
					'fields' => array(
						self::f( 'gallery', __( 'Photo Gallery', 'wb-listora' ), 'gallery', array( 'schema_prop' => 'image' ) ),
						self::f( 'social_links', __( 'Social Links', 'wb-listora' ), 'social_links', array( 'schema_prop' => 'sameAs' ) ),
					),
				),
			),
			'categories'   => array(
				__( 'Museum', 'wb-listora' ),
				__( 'Park', 'wb-listora' ),
				__( 'Monument', 'wb-listora' ),
				__( 'Beach', 'wb-listora' ),
				__( 'Temple', 'wb-listora' ),
				__( 'Zoo', 'wb-listora' ),
				__( 'Amusement Park', 'wb-listora' ),
				__( 'Garden', 'wb-listora' ),
				__( 'Historic Site', 'wb-listora' ),
				__( 'Viewpoint', 'wb-listora' ),
				__( 'Market', 'wb-listora' ),
			),
		);
	}

	// ─── Type 10: Classified ───

	private static function classified() {
		return array(
			'props'        => array(
				'name'            => __( 'Classified', 'wb-listora' ),
				'schema_type'     => 'Product',
				'icon'            => 'dashicons-tag',
				'color'           => '#CA8A04',
				'is_default'      => true,
				'expiration_days' => 60,
			),
			'field_groups' => array(
				array(
					'key'    => 'item_info',
					'label'  => __( 'Item Details', 'wb-listora' ),
					'icon'   => 'tag',
					'order'  => 1,
					'fields' => array(
						self::f(
							'price',
							__( 'Price', 'wb-listora' ),
							'price',
							array(
								'required'     => true,
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'offers.price',
							)
						),
						self::f(
							'condition',
							__( 'Condition', 'wb-listora' ),
							'select',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'itemCondition',
								'options'      => array(
									array(
										'value' => 'new',
										'label' => __( 'New', 'wb-listora' ),
									),
									array(
										'value' => 'like-new',
										'label' => __( 'Like New', 'wb-listora' ),
									),
									array(
										'value' => 'used',
										'label' => __( 'Used', 'wb-listora' ),
									),
									array(
										'value' => 'refurbished',
										'label' => __( 'Refurbished', 'wb-listora' ),
									),
									array(
										'value' => 'for-parts',
										'label' => __( 'For Parts', 'wb-listora' ),
									),
								),
							)
						),
						self::f(
							'availability',
							__( 'Availability', 'wb-listora' ),
							'select',
							array(
								'filterable'   => true,
								'show_in_card' => true,
								'schema_prop'  => 'availability',
								'options'      => array(
									array(
										'value' => 'available',
										'label' => __( 'Available', 'wb-listora' ),
									),
									array(
										'value' => 'sold',
										'label' => __( 'Sold', 'wb-listora' ),
									),
									array(
										'value' => 'reserved',
										'label' => __( 'Reserved', 'wb-listora' ),
									),
								),
							)
						),
					),
				),
				array(
					'key'    => 'seller',
					'label'  => __( 'Seller Contact', 'wb-listora' ),
					'icon'   => 'user',
					'order'  => 2,
					'fields' => array(
						self::f(
							'address',
							__( 'Location', 'wb-listora' ),
							'map_location',
							array(
								'filterable'   => true,
								'show_in_card' => true,
							)
						),
						self::f( 'seller_name', __( 'Seller Name', 'wb-listora' ), 'text', array( 'schema_prop' => 'seller.name' ) ),
						self::f( 'seller_phone', __( 'Seller Phone', 'wb-listora' ), 'phone', array( 'schema_prop' => 'seller.telephone' ) ),
						self::f( 'seller_email', __( 'Seller Email', 'wb-listora' ), 'email', array( 'schema_prop' => 'seller.email' ) ),
					),
				),
				array(
					'key'    => 'media',
					'label'  => __( 'Media', 'wb-listora' ),
					'icon'   => 'images',
					'order'  => 3,
					'fields' => array(
						self::f( 'gallery', __( 'Photo Gallery', 'wb-listora' ), 'gallery', array( 'schema_prop' => 'image' ) ),
					),
				),
			),
			'categories'   => array(
				__( 'Vehicles', 'wb-listora' ),
				__( 'Electronics', 'wb-listora' ),
				__( 'Furniture', 'wb-listora' ),
				__( 'Clothing', 'wb-listora' ),
				__( 'Sports', 'wb-listora' ),
				__( 'Books', 'wb-listora' ),
				__( 'Collectibles', 'wb-listora' ),
				__( 'Tools', 'wb-listora' ),
				__( 'Pets', 'wb-listora' ),
				__( 'Services', 'wb-listora' ),
				__( 'Other', 'wb-listora' ),
			),
		);
	}
}
