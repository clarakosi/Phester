<?php

use PHPUnit\Framework\TestCase;
use Wikimedia\Phester\Instructions;

/**
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

	/**
	 * Provides input to test Instructions::get
	 * @return Generator
	 */
	public function provideGet() {
		/**
		 * format: 'string test description' => ['array input', 'string|array expected value']
		 */
		yield 'string input' => [ [ 'request' ], new Instructions( [
			'method' => 'GET',
			'path' => '/w/api.php',
			'parameters' => [
				'action' => 'query',
				'prop' => 'info',
				'titles' => 'Main Page',
				'format' => 'json'
			]
		] ) ];
		yield 'array input' => [ [ [ 'request', 'method' ] ],'GET' ];
		yield 'with default' => [ [ 'response', 'none' ],'none' ];
		yield 'without default' => [ [ [ 'request', 'form-data' ] ], null ];
	}

	/**
	 * Test Instructions::get
	 * @dataProvider provideGet
	 * @param string|array $expected
	 * @param array $input
	 */
	public function testGet( $input, $expected ) {
		$this->assertEquals( $this->instructions->get( ...$input ), $expected );
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

	public function testAsArray() {
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

		$this->assertEquals( $this->instructions->asArray(), $array );
	}

	public function testGetLowerCaseWithSetKey() {
		$this->assertEquals( $this->instructions->getLowerCase( [ 'request', 'method' ] ), 'get' );
	}

	public function testGetLowerCaseWithUnsetKey() {
		$this->assertEquals( $this->instructions->getLowerCase( [ 'response', 'status' ] ), null );
	}

	public function testGetLowerCaseWithDefaultValue() {
		$this->assertEquals( $this->instructions->getLowerCase( [ 'response', 'status' ], '123' ),
			'123' );
	}

}
