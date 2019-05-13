<?php namespace Wikimedia\Phester;

/**
 * Class Instructions provides path based access to nested array structures
 * @package Wikimedia\Phester
 */
class Instructions {

	/**
	 * @var array
	 */
	private $array;

	/**
	 * Instructions constructor.
	 * @param array $array
	 */
	public function __construct( $array ) {
		$this->array = array_change_key_case( (array)$array, CASE_LOWER );
	}

	/**
	 * Checks if $key is set in array
	 * @param string|array $key
	 * @return bool
	 */
	public function has( $key ) {
		return $this->get( $key ) !== null;
	}

	/**
	 * Uses the $key to get the array value or the default
	 * @param string|array $key
	 * @param null $default
	 * @return Instructions|array|mixed|null
	 */
	public function get( $key, $default = null ) {
		$key = (array)$key;
		$current = $this->array;
		foreach ( $key as $arrKey => $value ) {
			if ( isset( $current[$value] ) ) {
				$current = $current[$value];
			} else {
				return $default;
			}
		}

		if ( is_array( $current ) ) {
			return new Instructions( $current );
		}

		return $current;
	}

	/**
	 * Checks if $key's value is an array
	 * @param string|array $key
	 * @return bool
	 */
	public function hasArray( $key ) {
		$value = $this->get( $key );

		if ( !is_null( $value ) ) {
			if ( $value  instanceof Instructions ) {
				return is_array( $value->getArray() );
			}
			return is_array( $value );
		}
		return false;
	}

	/**
	 * Converts array to string
	 * @return string containing the JSON representation of the array
	 */
	public function arrayToString() {
		return json_encode( $this->getArray() );
	}

	/**
	 * Gets the array
	 * @return array
	 */
	public function getArray() {
		return $this->array;
	}

	/**
	 * Gets the $key's value in lowercase
	 * @param string|array $key
	 * @return string|null
	 */
	public function getLowerCase( $key ) {
		if ( $this->has( $key ) && is_string( $this->get( $key ) ) ) {
			return strtolower( $this->get( $key ) );
		}

		return null;
	}
}
