<?php
// DESCRIPTION: Unit tests for WooCommerce_Product::sale_price — a sale range that overlaps the regular price
// DESCRIPTION: still gives a sale below it, a range wholly above it gives none, and prices round to cents.

namespace ContentForge\Tests\Integration;

use ContentForge\Integration\WooCommerce_Product;
use PHPUnit\Framework\TestCase;

class WooCommerce_ProductTest extends TestCase {

	public function test_overlapping_range_is_capped_below_the_regular_price() {
		$this->assertSame( 24.99, WooCommerce_Product::sale_price( 25.0, 10.0, 30.0, 100 ) );
		$this->assertSame( 10.0, WooCommerce_Product::sale_price( 25.0, 10.0, 30.0, 0 ) );
	}

	public function test_range_below_the_regular_price_is_used_as_is() {
		$this->assertSame( 20.0, WooCommerce_Product::sale_price( 80.0, 10.0, 30.0, 50 ) );
	}

	public function test_range_at_or_above_the_regular_price_gives_no_sale() {
		$this->assertNull( WooCommerce_Product::sale_price( 25.0, 25.0, 40.0, 50 ) );
		$this->assertNull( WooCommerce_Product::sale_price( 25.0, 30.0, 40.0, 50 ) );
	}

	public function test_no_sale_range_gives_no_sale() {
		$this->assertNull( WooCommerce_Product::sale_price( 25.0, null, null, 50 ) );
		$this->assertNull( WooCommerce_Product::sale_price( 25.0, 30.0, 10.0, 50 ) );
	}

	public function test_sale_price_rounds_to_cents() {
		$this->assertSame( 13.33, WooCommerce_Product::sale_price( 99.0, 10.0, 20.0, 33.3 ) );
	}
}
