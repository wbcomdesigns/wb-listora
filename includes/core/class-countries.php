<?php
/**
 * Countries: ISO 3166-1 alpha-2 codes, readable names, and the spellings
 * that mean the same country.
 *
 * Location terms were created from whatever text an address carried, so the
 * demo's "US", the geocoder's "United States" and a CSV's "USA" became three
 * country roots with listings split between them (card 10337180588). Every
 * location writer now resolves the country to its code first; the term keeps
 * a readable name and the code in term meta (owner decision 2026-09-25).
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Countries
 *
 * @since 1.9.0
 */
class Countries {

	/**
	 * Term meta key holding a country term's ISO code.
	 */
	const TERM_META = '_listora_country_code';

	/**
	 * ISO code => name in the site language.
	 *
	 * @return array<string, string>
	 */
	public static function names() {
		static $names = null;
		if ( null === $names ) {
			$names = self::build();
		}
		return $names;
	}

	/**
	 * The list, translated through the current locale.
	 *
	 * @return array<string, string>
	 */
	private static function build() {
		return array(
			'AF' => __( 'Afghanistan', 'wb-listora' ),
			'AX' => __( 'Åland Islands', 'wb-listora' ),
			'AL' => __( 'Albania', 'wb-listora' ),
			'DZ' => __( 'Algeria', 'wb-listora' ),
			'AS' => __( 'American Samoa', 'wb-listora' ),
			'AD' => __( 'Andorra', 'wb-listora' ),
			'AO' => __( 'Angola', 'wb-listora' ),
			'AI' => __( 'Anguilla', 'wb-listora' ),
			'AQ' => __( 'Antarctica', 'wb-listora' ),
			'AG' => __( 'Antigua and Barbuda', 'wb-listora' ),
			'AR' => __( 'Argentina', 'wb-listora' ),
			'AM' => __( 'Armenia', 'wb-listora' ),
			'AW' => __( 'Aruba', 'wb-listora' ),
			'AU' => __( 'Australia', 'wb-listora' ),
			'AT' => __( 'Austria', 'wb-listora' ),
			'AZ' => __( 'Azerbaijan', 'wb-listora' ),
			'BS' => __( 'Bahamas', 'wb-listora' ),
			'BH' => __( 'Bahrain', 'wb-listora' ),
			'BD' => __( 'Bangladesh', 'wb-listora' ),
			'BB' => __( 'Barbados', 'wb-listora' ),
			'BY' => __( 'Belarus', 'wb-listora' ),
			'BE' => __( 'Belgium', 'wb-listora' ),
			'BZ' => __( 'Belize', 'wb-listora' ),
			'BJ' => __( 'Benin', 'wb-listora' ),
			'BM' => __( 'Bermuda', 'wb-listora' ),
			'BT' => __( 'Bhutan', 'wb-listora' ),
			'BO' => __( 'Bolivia', 'wb-listora' ),
			'BQ' => __( 'Caribbean Netherlands', 'wb-listora' ),
			'BA' => __( 'Bosnia and Herzegovina', 'wb-listora' ),
			'BW' => __( 'Botswana', 'wb-listora' ),
			'BV' => __( 'Bouvet Island', 'wb-listora' ),
			'BR' => __( 'Brazil', 'wb-listora' ),
			'IO' => __( 'British Indian Ocean Territory', 'wb-listora' ),
			'BN' => __( 'Brunei', 'wb-listora' ),
			'BG' => __( 'Bulgaria', 'wb-listora' ),
			'BF' => __( 'Burkina Faso', 'wb-listora' ),
			'BI' => __( 'Burundi', 'wb-listora' ),
			'CV' => __( 'Cape Verde', 'wb-listora' ),
			'KH' => __( 'Cambodia', 'wb-listora' ),
			'CM' => __( 'Cameroon', 'wb-listora' ),
			'CA' => __( 'Canada', 'wb-listora' ),
			'KY' => __( 'Cayman Islands', 'wb-listora' ),
			'CF' => __( 'Central African Republic', 'wb-listora' ),
			'TD' => __( 'Chad', 'wb-listora' ),
			'CL' => __( 'Chile', 'wb-listora' ),
			'CN' => __( 'China', 'wb-listora' ),
			'CX' => __( 'Christmas Island', 'wb-listora' ),
			'CC' => __( 'Cocos (Keeling) Islands', 'wb-listora' ),
			'CO' => __( 'Colombia', 'wb-listora' ),
			'KM' => __( 'Comoros', 'wb-listora' ),
			'CG' => __( 'Congo', 'wb-listora' ),
			'CD' => __( 'Democratic Republic of the Congo', 'wb-listora' ),
			'CK' => __( 'Cook Islands', 'wb-listora' ),
			'CR' => __( 'Costa Rica', 'wb-listora' ),
			'CI' => __( 'Côte d\'Ivoire', 'wb-listora' ),
			'HR' => __( 'Croatia', 'wb-listora' ),
			'CU' => __( 'Cuba', 'wb-listora' ),
			'CW' => __( 'Curaçao', 'wb-listora' ),
			'CY' => __( 'Cyprus', 'wb-listora' ),
			'CZ' => __( 'Czechia', 'wb-listora' ),
			'DK' => __( 'Denmark', 'wb-listora' ),
			'DJ' => __( 'Djibouti', 'wb-listora' ),
			'DM' => __( 'Dominica', 'wb-listora' ),
			'DO' => __( 'Dominican Republic', 'wb-listora' ),
			'EC' => __( 'Ecuador', 'wb-listora' ),
			'EG' => __( 'Egypt', 'wb-listora' ),
			'SV' => __( 'El Salvador', 'wb-listora' ),
			'GQ' => __( 'Equatorial Guinea', 'wb-listora' ),
			'ER' => __( 'Eritrea', 'wb-listora' ),
			'EE' => __( 'Estonia', 'wb-listora' ),
			'SZ' => __( 'Eswatini', 'wb-listora' ),
			'ET' => __( 'Ethiopia', 'wb-listora' ),
			'FK' => __( 'Falkland Islands', 'wb-listora' ),
			'FO' => __( 'Faroe Islands', 'wb-listora' ),
			'FJ' => __( 'Fiji', 'wb-listora' ),
			'FI' => __( 'Finland', 'wb-listora' ),
			'FR' => __( 'France', 'wb-listora' ),
			'GF' => __( 'French Guiana', 'wb-listora' ),
			'PF' => __( 'French Polynesia', 'wb-listora' ),
			'TF' => __( 'French Southern Territories', 'wb-listora' ),
			'GA' => __( 'Gabon', 'wb-listora' ),
			'GM' => __( 'Gambia', 'wb-listora' ),
			'GE' => __( 'Georgia', 'wb-listora' ),
			'DE' => __( 'Germany', 'wb-listora' ),
			'GH' => __( 'Ghana', 'wb-listora' ),
			'GI' => __( 'Gibraltar', 'wb-listora' ),
			'GR' => __( 'Greece', 'wb-listora' ),
			'GL' => __( 'Greenland', 'wb-listora' ),
			'GD' => __( 'Grenada', 'wb-listora' ),
			'GP' => __( 'Guadeloupe', 'wb-listora' ),
			'GU' => __( 'Guam', 'wb-listora' ),
			'GT' => __( 'Guatemala', 'wb-listora' ),
			'GG' => __( 'Guernsey', 'wb-listora' ),
			'GN' => __( 'Guinea', 'wb-listora' ),
			'GW' => __( 'Guinea-Bissau', 'wb-listora' ),
			'GY' => __( 'Guyana', 'wb-listora' ),
			'HT' => __( 'Haiti', 'wb-listora' ),
			'HM' => __( 'Heard Island and McDonald Islands', 'wb-listora' ),
			'VA' => __( 'Vatican City', 'wb-listora' ),
			'HN' => __( 'Honduras', 'wb-listora' ),
			'HK' => __( 'Hong Kong', 'wb-listora' ),
			'HU' => __( 'Hungary', 'wb-listora' ),
			'IS' => __( 'Iceland', 'wb-listora' ),
			'IN' => __( 'India', 'wb-listora' ),
			'ID' => __( 'Indonesia', 'wb-listora' ),
			'IR' => __( 'Iran', 'wb-listora' ),
			'IQ' => __( 'Iraq', 'wb-listora' ),
			'IE' => __( 'Ireland', 'wb-listora' ),
			'IM' => __( 'Isle of Man', 'wb-listora' ),
			'IL' => __( 'Israel', 'wb-listora' ),
			'IT' => __( 'Italy', 'wb-listora' ),
			'JM' => __( 'Jamaica', 'wb-listora' ),
			'JP' => __( 'Japan', 'wb-listora' ),
			'JE' => __( 'Jersey', 'wb-listora' ),
			'JO' => __( 'Jordan', 'wb-listora' ),
			'KZ' => __( 'Kazakhstan', 'wb-listora' ),
			'KE' => __( 'Kenya', 'wb-listora' ),
			'KI' => __( 'Kiribati', 'wb-listora' ),
			'KP' => __( 'North Korea', 'wb-listora' ),
			'KR' => __( 'South Korea', 'wb-listora' ),
			'KW' => __( 'Kuwait', 'wb-listora' ),
			'KG' => __( 'Kyrgyzstan', 'wb-listora' ),
			'LA' => __( 'Laos', 'wb-listora' ),
			'LV' => __( 'Latvia', 'wb-listora' ),
			'LB' => __( 'Lebanon', 'wb-listora' ),
			'LS' => __( 'Lesotho', 'wb-listora' ),
			'LR' => __( 'Liberia', 'wb-listora' ),
			'LY' => __( 'Libya', 'wb-listora' ),
			'LI' => __( 'Liechtenstein', 'wb-listora' ),
			'LT' => __( 'Lithuania', 'wb-listora' ),
			'LU' => __( 'Luxembourg', 'wb-listora' ),
			'MO' => __( 'Macao', 'wb-listora' ),
			'MG' => __( 'Madagascar', 'wb-listora' ),
			'MW' => __( 'Malawi', 'wb-listora' ),
			'MY' => __( 'Malaysia', 'wb-listora' ),
			'MV' => __( 'Maldives', 'wb-listora' ),
			'ML' => __( 'Mali', 'wb-listora' ),
			'MT' => __( 'Malta', 'wb-listora' ),
			'MH' => __( 'Marshall Islands', 'wb-listora' ),
			'MQ' => __( 'Martinique', 'wb-listora' ),
			'MR' => __( 'Mauritania', 'wb-listora' ),
			'MU' => __( 'Mauritius', 'wb-listora' ),
			'YT' => __( 'Mayotte', 'wb-listora' ),
			'MX' => __( 'Mexico', 'wb-listora' ),
			'FM' => __( 'Micronesia', 'wb-listora' ),
			'MD' => __( 'Moldova', 'wb-listora' ),
			'MC' => __( 'Monaco', 'wb-listora' ),
			'MN' => __( 'Mongolia', 'wb-listora' ),
			'ME' => __( 'Montenegro', 'wb-listora' ),
			'MS' => __( 'Montserrat', 'wb-listora' ),
			'MA' => __( 'Morocco', 'wb-listora' ),
			'MZ' => __( 'Mozambique', 'wb-listora' ),
			'MM' => __( 'Myanmar', 'wb-listora' ),
			'NA' => __( 'Namibia', 'wb-listora' ),
			'NR' => __( 'Nauru', 'wb-listora' ),
			'NP' => __( 'Nepal', 'wb-listora' ),
			'NL' => __( 'Netherlands', 'wb-listora' ),
			'NC' => __( 'New Caledonia', 'wb-listora' ),
			'NZ' => __( 'New Zealand', 'wb-listora' ),
			'NI' => __( 'Nicaragua', 'wb-listora' ),
			'NE' => __( 'Niger', 'wb-listora' ),
			'NG' => __( 'Nigeria', 'wb-listora' ),
			'NU' => __( 'Niue', 'wb-listora' ),
			'NF' => __( 'Norfolk Island', 'wb-listora' ),
			'MK' => __( 'North Macedonia', 'wb-listora' ),
			'MP' => __( 'Northern Mariana Islands', 'wb-listora' ),
			'NO' => __( 'Norway', 'wb-listora' ),
			'OM' => __( 'Oman', 'wb-listora' ),
			'PK' => __( 'Pakistan', 'wb-listora' ),
			'PW' => __( 'Palau', 'wb-listora' ),
			'PS' => __( 'Palestine', 'wb-listora' ),
			'PA' => __( 'Panama', 'wb-listora' ),
			'PG' => __( 'Papua New Guinea', 'wb-listora' ),
			'PY' => __( 'Paraguay', 'wb-listora' ),
			'PE' => __( 'Peru', 'wb-listora' ),
			'PH' => __( 'Philippines', 'wb-listora' ),
			'PN' => __( 'Pitcairn Islands', 'wb-listora' ),
			'PL' => __( 'Poland', 'wb-listora' ),
			'PT' => __( 'Portugal', 'wb-listora' ),
			'PR' => __( 'Puerto Rico', 'wb-listora' ),
			'QA' => __( 'Qatar', 'wb-listora' ),
			'RE' => __( 'Réunion', 'wb-listora' ),
			'RO' => __( 'Romania', 'wb-listora' ),
			'RU' => __( 'Russia', 'wb-listora' ),
			'RW' => __( 'Rwanda', 'wb-listora' ),
			'BL' => __( 'Saint Barthélemy', 'wb-listora' ),
			'SH' => __( 'Saint Helena', 'wb-listora' ),
			'KN' => __( 'Saint Kitts and Nevis', 'wb-listora' ),
			'LC' => __( 'Saint Lucia', 'wb-listora' ),
			'MF' => __( 'Saint Martin', 'wb-listora' ),
			'PM' => __( 'Saint Pierre and Miquelon', 'wb-listora' ),
			'VC' => __( 'Saint Vincent and the Grenadines', 'wb-listora' ),
			'WS' => __( 'Samoa', 'wb-listora' ),
			'SM' => __( 'San Marino', 'wb-listora' ),
			'ST' => __( 'São Tomé and Príncipe', 'wb-listora' ),
			'SA' => __( 'Saudi Arabia', 'wb-listora' ),
			'SN' => __( 'Senegal', 'wb-listora' ),
			'RS' => __( 'Serbia', 'wb-listora' ),
			'SC' => __( 'Seychelles', 'wb-listora' ),
			'SL' => __( 'Sierra Leone', 'wb-listora' ),
			'SG' => __( 'Singapore', 'wb-listora' ),
			'SX' => __( 'Sint Maarten', 'wb-listora' ),
			'SK' => __( 'Slovakia', 'wb-listora' ),
			'SI' => __( 'Slovenia', 'wb-listora' ),
			'SB' => __( 'Solomon Islands', 'wb-listora' ),
			'SO' => __( 'Somalia', 'wb-listora' ),
			'ZA' => __( 'South Africa', 'wb-listora' ),
			'GS' => __( 'South Georgia and the South Sandwich Islands', 'wb-listora' ),
			'SS' => __( 'South Sudan', 'wb-listora' ),
			'ES' => __( 'Spain', 'wb-listora' ),
			'LK' => __( 'Sri Lanka', 'wb-listora' ),
			'SD' => __( 'Sudan', 'wb-listora' ),
			'SR' => __( 'Suriname', 'wb-listora' ),
			'SJ' => __( 'Svalbard and Jan Mayen', 'wb-listora' ),
			'SE' => __( 'Sweden', 'wb-listora' ),
			'CH' => __( 'Switzerland', 'wb-listora' ),
			'SY' => __( 'Syria', 'wb-listora' ),
			'TW' => __( 'Taiwan', 'wb-listora' ),
			'TJ' => __( 'Tajikistan', 'wb-listora' ),
			'TZ' => __( 'Tanzania', 'wb-listora' ),
			'TH' => __( 'Thailand', 'wb-listora' ),
			'TL' => __( 'Timor-Leste', 'wb-listora' ),
			'TG' => __( 'Togo', 'wb-listora' ),
			'TK' => __( 'Tokelau', 'wb-listora' ),
			'TO' => __( 'Tonga', 'wb-listora' ),
			'TT' => __( 'Trinidad and Tobago', 'wb-listora' ),
			'TN' => __( 'Tunisia', 'wb-listora' ),
			'TR' => __( 'Türkiye', 'wb-listora' ),
			'TM' => __( 'Turkmenistan', 'wb-listora' ),
			'TC' => __( 'Turks and Caicos Islands', 'wb-listora' ),
			'TV' => __( 'Tuvalu', 'wb-listora' ),
			'UG' => __( 'Uganda', 'wb-listora' ),
			'UA' => __( 'Ukraine', 'wb-listora' ),
			'AE' => __( 'United Arab Emirates', 'wb-listora' ),
			'GB' => __( 'United Kingdom', 'wb-listora' ),
			'US' => __( 'United States', 'wb-listora' ),
			'UM' => __( 'United States Minor Outlying Islands', 'wb-listora' ),
			'UY' => __( 'Uruguay', 'wb-listora' ),
			'UZ' => __( 'Uzbekistan', 'wb-listora' ),
			'VU' => __( 'Vanuatu', 'wb-listora' ),
			'VE' => __( 'Venezuela', 'wb-listora' ),
			'VN' => __( 'Vietnam', 'wb-listora' ),
			'VG' => __( 'British Virgin Islands', 'wb-listora' ),
			'VI' => __( 'U.S. Virgin Islands', 'wb-listora' ),
			'WF' => __( 'Wallis and Futuna', 'wb-listora' ),
			'EH' => __( 'Western Sahara', 'wb-listora' ),
			'YE' => __( 'Yemen', 'wb-listora' ),
			'ZM' => __( 'Zambia', 'wb-listora' ),
			'ZW' => __( 'Zimbabwe', 'wb-listora' ),
		);
	}


