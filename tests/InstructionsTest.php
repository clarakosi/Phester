<?php

use PHPUnit\Framework\TestCase;
use Wikimedia\Phester\Instructions;

/**
 * Class InstructionsTest
 * @covers \Wikimedia\Phester\Instructions
 */
class InstructionsTest extends TestCase {
	/**
	 * @var Instructions|null
	 */
	private $instructions;

	protected function setUp(): void {
		$this->instructions = new Instructions( [
			'request' => [
				'method' => 'GET',
				'path' => '/w/api.php',
				'parameters' => [
					'action' => 'query',
					'prop' => 'info',
					'titles' => 'Main Page',
					'format' => 'json'
				]
			]
		] );
	}

	protected function tearDown(): void {
		$this->instructions = null;
	}

	public function testConstructorLowerCasesKeys() {
		$this->assertEquals( $this->instructions,  new Instructions( [
			'REQUEST' => [
				'method' => 'GET',
				'path' => '/w/api.php',
				'parameters' => [
					'action' => 'query',
					'prop' => 'info',
					'titles' => 'Main Page',
					'format' => 'json'
				]
			]
		] ) );
	}

	public function testHasWithSetKey() {
		$this->assertTrue( $this->instructions->has( [ 'request', 'parameters' ] ) );
	}

	public function testHasWithUnsetKey() {
		$this->assertFalse( $this->instructions->has( 'response' ) );
	}

	public function testGetWithStringInput() {
		$this->assertEquals( $this->instructions->get( 'request' ),
			new Instructions( [
			'method' => 'GET',
			'path' => '/w/api.php',
			'parameters' => [
				'action' => 'query',
				'prop' => 'info',
				'titles' => 'Main Page',
				'format' => 'json'
			] ] ) );
	}

	public function testGetWithArrayInput() {
		$this->assertEquals( $this->instructions->get( [ 'request', 'method' ] ), 'GET' );
	}

	public function testGetWithDefault() {
		$this->assertEquals( $this->instructions->get( 'response', 'none' ), 'none' );
	}

	public function testGetWithoutDefault() {
		$this->assertEquals( $this->instructions->get( [ 'request', 'form-data' ] ), null );
	}

	public function testHasArrayWithSetKey() {
		$this->assertTrue( $this->instructions->hasArray( 'request' ) );
	}

	public function testHasArrayWithSetKeyAndStringValue() {
		$this->assertFalse( $this->instructions->hasArray( [ 'request', 'path' ] ) );
	}

	public function testHasArrayWithUnsetKey() {
		$this->assertFalse( $this->instructions->hasArray( [ 'response' ] ) );
	}

	public function testArrayToString() {
		$string = '{
            "request":{
                "method":"GET",
                "path":"\/w\/api.php",
                "parameters":{
                    "action":"query",
                    "prop":"info",
                    "titles":"Main Page",
                    "format":"json"
                }
            }
        }';

		$this->assertJsonStringEqualsJsonString( $this->instructions->arrayToString(), $string );
	}

	public function testGetArray() {
		$array = [
			'request' => [
				'method' => 'GET',
				'path' => '/w/api.php',
				'parameters' => [
					'action' => 'query',
					'prop' => 'info',
					'titles' => 'Main Page',
					'format' => 'json'
				]
			]
		];

		$this->assertEquals( $this->instructions->getArray(), $array );
	}

	public function testGetLowerCaseWithSetKey() {
		$this->assertEquals( $this->instructions->getLowerCase( [ 'request', 'method' ] ), 'get' );
	}

	public function testGetLowerCaseWithUnsetKey() {
		$this->assertEquals( $this->instructions->getLowerCase( [ 'response', 'status' ] ), null );
	}

}
