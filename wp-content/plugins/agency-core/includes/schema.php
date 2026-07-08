<?php
/**
 * JSON-LD schema and SEO-plugin compatibility.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_detect_seo_schema_provider() {
	if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
		return 'Yoast SEO';
	}
	if ( defined( 'RANK_MATH_VERSION' ) ) {
		return 'Rank Math';
	}
	if ( defined( 'AIOSEO_VERSION' ) || defined( 'AIOSEO_PHP_VERSION_DIR' ) || function_exists( 'aioseo' ) ) {
		return 'All in One SEO';
	}
	if ( defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_init' ) ) {
		return 'SEOPress';
	}
	return '';
}

function agency_core_schema_is_enabled() {
	$mode = agency_core_get_setting( 'schema_mode', 'auto' );
	if ( 'disabled' === $mode ) {
		return false;
	}
	if ( 'force' === $mode ) {
		return true;
	}
	return ! agency_core_detect_seo_schema_provider();
}

function agency_core_organization_schema() {
	$site_url = home_url( '/' );
	$type     = agency_core_get_setting( 'localbusiness_type', 'LocalBusiness' );
	$type     = preg_match( '/^[A-Za-z]+$/', $type ) ? $type : 'LocalBusiness';
	$schema   = array(
		'@type' => $type,
		'@id'   => $site_url . '#organization',
		'name'  => agency_core_get_setting( 'brand_name', get_bloginfo( 'name' ) ),
		'url'   => $site_url,
	);

	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$logo = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( $logo ) {
			$schema['logo'] = $logo;
		}
	}
	$optional = array(
		'telephone'   => agency_core_get_setting( 'phone' ),
		'email'       => agency_core_get_setting( 'email' ),
		'priceRange'  => agency_core_get_setting( 'price_range' ),
		'openingHours' => agency_core_get_setting( 'opening_hours' ),
	);
	foreach ( $optional as $key => $value ) {
		if ( $value ) {
			$schema[ $key ] = $value;
		}
	}

	$street  = agency_core_get_setting( 'street_address' );
	$postal  = agency_core_get_setting( 'postal_code' );
	$city    = agency_core_get_setting( 'locality' );
	$region  = agency_core_get_setting( 'region' );
	$country = agency_core_get_setting( 'country_code' );
	if ( $street || $postal || $city || $region || $country ) {
		$schema['address'] = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $street,
				'postalCode'      => $postal,
				'addressLocality' => $city,
				'addressRegion'   => $region,
				'addressCountry'  => $country,
			)
		);
	} elseif ( agency_core_get_setting( 'address' ) ) {
		$schema['address'] = agency_core_get_setting( 'address' );
	}

	$latitude  = agency_core_get_setting( 'latitude' );
	$longitude = agency_core_get_setting( 'longitude' );
	if ( is_numeric( $latitude ) && is_numeric( $longitude ) ) {
		$schema['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => (float) $latitude, 'longitude' => (float) $longitude );
	}

	$socials = array_filter(
		array(
			agency_core_get_setting( 'facebook_url' ),
			agency_core_get_setting( 'instagram_url' ),
			agency_core_get_setting( 'linkedin_url' ),
		)
	);
	if ( $socials ) {
		$schema['sameAs'] = array_values( $socials );
	}
	return $schema;
}

function agency_core_page_contains_component( $component, $variant = '' ) {
	if ( ! is_page() ) {
		return false;
	}
	$sections = json_decode( get_post_meta( get_queried_object_id(), '_agency_sections', true ), true );
	if ( ! is_array( $sections ) ) {
		return false;
	}
	foreach ( $sections as $section ) {
		$normalized = agency_core_normalize_section( $section );
		if ( $normalized && $normalized['component'] === $component && ( ! $variant || $normalized['variant'] === $variant ) ) {
			return true;
		}
	}
	return false;
}

function agency_core_schema_graph() {
	$site_url = home_url( '/' );
	$graph    = array();

	if ( is_front_page() || is_singular( array( 'post', 'agency_service' ) ) ) {
		$graph[] = agency_core_organization_schema();
	}

	if ( is_singular( 'post' ) ) {
		$article = array(
			'@type'            => 'Article',
			'@id'              => get_permalink() . '#article',
			'headline'         => get_the_title(),
			'description'      => wp_strip_all_tags( get_the_excerpt() ),
			'datePublished'    => get_the_date( DATE_W3C ),
			'dateModified'     => get_the_modified_date( DATE_W3C ),
			'mainEntityOfPage' => get_permalink(),
			'author'           => array( '@type' => 'Person', 'name' => get_the_author() ),
			'publisher'        => array( '@id' => $site_url . '#organization' ),
		);
		$image = get_the_post_thumbnail_url( get_queried_object_id(), 'full' );
		if ( $image ) {
			$article['image'] = $image;
		}
		$graph[] = $article;
	}

	if ( is_singular( 'agency_service' ) ) {
		$service = array(
			'@type'       => 'Service',
			'@id'         => get_permalink() . '#service',
			'name'        => get_the_title(),
			'description' => wp_strip_all_tags( has_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 35 ) ),
			'url'         => get_permalink(),
			'provider'    => array( '@id' => $site_url . '#organization' ),
		);
		$price = get_post_meta( get_queried_object_id(), '_agency_price', true );
		if ( is_numeric( $price ) ) {
			$service['offers'] = array(
				'@type'         => 'Offer',
				'price'         => (float) $price,
				'priceCurrency' => get_post_meta( get_queried_object_id(), '_agency_price_currency', true ) ?: agency_core_get_setting( 'default_currency', 'HUF' ),
				'availability'  => 'https://schema.org/InStock',
				'url'           => get_permalink(),
			);
		}
		$graph[] = $service;
	}

	$faq_items = array();
	if ( is_post_type_archive( 'agency_faq' ) || agency_core_page_contains_component( 'faq', 'accordion' ) ) {
		$faqs = get_posts( array( 'post_type' => 'agency_faq', 'numberposts' => 20, 'post_status' => 'publish' ) );
		foreach ( $faqs as $faq ) {
			$faq_items[] = array(
				'@type'          => 'Question',
				'name'           => get_the_title( $faq ),
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $faq->post_content ) ),
			);
		}
	}
	if ( $faq_items ) {
		$graph[] = array( '@type' => 'FAQPage', 'mainEntity' => $faq_items );
	}
	return apply_filters( 'agency_core_schema_graph', $graph );
}

function agency_core_output_schema() {
	if ( is_admin() || ! apply_filters( 'agency_core_schema_enabled', agency_core_schema_is_enabled() ) ) {
		return;
	}
	$graph = agency_core_schema_graph();
	if ( ! $graph ) {
		return;
	}
	echo '<script type="application/ld+json">' . wp_json_encode(
		array( '@context' => 'https://schema.org', '@graph' => $graph ),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
	) . '</script>' . "\n";
}
add_action( 'wp_head', 'agency_core_output_schema', 30 );

function agency_core_filter_document_title( $parts ) {
	if ( is_admin() || agency_core_detect_seo_schema_provider() ) {
		return $parts;
	}
	$suffix = agency_core_get_setting( 'seo_title_suffix' );
	if ( $suffix && isset( $parts['title'] ) && false === str_contains( $parts['title'], $suffix ) ) {
		$parts['title'] .= ' ' . $suffix;
	}
	return $parts;
}
add_filter( 'document_title_parts', 'agency_core_filter_document_title' );
