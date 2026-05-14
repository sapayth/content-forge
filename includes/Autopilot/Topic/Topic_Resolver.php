<?php
// DESCRIPTION: Resolves the next topic for a schedule based on its topic.mode.
// Dispatches to one of: list_cycle, list_random, queue, ai_theme.

namespace ContentForge\Autopilot\Topic;

use ContentForge\Autopilot\Schedule;
use ContentForge\Autopilot\Schedule_Repository;
use ContentForge\Autopilot\Topic\Strategies\List_Cycle;
use ContentForge\Autopilot\Topic\Strategies\List_Random;
use ContentForge\Autopilot\Topic\Strategies\Queue;
use ContentForge\Autopilot\Topic\Strategies\AI_Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strategy dispatcher for topic resolution.
 *
 * Some strategies mutate the schedule's config (cursors, queue depth); the
 * repository is injected so they can persist updates atomically with resolution.
 *
 * @since 1.5.0
 */
class Topic_Resolver {

	/**
	 * Schedule repository (for persisting cursor/queue mutations).
	 *
	 * @var Schedule_Repository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule_Repository $repository Repository.
	 */
	public function __construct( Schedule_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Resolve the next topic for a schedule.
	 *
	 * Returns empty string if no topic is available (caller should skip).
	 *
	 * @since 1.5.0
	 *
	 * @param Schedule $schedule Schedule object.
	 * @return string             Topic string or '' if unavailable.
	 */
	public function resolve( Schedule $schedule ) {
		$mode = (string) $schedule->config_get( 'topic.mode', 'list_cycle' );

		switch ( $mode ) {
			case 'list_random':
				$topic = ( new List_Random() )->resolve( $schedule );
				break;

			case 'queue':
				$topic = ( new Queue( $this->repository ) )->resolve( $schedule );
				break;

			case 'ai_theme':
				$topic = ( new AI_Theme() )->resolve( $schedule );
				break;

			case 'list_cycle':
			default:
				$topic = ( new List_Cycle( $this->repository ) )->resolve( $schedule );
				break;
		}

		/**
		 * Filter the resolved topic before it is handed to the AI generator.
		 *
		 * @since 1.5.0
		 *
		 * @param string   $topic    Resolved topic.
		 * @param Schedule $schedule Schedule object.
		 */
		return (string) apply_filters( 'cforge_autopilot_resolved_topic', $topic, $schedule );
	}
}
