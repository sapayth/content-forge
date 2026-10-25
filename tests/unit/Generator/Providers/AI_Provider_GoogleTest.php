<?php
// DESCRIPTION: Unit tests for the Google AI provider's request payload.
// Covers JSON mode, which keeps block-editor replies parseable.

namespace ContentForge\Tests\Generator\Providers;

use ContentForge\Generator\Providers\AI_Provider_Google;
use PHPUnit\Framework\TestCase;

final class AI_Provider_GoogleTest extends TestCase {

	public function test_requests_json_mode() {
		$payload = ( new AI_Provider_Google( 'key', 'gemini-test' ) )->build_request_payload( [ 'prompt' => 'Growing tomatoes' ] );

		$this->assertSame( 'application/json', $payload['generationConfig']['responseMimeType'] );
		$this->assertStringContainsString( 'Growing tomatoes', $payload['contents'][0]['parts'][0]['text'] );
	}
}