	/**
	 * Other spellings people and data sources use => code.
	 *
	 * @var array<string, string>
	 */
	const ALIASES = array(
		'usa'                                                  => 'US',
		'u.s.'                                                 => 'US',
		'u.s.a.'                                               => 'US',
		'united states of america'                             => 'US',
		'america'                                              => 'US',
		'uk'                                                   => 'GB',
		'u.k.'                                                 => 'GB',
		'great britain'                                        => 'GB',
		'britain'                                              => 'GB',
		'united kingdom of great britain and northern ireland' => 'GB',
		'uae'                                                  => 'AE',
		'korea, republic of'                                   => 'KR',
		'republic of korea'                                    => 'KR',
		'korea'                                                => 'KR',
		'russian federation'                                   => 'RU',
		'czech republic'                                       => 'CZ',
		'turkey'                                               => 'TR',
		'viet nam'                                             => 'VN',
		'holland'                                              => 'NL',
		'the netherlands'                                      => 'NL',
		'ivory coast'                                          => 'CI',
		'swaziland'                                            => 'SZ',
		'macedonia'                                            => 'MK',
		'burma'                                                => 'MM',
		'deutschland'                                          => 'DE',
		'españa'                                               => 'ES',
		'italia'                                               => 'IT',
		'brasil'                                               => 'BR',
		'méxico'                                               => 'MX',
		'nederland'                                            => 'NL',
		'österreich'                                           => 'AT',
		'schweiz'                                              => 'CH',
		'suisse'                                               => 'CH',
		'belgique'                                             => 'BE',
		'belgië'                                               => 'BE',
		'polska'                                               => 'PL',
		'sverige'                                              => 'SE',
		'norge'                                                => 'NO',
		'danmark'                                              => 'DK',
		'suomi'                                                => 'FI',
		'éire'                                                 => 'IE',
		'nippon'                                               => 'JP',
		'bharat'                                               => 'IN',
	);

