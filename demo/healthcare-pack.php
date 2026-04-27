<?php
/**
 * Healthcare Demo Pack — 6 clinics / doctors with Schema.org Physician markup.
 *
 * @package WBListora\Demo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-demo-seeder.php';

use WBListora\Demo\Demo_Seeder;

// ── Categories ──

Demo_Seeder::ensure_categories(
	array(
		'general-practitioner' => 'General Practitioner',
		'dentist'              => 'Dentist',
		'dermatologist'        => 'Dermatologist',
		'cardiologist'         => 'Cardiologist',
		'pediatrician'         => 'Pediatrician',
		'orthopedist'          => 'Orthopedist',
		'ophthalmologist'      => 'Ophthalmologist',
		'psychiatrist'         => 'Psychiatrist',
		'neurologist'          => 'Neurologist',
		'gynecologist'         => 'Gynecologist',
	)
);

Demo_Seeder::ensure_features(
	array(
		'accepting-new'      => 'Accepting New Patients',
		'telemedicine'       => 'Telemedicine',
		'wheelchair'         => 'Wheelchair Accessible',
		'spanish'            => 'Spanish Spoken',
		'mandarin'           => 'Mandarin Spoken',
		'evening-hours'      => 'Evening Hours',
		'weekend-hours'      => 'Weekend Hours',
		'on-site-pharmacy'   => 'On-Site Pharmacy',
	)
);

// ── Listings ──

$listings = array(
	array(
		'title'      => 'Dr. Maya Patel, MD — Family Medicine',
		'type'       => 'healthcare',
		'categories' => array( 'General Practitioner' ),
		'featured'   => true,
		'features'   => array( 'accepting-new', 'telemedicine', 'wheelchair', 'spanish' ),
		'tags'       => array( 'family-medicine', 'primary-care', 'preventive' ),
		'content'    => 'Dr. Maya Patel has practiced family medicine in Manhattan for over 15 years, focused on preventive care and chronic disease management. She sees patients ages 5 and up. New patients welcome — most insurance accepted, including Medicare and Medicaid. Same-week sick visits and 30-minute new-patient intake appointments. Telemedicine available for follow-ups.',
		'address'    => array(
			'address' => 'Murray Hill, Manhattan, NY',
			'city'    => 'Manhattan',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'              => array(
				'address' => '212 E 38th St, New York, NY 10016',
				'lat'     => 40.7479,
				'lng'     => -73.9760,
				'city'    => 'Manhattan',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'                => '(212) 555-0445',
			'website'              => 'https://drpatelmd.example.com',
			'email'                => 'office@drpatelmd.example.com',
			'specialty'            => array( 'family-medicine' ),
			'qualifications'       => 'MD, Columbia University; Board Certified, American Board of Family Medicine',
			'insurance_accepted'   => array( 'Aetna', 'Anthem', 'Cigna', 'United Healthcare', 'Medicare', 'Medicaid' ),
			'hospital_affiliation' => 'Mount Sinai Hospital',
			'consultation_fee'     => 175,
			'languages_spoken'     => array( 'English', 'Spanish', 'Hindi' ),
			'experience_years'     => 15,
			'appointment_url'      => 'https://drpatelmd.example.com/book',
			'business_hours'       => Demo_Seeder::make_hours( '08:00', '18:00', true ),
		),
		'reviews'    => array(
			array( 5, 'Caring and thorough', 'Dr. Patel takes the time to listen. Never feel rushed. Office staff is friendly and the wait is never long.' ),
			array( 5, 'Great primary care doc', 'Switched to Dr. Patel after my old doctor retired. So glad I did. She is genuinely interested in your health, not just billing codes.' ),
		),
	),
	array(
		'title'      => 'Brooklyn Smile Dental — Dr. Chen, DDS',
		'type'       => 'healthcare',
		'categories' => array( 'Dentist' ),
		'features'   => array( 'accepting-new', 'evening-hours', 'weekend-hours', 'mandarin' ),
		'tags'       => array( 'dental', 'cleanings', 'cosmetic-dentistry', 'invisalign' ),
		'content'    => 'A modern, family-friendly dental practice in Park Slope offering general dentistry, cleanings, cosmetic treatments, and Invisalign. Dr. Chen and her team make dental visits easy — gentle techniques, transparent pricing, and a soothing office. Saturday morning slots available. Most PPO insurance accepted; in-house savings plan for the uninsured.',
		'address'    => array(
			'address' => 'Park Slope, Brooklyn, NY',
			'city'    => 'Brooklyn',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'              => array(
				'address' => '450 5th Ave, Brooklyn, NY 11215',
				'lat'     => 40.6720,
				'lng'     => -73.9847,
				'city'    => 'Brooklyn',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'                => '(718) 555-0822',
			'website'              => 'https://brooklynsmile.example.com',
			'email'                => 'hello@brooklynsmile.example.com',
			'specialty'            => array( 'dentist' ),
			'qualifications'       => 'DDS, NYU College of Dentistry',
			'insurance_accepted'   => array( 'Delta Dental', 'MetLife', 'Cigna Dental', 'Guardian' ),
			'hospital_affiliation' => '',
			'consultation_fee'     => 125,
			'languages_spoken'     => array( 'English', 'Mandarin' ),
			'experience_years'     => 9,
			'appointment_url'      => 'https://brooklynsmile.example.com/appointments',
			'business_hours'       => Demo_Seeder::make_hours( '09:00', '19:00', false ),
		),
		'reviews'    => array(
			array( 5, 'Best dentist in Brooklyn', 'I had massive dental anxiety. Dr. Chen and her team made it actually pleasant. The Saturday hours saved me.' ),
		),
	),
	array(
		'title'      => 'Dr. Aaron Mendez, MD — Pediatrics',
		'type'       => 'healthcare',
		'categories' => array( 'Pediatrician' ),
		'featured'   => true,
		'features'   => array( 'accepting-new', 'spanish', 'evening-hours' ),
		'tags'       => array( 'pediatrics', 'newborns', 'kids', 'wellness-checks' ),
		'content'    => 'Dr. Mendez is a board-certified pediatrician with a busy Queens practice serving newborns through age 21. Lactation support, developmental screenings, sports physicals, and routine immunizations. New babies welcome — same-week first appointment for newborns. Spanish-speaking front desk and bilingual provider.',
		'address'    => array(
			'address' => 'Forest Hills, Queens, NY',
			'city'    => 'Queens',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'              => array(
				'address' => '108-22 Queens Blvd, Forest Hills, NY 11375',
				'lat'     => 40.7273,
				'lng'     => -73.8456,
				'city'    => 'Queens',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'                => '(718) 555-0930',
			'website'              => 'https://drmendezped.example.com',
			'email'                => 'office@drmendezped.example.com',
			'specialty'            => array( 'pediatrician' ),
			'qualifications'       => 'MD, SUNY Downstate; Board Certified, American Board of Pediatrics',
			'insurance_accepted'   => array( 'Aetna', 'Empire BCBS', 'Cigna', 'Healthfirst', 'Medicaid' ),
			'hospital_affiliation' => 'NewYork-Presbyterian Queens',
			'consultation_fee'     => 165,
			'languages_spoken'     => array( 'English', 'Spanish' ),
			'experience_years'     => 12,
			'appointment_url'      => 'https://drmendezped.example.com/book',
			'business_hours'       => Demo_Seeder::make_hours( '08:00', '20:00', true ),
		),
		'reviews'    => array(
			array( 5, 'Wonderful with kids', 'Both my kids actually look forward to visiting Dr. Mendez. Patient, kind, and never alarmist.' ),
			array( 4, 'Great practice', 'Front desk is helpful and bilingual which is a huge plus for our family. Wait can be a bit long during cold season but the care is worth it.' ),
		),
	),
	array(
		'title'      => 'Manhattan Dermatology Group',
		'type'       => 'healthcare',
		'categories' => array( 'Dermatologist' ),
		'features'   => array( 'accepting-new', 'telemedicine', 'wheelchair' ),
		'tags'       => array( 'dermatology', 'acne', 'mole-checks', 'cosmetic' ),
		'content'    => 'A multi-provider dermatology practice on the Upper East Side covering medical, surgical, and cosmetic dermatology. Annual skin checks, acne and rosacea management, mole removal, Mohs surgery referrals, and cosmetic injectables. Telederm consults available for established patients.',
		'address'    => array(
			'address' => 'Upper East Side, Manhattan, NY',
			'city'    => 'Manhattan',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'              => array(
				'address' => '1080 Park Ave, New York, NY 10128',
				'lat'     => 40.7848,
				'lng'     => -73.9559,
				'city'    => 'Manhattan',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'                => '(212) 555-0623',
			'website'              => 'https://manhattanderm.example.com',
			'email'                => 'info@manhattanderm.example.com',
			'specialty'            => array( 'dermatologist' ),
			'qualifications'       => 'MD, Multiple board-certified providers',
			'insurance_accepted'   => array( 'Aetna', 'Anthem', 'Cigna', 'Oxford', 'United Healthcare' ),
			'hospital_affiliation' => 'Lenox Hill Hospital',
			'consultation_fee'     => 295,
			'languages_spoken'     => array( 'English', 'French', 'Spanish' ),
			'experience_years'     => 22,
			'appointment_url'      => 'https://manhattanderm.example.com/book',
			'business_hours'       => Demo_Seeder::make_hours( '08:30', '17:30', true ),
		),
		'reviews'    => array(
			array( 5, 'Annual skin check made easy', 'Quick and thorough. They caught a precancerous mole that two other doctors had said was fine.' ),
		),
	),
	array(
		'title'      => 'Dr. Nadia Rahman, MD — Cardiology',
		'type'       => 'healthcare',
		'categories' => array( 'Cardiologist' ),
		'features'   => array( 'accepting-new', 'telemedicine', 'wheelchair' ),
		'tags'       => array( 'cardiology', 'heart-health', 'echocardiogram' ),
		'content'    => 'Dr. Rahman is a board-certified cardiologist with subspecialty training in preventive cardiology and women’s heart health. She offers comprehensive cardiac evaluations, echocardiograms, stress tests, and lipid management. Particularly experienced with patients who have a family history of early heart disease.',
		'address'    => array(
			'address' => 'Midtown East, Manhattan, NY',
			'city'    => 'Manhattan',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'              => array(
				'address' => '475 Park Ave South, New York, NY 10016',
				'lat'     => 40.7421,
				'lng'     => -73.9821,
				'city'    => 'Manhattan',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'                => '(212) 555-0710',
			'website'              => 'https://drrahmancardio.example.com',
			'email'                => 'office@drrahmancardio.example.com',
			'specialty'            => array( 'cardiologist' ),
			'qualifications'       => 'MD, Johns Hopkins; Board Certified, American Board of Internal Medicine — Cardiovascular Disease',
			'insurance_accepted'   => array( 'Aetna', 'Empire BCBS', 'Cigna', 'United Healthcare', 'Medicare' ),
			'hospital_affiliation' => 'Mount Sinai Heart',
			'consultation_fee'     => 425,
			'languages_spoken'     => array( 'English', 'Urdu', 'Arabic' ),
			'experience_years'     => 18,
			'appointment_url'      => 'https://drrahmancardio.example.com/book',
			'business_hours'       => Demo_Seeder::make_hours( '08:00', '17:00', true ),
		),
		'reviews'    => array(
			array( 5, 'Saved my life', 'Dr. Rahman ordered the right tests when other doctors brushed off my symptoms. I had a previously undetected heart issue. Forever grateful.' ),
		),
	),
	array(
		'title'      => 'Hudson Mind Wellness — Adult & Adolescent Psychiatry',
		'type'       => 'healthcare',
		'categories' => array( 'Psychiatrist' ),
		'features'   => array( 'accepting-new', 'telemedicine', 'evening-hours' ),
		'tags'       => array( 'psychiatry', 'therapy', 'medication-management', 'anxiety', 'depression' ),
		'content'    => 'A small group psychiatry practice offering evaluations, medication management, and integrated therapy. We see adults and adolescents 13+. Most appointments are 100% telehealth, with optional in-person visits at our Tribeca office. Sliding scale slots available for self-pay patients. We don’t prescribe controlled substances on a first visit.',
		'address'    => array(
			'address' => 'Tribeca, Manhattan, NY',
			'city'    => 'Manhattan',
			'state'   => 'NY',
			'country' => 'US',
		),
		'meta'       => array(
			'address'              => array(
				'address' => '90 Hudson St, New York, NY 10013',
				'lat'     => 40.7204,
				'lng'     => -74.0083,
				'city'    => 'Manhattan',
				'state'   => 'NY',
				'country' => 'US',
			),
			'phone'                => '(212) 555-0498',
			'website'              => 'https://hudsonmind.example.com',
			'email'                => 'intake@hudsonmind.example.com',
			'specialty'            => array( 'psychiatrist' ),
			'qualifications'       => 'MD/DO providers, board-certified in Psychiatry',
			'insurance_accepted'   => array( 'Aetna', 'Cigna', 'Oxford', 'Out-of-network with super-bill' ),
			'hospital_affiliation' => '',
			'consultation_fee'     => 350,
			'languages_spoken'     => array( 'English', 'Spanish' ),
			'experience_years'     => 11,
			'appointment_url'      => 'https://hudsonmind.example.com/intake',
			'business_hours'       => Demo_Seeder::make_hours( '09:00', '20:00', true ),
		),
		'reviews'    => array(
			array( 5, 'Felt heard from day one', 'After bouncing between two other psychiatrists, my new provider here is a perfect fit. Telehealth is seamless.' ),
			array( 4, 'Solid practice', 'Intake was thorough. Wait for first appointment was about 3 weeks but worth it.' ),
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

	// Healthcare services: new patient intake, telemedicine consult.
	$services = array(
		array( 'New Patient Intake (60 min)', 175, 60, 'Comprehensive new-patient evaluation including medical history, exam, and personalized care plan.', 'Visits' ),
		array( 'Telemedicine Follow-up', 95, 20, 'A 20-minute virtual follow-up for established patients — meds, labs, and brief check-ins.', 'Visits' ),
		array( 'Annual Wellness Visit', 0, 30, 'Covered annual preventive visit for most insurance plans. Vitals, screenings, and goal-setting.', 'Visits' ),
	);

	Demo_Seeder::seed_pack_extras( $post_id, 'healthcare', $idx, $services );
}
