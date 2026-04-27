<?php
/**
 * Education Demo Pack — 6 schools, courses, bootcamps.
 *
 * @package WBListora\Demo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-demo-seeder.php';

use WBListora\Demo\Demo_Seeder;

// ── Categories ──

Demo_Seeder::ensure_categories(
	array(
		'online-course'  => 'Online Course',
		'university'     => 'University',
		'tutoring'       => 'Tutoring',
		'language'       => 'Language School',
		'bootcamp'       => 'Coding Bootcamp',
		'certification'  => 'Professional Certification',
		'k12'            => 'K-12',
		'graduate'       => 'Graduate',
		'vocational'     => 'Vocational',
	)
);

Demo_Seeder::ensure_features(
	array(
		'online'         => 'Online',
		'in-person'      => 'In-Person',
		'hybrid'         => 'Hybrid',
		'self-paced'     => 'Self-Paced',
		'live-cohort'    => 'Live Cohort',
		'job-guarantee'  => 'Job Guarantee',
		'financial-aid'  => 'Financial Aid',
		'small-class'    => 'Small Class Size',
	)
);

// ── Listings ──

$listings = array(
	array(
		'title'      => 'Hudson Valley Coding Bootcamp — Full-Stack JavaScript',
		'type'       => 'education',
		'categories' => array( 'Coding Bootcamp' ),
		'featured'   => true,
		'features'   => array( 'in-person', 'live-cohort', 'job-guarantee', 'financial-aid' ),
		'tags'       => array( 'javascript', 'react', 'node', 'bootcamp' ),
		'content'    => 'A 14-week immersive bootcamp covering modern JavaScript, React, Node.js, SQL/NoSQL, and DevOps fundamentals. Small cohorts (max 18 students) with a 1:6 instructor ratio, daily code reviews, and a capstone project for a real local nonprofit. 92% job placement within 6 months — backed by our money-back job guarantee. Income share agreements and partial scholarships available.',
		'address'    => array(
			'address' => 'Beacon, NY',
			'city'    => 'Beacon',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'        => array(
				'address' => '450 Main St, Beacon, NY 12508',
				'lat'     => 41.5048,
				'lng'     => -73.9698,
				'city'    => 'Beacon',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'          => '(845) 555-0142',
			'website'        => 'https://hvbootcamp.example.com',
			'email'          => 'admissions@hvbootcamp.example.com',
			'provider'       => 'Hudson Valley Bootcamp',
			'course_level'   => 'intermediate',
			'duration'       => '14 weeks',
			'price'          => 14500,
			'format'         => 'in-person',
			'start_date'     => gmdate( 'Y-m-d', strtotime( '+30 days' ) ),
			'certification'  => true,
			'enrollment_url' => 'https://hvbootcamp.example.com/apply',
			'prerequisites'  => 'No prior coding experience required. Comfort with computers and 60 hours of pre-work before week 1.',
			'business_hours' => Demo_Seeder::make_hours( '09:00', '18:00', true ),
		),
		'reviews'    => array(
			array( 5, 'Career-changing program', 'I came in as a barista with zero coding background. Three months after graduating I started as a junior dev at a fintech startup. The mentors are incredible.' ),
			array( 4, 'Intense but worth it', 'Be prepared for 60-hour weeks. The pace is brutal but you will come out a different person. Career services are excellent.' ),
		),
	),
	array(
		'title'      => 'Modern Spanish Conversation — Online Live Cohort',
		'type'       => 'education',
		'categories' => array( 'Language School' ),
		'features'   => array( 'online', 'live-cohort', 'small-class' ),
		'tags'       => array( 'spanish', 'language', 'live', 'conversational' ),
		'content'    => 'Eight-week conversational Spanish course taught entirely in Spanish from day one. Live Zoom classes with native instructors from Mexico City, Madrid, and Buenos Aires. Maximum 6 students per class so everyone speaks. Comes with a curated Anki deck, weekly homework, and a private community for practice partners. CEFR A2-B1 sweet spot.',
		'address'    => array(
			'address' => 'Online (US)',
			'city'    => 'New York',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'        => array(
				'address' => 'Online — Brooklyn, NY HQ',
				'lat'     => 40.6782,
				'lng'     => -73.9442,
				'city'    => 'Brooklyn',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'          => '(718) 555-0247',
			'website'        => 'https://modernspanish.example.com',
			'email'          => 'hola@modernspanish.example.com',
			'provider'       => 'Modern Spanish',
			'course_level'   => 'intermediate',
			'duration'       => '8 weeks',
			'price'          => 425,
			'format'         => 'online',
			'start_date'     => gmdate( 'Y-m-d', strtotime( '+14 days' ) ),
			'certification'  => false,
			'enrollment_url' => 'https://modernspanish.example.com/enroll',
			'prerequisites'  => 'Some prior Spanish exposure recommended (Duolingo Section 2+ or 1 year of high school).',
		),
		'reviews'    => array(
			array( 5, 'Best language class I have taken', 'The conversation-first approach pushed me out of my comfort zone in the best way. I went from frozen to fluent-ish in 8 weeks.' ),
		),
	),
	array(
		'title'      => 'AP Calculus Tutoring — 1:1 with Stanford Grad',
		'type'       => 'education',
		'categories' => array( 'Tutoring' ),
		'features'   => array( 'online', 'in-person', 'self-paced' ),
		'tags'       => array( 'calculus', 'ap', 'sat', 'math', 'tutoring' ),
		'content'    => 'Personalized 1:1 calculus tutoring for high schoolers tackling AP Calc AB or BC. I am a Stanford math grad who has been tutoring for 9 years — average score improvement of 1.4 points on the AP exam. Sessions are 60 minutes, weekly or bi-weekly. Online via Zoom + GoodNotes, or in-person on the Upper East Side.',
		'address'    => array(
			'address' => 'Upper East Side, Manhattan, NY',
			'city'    => 'Manhattan',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'        => array(
				'address' => 'Upper East Side, Manhattan, NY 10075',
				'lat'     => 40.7740,
				'lng'     => -73.9588,
				'city'    => 'Manhattan',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'          => '(212) 555-0871',
			'website'        => 'https://apcalc.example.com',
			'email'          => 'tutor@apcalc.example.com',
			'provider'       => 'Riley Wong, M.S.',
			'course_level'   => 'advanced',
			'duration'       => 'Per-session (1 hour)',
			'price'          => 145,
			'format'         => 'hybrid',
			'start_date'     => gmdate( 'Y-m-d' ),
			'certification'  => false,
			'enrollment_url' => 'https://apcalc.example.com/book',
			'prerequisites'  => 'Currently enrolled in or about to start AP Calculus AB or BC.',
		),
		'reviews'    => array(
			array( 5, 'Got a 5 thanks to Riley', 'Patient, deeply knowledgeable, and great at finding the gaps you didn’t know you had. Worth every penny.' ),
			array( 5, 'Math anxiety gone', 'My daughter went from a B- to an A in two months. More importantly, she actually likes calc now.' ),
		),
	),
	array(
		'title'      => 'Data Analytics Certificate — Self-Paced',
		'type'       => 'education',
		'categories' => array( 'Online Course', 'Professional Certification' ),
		'featured'   => true,
		'features'   => array( 'online', 'self-paced' ),
		'tags'       => array( 'data', 'analytics', 'sql', 'tableau', 'python' ),
		'content'    => 'A self-paced certificate program covering SQL, Python (pandas/numpy), Tableau, and statistics fundamentals. 11 modules, 60+ hours of video, 5 capstone projects you can publish to GitHub. Lifetime access. Optional weekly office hours with instructors. Industry-recognized certificate on completion. Great for analysts pivoting from Excel.',
		'address'    => array(
			'address' => 'Online — based in Boston, MA',
			'city'    => 'Boston',
			'state'   => 'MA',
			'country' => 'US',
		),
		'meta'       => array(
			'address'        => array(
				'address' => 'Online — Boston, MA HQ',
				'lat'     => 42.3601,
				'lng'     => -71.0589,
				'city'    => 'Boston',
				'state'   => 'MA',
				'country' => 'US',
			),
			'phone'          => '(617) 555-0123',
			'website'        => 'https://dataanalyticscert.example.com',
			'email'          => 'support@dataanalyticscert.example.com',
			'provider'       => 'Beacon Hill Data',
			'course_level'   => 'beginner',
			'duration'       => 'Self-paced (avg 12 weeks)',
			'price'          => 695,
			'format'         => 'online',
			'start_date'     => gmdate( 'Y-m-d' ),
			'certification'  => true,
			'enrollment_url' => 'https://dataanalyticscert.example.com/enroll',
			'prerequisites'  => 'Comfort with basic spreadsheets. No prior coding required.',
		),
		'reviews'    => array(
			array( 4, 'Solid foundation', 'Great mix of theory and applied projects. The Tableau module was the highlight. Wish there were more advanced Python content.' ),
		),
	),
	array(
		'title'      => 'Brooklyn Music Academy — Group Piano (Kids 7-12)',
		'type'       => 'education',
		'categories' => array( 'K-12' ),
		'features'   => array( 'in-person', 'small-class' ),
		'tags'       => array( 'piano', 'music', 'kids', 'group-class' ),
		'content'    => 'A 12-week group piano program for kids 7-12. Small classes of 4 students, taught by Juilliard-trained instructors. Each student has their own keyboard during class. Recital at the end of the program for friends and family. Reading sheet music, basic theory, and one fun pop song students choose together.',
		'address'    => array(
			'address' => 'Park Slope, Brooklyn, NY',
			'city'    => 'Brooklyn',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'        => array(
				'address' => '88 Garfield Pl, Brooklyn, NY 11215',
				'lat'     => 40.6735,
				'lng'     => -73.9785,
				'city'    => 'Brooklyn',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'          => '(718) 555-0388',
			'website'        => 'https://bkmusic.example.com',
			'email'          => 'hello@bkmusic.example.com',
			'provider'       => 'Brooklyn Music Academy',
			'course_level'   => 'beginner',
			'duration'       => '12 weeks (1 hour/week)',
			'price'          => 480,
			'format'         => 'in-person',
			'start_date'     => gmdate( 'Y-m-d', strtotime( '+21 days' ) ),
			'certification'  => false,
			'enrollment_url' => 'https://bkmusic.example.com/register',
			'prerequisites'  => 'No prior music experience needed.',
			'business_hours' => Demo_Seeder::make_hours( '15:00', '20:00', false ),
		),
		'reviews'    => array(
			array( 5, 'My son loves it', 'After 6 weeks he was playing simple two-handed pieces. The recital was the highlight of his fall.' ),
			array( 4, 'Great teachers', 'Patient and engaging with the kids. Wish the class were a touch longer than an hour.' ),
		),
	),
	array(
		'title'      => 'NY Welding Institute — Trade Certification',
		'type'       => 'education',
		'categories' => array( 'Vocational', 'Professional Certification' ),
		'features'   => array( 'in-person', 'job-guarantee', 'financial-aid' ),
		'tags'       => array( 'welding', 'trade', 'certification', 'career' ),
		'content'    => 'A 16-week hands-on welding certification covering MIG, TIG, and stick welding. Graduates leave with an AWS-recognized certification and a portfolio of weld samples. We work directly with local fabrication shops and unions for placement — over 80% of graduates land a job within 30 days. Veterans accepted under the GI Bill. Tuition assistance available for NY residents.',
		'address'    => array(
			'address' => 'Yonkers, NY',
			'city'    => 'Yonkers',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'        => array(
				'address' => '202 Tuckahoe Rd, Yonkers, NY 10710',
				'lat'     => 40.9587,
				'lng'     => -73.8632,
				'city'    => 'Yonkers',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'          => '(914) 555-0421',
			'website'        => 'https://nyweldinginstitute.example.com',
			'email'          => 'admissions@nyweldinginstitute.example.com',
			'provider'       => 'NY Welding Institute',
			'course_level'   => 'beginner',
			'duration'       => '16 weeks',
			'price'          => 8950,
			'format'         => 'in-person',
			'start_date'     => gmdate( 'Y-m-d', strtotime( '+45 days' ) ),
			'certification'  => true,
			'enrollment_url' => 'https://nyweldinginstitute.example.com/apply',
			'prerequisites'  => 'High school diploma or GED. Must be 18+.',
			'business_hours' => Demo_Seeder::make_hours( '07:30', '17:00', true ),
		),
		'reviews'    => array(
			array( 5, 'Changed my career', 'I was a line cook making $16/hour. Six months after graduating I am at $34/hour as a structural welder. Hands-on training all the way.' ),
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

	// Education services: info session, 1:1 advising.
	$services = array(
		array( 'Free Info Session', 0, 45, 'A 45-minute live overview of the curriculum, outcomes, and admissions process. Q&A with current students.', 'Admissions' ),
		array( '1:1 Career Advising', 0, 30, '30-minute call with a career advisor to map your goals to the right program track.', 'Advising' ),
	);

	Demo_Seeder::seed_pack_extras( $post_id, 'education', $idx, $services );
}
