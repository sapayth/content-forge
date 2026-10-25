<?php
// DESCRIPTION: Unit tests for the AI-suggested topic request.
// Covers that it asks for just a topic on the theme, without the blog-post prompt.

namespace ContentForge\Tests\Autopilot\Topic;

use ContentForge\Autopilot\Topic\Strategies\AI_Theme;
use PHPUnit\Framework\TestCase;

final class AI_ThemeTest extends TestCase {

	private function prompt( array $recent_titles ) {
		$build = new \ReflectionMethod( AI_Theme::class, 'build_prompt' );
		$build->setAccessible( true );
		return $build->invoke( new AI_Theme(), 'beginner home gardening tips', $recent_titles );
	}

	public function test_asks_for_one_topic_on_the_theme() {
		$prompt = $this->prompt( [] );

		$this->assertStringContainsString( 'beginner home gardening tips', $prompt );
		$this->assertStringContainsString( 'empty string as "content"', $prompt );
		$this->assertStringNotContainsString( 'Keywords to consider', $prompt );
	}

	public function test_lists_recent_titles_to_avoid() {
		$this->assertStringContainsString( '- Watering houseplants', $this->prompt( [ 'Watering houseplants' ] ) );
	}
}
