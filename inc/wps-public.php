<?php
/**
 * WP Seeds 🌱
 *
 * Custom functionality for transactions overview page.
 *
 * @package   wp-seeds/inc
 * @link      https://github.com/limikael/wp-seeds
 * @author    Mikael Lindqvist & Niels Lange
 * @copyright 2019 Mikael Lindqvist & Niels Lange
 * @license   GPL v2 or later
 */

register_activation_hook( __FILE__, 'flush_rewrite_rules' );

add_filter(
	'generate_rewrite_rules',
	function ( $wp_rewrite ) {
		$wp_rewrite->rules = array_merge(
			[ 'seeds-account/?$' => 'index.php?wpsaccount=1' ],
			[ 'seeds-account/send/?$' => 'index.php?wpssend=1' ],
			[ 'seeds-account/request/?$' => 'index.php?wpsrequest=1' ],
			$wp_rewrite->rules
		);
	}
);

add_filter(
	'query_vars',
	function( $query_vars ) {
		$query_vars[] = 'wpsaccount';
		$query_vars[] = 'wpssend';
		$query_vars[] = 'wpsrequest';
		return $query_vars;
	}
);

add_action(
	'template_redirect',
	function() {
		$seeds_account = intval( get_query_var( 'wpsaccount' ) );
		$send_seeds = intval( get_query_var( 'wpssend' ) );
		$request_seeds = intval( get_query_var( 'wpsrequest' ) );

		$wps_id = get_option( 'wpseeds_wpsaccount_page_id' );
		$wps_post = get_post( $wps_id );
		$wps_content = $wps_post->post_content;

		if ( $seeds_account || $send_seeds || $request_seeds ) {

			include( __DIR__ . '/wps-account/seeds-account.php' );

			exit();
		}
	}
);

/**
 * Show transaction history for the current user.
 *
 * @param array $args The shortcode args.
 * @return void.
 */
function wps_history_sc( $args ) {
	$user = wp_get_current_user();
	if ( ! $user || ! $user->ID ) {
		esc_html_e( 'You need to be logged in to view this page', 'wp-seeds' );
		return;
	}

	$user_display_by_id = wps_user_display_by_id();
	$vars = array();
	$vars['transactions'] = array();
	$transactions         = Transaction::findAllByQuery(
		'SELECT * ' .
		'FROM   :table ' .
		'WHERE  sender=%s ' .
		'OR     receiver=%s',
		$user->ID,
		$user->ID
	);
	foreach ( $transactions as $transaction ) {
		$view = array();
		$view['id'] = $transaction->transaction_id;

		if ( $user->ID == $transaction->sender ) {
			// If we are the sender, show amount as negative, and the
			// receiver in the to/from field...
			$view['amount'] = -$transaction->amount;
			$view['user'] = 'To: ' . $user_display_by_id[ $transaction->receiver ];
		} else {
			// ...otherwise, we are the receiver, so show the amount as
			// positive and the sender in the to/from field.
			$view['amount'] = $transaction->amount;
			$view['user'] = 'From: ' . $user_display_by_id[ $transaction->sender ];
		}

		$vars['transactions'][] = $view;
	}

	display_template( __DIR__ . '/../tpl/wps-history.tpl.php', $vars );
}
add_shortcode( 'seeds-history', 'wps_history_sc' );


/**
 * Register request seeds query vars.
 *
 * @param array $vars The query vars.
 * @return $vars.
 */
function wps_add_query_vars_filter( $vars ) {
	$vars[] = 'to_user';
	$vars[] = 'amount';
	return $vars;
}
add_filter( 'query_vars', 'wps_add_query_vars_filter' );