	/**
	 * US state and territory abbreviations => names. The demo and many data
	 * feeds write "NY" where geocoders write "New York".
	 *
	 * @var array<string, string>
	 */
	const US_STATES = array(
		'AL' => 'Alabama',
		'AK' => 'Alaska',
		'AZ' => 'Arizona',
		'AR' => 'Arkansas',
		'CA' => 'California',
		'CO' => 'Colorado',
		'CT' => 'Connecticut',
		'DE' => 'Delaware',
		'DC' => 'District of Columbia',
		'FL' => 'Florida',
		'GA' => 'Georgia',
		'HI' => 'Hawaii',
		'ID' => 'Idaho',
		'IL' => 'Illinois',
		'IN' => 'Indiana',
		'IA' => 'Iowa',
		'KS' => 'Kansas',
		'KY' => 'Kentucky',
		'LA' => 'Louisiana',
		'ME' => 'Maine',
		'MD' => 'Maryland',
		'MA' => 'Massachusetts',
		'MI' => 'Michigan',
		'MN' => 'Minnesota',
		'MS' => 'Mississippi',
		'MO' => 'Missouri',
		'MT' => 'Montana',
		'NE' => 'Nebraska',
		'NV' => 'Nevada',
		'NH' => 'New Hampshire',
		'NJ' => 'New Jersey',
		'NM' => 'New Mexico',
		'NY' => 'New York',
		'NC' => 'North Carolina',
		'ND' => 'North Dakota',
		'OH' => 'Ohio',
		'OK' => 'Oklahoma',
		'OR' => 'Oregon',
		'PA' => 'Pennsylvania',
		'RI' => 'Rhode Island',
		'SC' => 'South Carolina',
		'SD' => 'South Dakota',
		'TN' => 'Tennessee',
		'TX' => 'Texas',
		'UT' => 'Utah',
		'VT' => 'Vermont',
		'VA' => 'Virginia',
		'WA' => 'Washington',
		'WV' => 'West Virginia',
		'WI' => 'Wisconsin',
		'WY' => 'Wyoming',
		'PR' => 'Puerto Rico',
		'GU' => 'Guam',
		'VI' => 'U.S. Virgin Islands',
	);

