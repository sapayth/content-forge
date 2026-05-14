<?php
// DESCRIPTION: Autopilot bootstrap — registers CPT, schedules AS hooks, wires handlers.
// Single entry point called from Loader::load(). Idempotent; safe to invoke per request.

namespace ContentForge\Autopilot;

use ContentForge\Autopilot\Publishing\Post_Builder;
use ContentForge\Autopilot\Publishing\Status_Resolver;
use ContentForge\Autopilot\Topic\Topic_Resolver;
use ContentForge\Autopilot\Topic\Duplicate_Guard;
use ContentForge\Autopilot\Notifications\Admin_Notices;
use ContentForge\Autopilot\Notifications\Email_Notifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps the Autopilot subsystem.
 *
 * @since 1.5.0
 */
class Plugin {

	const FEATURE_FLAG_OPTION = 'cforge_autopilot_enabled';

	const DISPATCH_INTERVAL    = 60;
	const MAINTENANCE_INTERVAL = DAY_IN_SECONDS;

	/**
	 * Boot the subsystem.
	 *
	 * Always registers the CPT (so existing schedule rows remain queryable even
	 * when the feature flag is off). Only registers AS hooks + handlers when the
	 * flag is enabled.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public static function boot() {
		self::register_post_type();

		// Run migrations on every load behind a version gate.
		Installer::maybe_install();

		if ( ! self::is_enabled() ) {
			return;
		}

		self::wire_handlers();
		self::ensure_recurring_actions();
	}

	/**
	 * Is the feature flag enabled for this site?
	 *
	 * @since 1.5.0
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( self::FEATURE_FLAG_OPTION, false );
	}

	/**
	 * Register the schedules CPT.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public static function register_post_type() {
		register_post_type(
			Schedule::POST_TYPE,
			[
				'labels'              => [
					'name'          => __( 'Autopilots', 'content-forge' ),
					'singular_name' => __( 'Autopilot', 'content-forge' ),
				],
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => [ 'title', 'author' ],
			]
		);
	}

	/**
	 * Build handler instances and register their AS hooks.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	protected static function wire_handlers() {
		$schedules       = new Schedule_Repository();
		$runs            = new Run_Repository();
		$topic_resolver  = new Topic_Resolver( $schedules );
		$post_builder    = new Post_Builder( new Status_Resolver() );
		$duplicate_guard = new Duplicate_Guard();
		$admin_notices   = new Admin_Notices();
		$email_notifier  = new Email_Notifier();

		$admin_notices->register();

		( new Dispatcher( $schedules, $runs ) )->register();
		( new Schedule_Runner( $schedules, $runs, $topic_resolver, $post_builder, $duplicate_guard, $admin_notices, $email_notifier ) )->register();
		( new Maintenance( $runs ) )->register();
		( new Dashboard_Widget() )->register();
	}

	/**
	 * Ensure the recurring AS actions are scheduled exactly once.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	protected static function ensure_recurring_actions() {
		if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( ! as_next_scheduled_action( Dispatcher::HOOK, [], 'cforge_autopilot' ) ) {
			as_schedule_recurring_action(
				time(),
				self::DISPATCH_INTERVAL,
				Dispatcher::HOOK,
				[],
				'cforge_autopilot'
			);
		}

		if ( ! as_next_scheduled_action( Maintenance::HOOK, [], 'cforge_autopilot' ) ) {
			as_schedule_recurring_action(
				strtotime( 'tomorrow 03:00' ),
				self::MAINTENANCE_INTERVAL,
				Maintenance::HOOK,
				[],
				'cforge_autopilot'
			);
		}
	}

	/**
	 * Unschedule all Autopilot recurring AS actions. Called on plugin deactivation.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public static function unschedule_recurring_actions() {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( Dispatcher::HOOK, [], 'cforge_autopilot' );
		as_unschedule_all_actions( Maintenance::HOOK, [], 'cforge_autopilot' );
	}
}
