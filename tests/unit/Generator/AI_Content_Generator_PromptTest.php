<?php
// DESCRIPTION: Unit tests for the AI generation prompt.
// Covers Autopilot's tone and length reaching the prompt, and the default prompt without them.

namespace ContentForge\Tests\Generator;

use ContentForge\Generator\AI_Content_Generator;
use PHPUnit\Framework\TestCase;

final class AI_Content_Generator_PromptTest extends TestCase {

	private function prompt( AI_Content_Generator $generator ) {
		$build = new \ReflectionMethod( $generator, 'build_prompt' );
		$build->setAccessible( true );
		return $build->invoke( $generator, 'general', 'Growing tomatoes' );
	}

	public function test_tone_and_length_reach_the_prompt() {
		$generator = new AI_Content_Generator( 'google', 'gemini-test', 'key' );
		$generator->set_style( 'casual', 'short' );

		$prompt = $this->prompt( $generator );

		$this->assertStringContainsString( 'about 300 words', $prompt );
		$this->assertStringContainsString( 'Tone: casual', $prompt );
		$this->assertStringNotContainsString( 'minimum 500 words', $prompt );
	}

	public function test_prompt_is_unchanged_without_a_style() {
		$prompt = $this->prompt( new AI_Content_Generator( 'google', 'gemini-test', 'key' ) );

		$this->assertStringContainsString( 'minimum 500 words', $prompt );
		$this->assertStringNotContainsString( 'Tone:', $prompt );
	}

	public function test_unknown_length_keeps_the_default() {
		$generator = new AI_Content_Generator( 'google', 'gemini-test', 'key' );
		$generator->set_style( '', 'huge' );

		$this->assertStringContainsString( 'minimum 500 words', $this->prompt( $generator ) );
	}

	public function test_topic_is_the_subject_without_generic_keywords() {
		$generator = new AI_Content_Generator( 'google', 'gemini-test', 'key' );
		$generator->set_topic( 'Starting a herb garden' );

		$prompt = $this->prompt( $generator );

		$this->assertStringContainsString( 'Topic: Starting a herb garden', $prompt );
		$this->assertStringNotContainsString( 'Keywords to consider', $prompt );
	}

	public function test_keywords_stay_without_a_topic() {
		$this->assertStringContainsString( 'Keywords to consider', $this->prompt( new AI_Content_Generator( 'google', 'gemini-test', 'key' ) ) );
	}
}