	/**
	 * The ISO code for a country written as a code, its name or a common
	 * alias; '' when it is not recognised.
	 *
	 * @param string $value Country as written.
	 * @return string Upper-case ISO code, or ''.
	 */
	public static function code_for( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$upper = strtoupper( $value );
		if ( 2 === strlen( $upper ) && isset( self::names()[ $upper ] ) ) {
			return $upper;
		}
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
		if ( isset( self::ALIASES[ $lower ] ) ) {
			return self::ALIASES[ $lower ];
		}
		static $by_name = null;
		if ( null === $by_name ) {
			// English names too, whatever the site language: geocoders and
			// imports often write "Germany" on a German site. Built once with
			// translation switched off, so the list is not kept twice.
			$english = static function ( $translation, $text ) {
				return $text;
			};
			add_filter( 'gettext_wb-listora', $english, PHP_INT_MAX, 2 );
			$names_en = self::build();
			remove_filter( 'gettext_wb-listora', $english, PHP_INT_MAX );

			$by_name = array();
			foreach ( array( $names_en, self::names() ) as $list ) {
				foreach ( $list as $code => $name ) {
					$by_name[ function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name ) ] = $code;
				}
			}
		}
		return $by_name[ $lower ] ?? '';
	}

	/**
	 * A country's readable name in the site language.
	 *
	 * @param string $code ISO code.
	 * @return string Name, or the code when unknown.
	 */
	public static function name( $code ) {
		$code  = strtoupper( (string) $code );
		$names = self::names();
		return $names[ $code ] ?? $code;
	}

	/**
	 * A region's readable name: expands US state abbreviations.
	 *
	 * @param string $state        State as written.
	 * @param string $country_code The address's country code.
	 * @return string
	 */
	public static function state_name( $state, $country_code ) {
		$state = trim( (string) $state );
		if ( 'US' === $country_code && isset( self::US_STATES[ strtoupper( $state ) ] ) ) {
			return self::US_STATES[ strtoupper( $state ) ];
		}
		return $state;
	}
}
