<?php
// DESCRIPTION: Unit tests for AI settings model lookups.
// Covers mapping a model saved without its dots (sanitize_key) back to the real model.

namespace ContentForge\Tests\Settings;

use ContentForge\Settings\AI_Settings_Manager;
use PHPUnit\Framework\TestCase;

final class AI_Settings_ManagerTest extends TestCase {

	private const MODELS = [
		'gemini-3.5-flash'      => 'Gemini 3.5 Flash',
		'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite',
		'gpt-3.5-turbo'         => 'GPT-3.5 Turbo',
	];

	public function test_maps_an_undotted_model_back() {
		$this->assertSame( 'gemini-3.5-flash-lite', AI_Settings_Manager::match_undotted_model( 'gemini-35-flash-lite', self::MODELS ) );
		$this->assertSame( 'gemini-3.5-flash', AI_Settings_Manager::match_undotted_model( 'gemini-35-flash', self::MODELS ) );
		$this->assertSame( 'gpt-3.5-turbo', AI_Settings_Manager::match_undotted_model( 'gpt-35-turbo', self::MODELS ) );
	}

	public function test_unknown_model_matches_nothing() {
		$this->assertSame( '', AI_Settings_Manager::match_undotted_model( 'gemini-99-flash', self::MODELS ) );
	}
}
