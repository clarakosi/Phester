<?php


namespace API;

/**
 * Class ArrayWrapper
 * @package API
 */
class ArrayWrapper {

	/**
	 * @var array
	 */
	private $array;

	/**
	 * ArrayWrapper constructor.
	 * @param array $array
	 */
	public function __construct( $array ) {
		$this->array = $array;
	}

	/**
	 * Checks if $key is set in array
	 * @param string $key
	 * @return bool
	 */
	public function has( $key ) {
		return isset( $this->array[$key] );
	}

	/**
	 * Uses the $keys to get the array value or null
	 * @param mixed ...$keys
	 * @return array|mixed|null
	 */
	public function get( ...$keys ) {
		$current = $this->array;
		foreach ( $keys as $key => $value ) {
			if ( isset( $current[$value] ) ) {
				$current = $current[$value];
			} else {
				return null;
			}
		}

		return $current;
	}

	/**
	 * Checks if $key's value is an array
	 * @param string $key
	 * @return bool
	 */
	public function isArray( $key ) {
		return is_array( $this->array[$key] );
	}
}
