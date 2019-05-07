<?php namespace API;

/**
 * Class ArrayUtils provides path based access to nested array structures
 * @package API
 */
class ArrayUtils {

	/**
	 * @var array
	 */
	private $array;

	/**
	 * ArrayWrapper constructor.
	 * @param array $array
	 */
	public function __construct( $array ) {
		$this->array = (array)$array;
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
	 * Uses the $keys to get the array value or the default
	 * @param string|array $key
	 * @param null $default
	 * @return ArrayUtils|array|mixed|null
	 */
	public function get( $key, $default = null ) {
		if ( is_string( $key ) ) {
			if ( $this->has( $key ) ) {
				return $this->array[$key];
			} else {
				return $default;
			}
		} elseif ( is_array( $key ) ) {
			$current = $this->array;
			foreach ( $key as $arrKey => $value ) {
				if ( isset( $current[$value] ) ) {
					$current = $current[$value];
				} else {
					return $default;
				}
			}

			if ( is_array( $current ) ) {
				return new ArrayUtils( $current );
			}

			return $current;
		}
		return $default;
	}

	/**
	 * Checks if $key's value is an array
	 * @param string|array $key
	 * @return bool
	 */
	public function hasArray( $key ) {
		$value = $this->get( $key );

		if ( !is_null( $value ) ) {
			return is_array( $value );
		}
		return false;
	}

	/**
	 * Converts array to string
	 * @return array
	 */
	public function arrayToString() {
		return json_encode( $this->array );
	}

	/**
	 * Gets the $key's value in lowercase
	 * @param string $key
	 * @return string|null
	 */
	public function getLowerCase( $key ) {
		if ( $this->has( $key ) && is_string( $this->get( $key ) ) ) {
			return strtolower( $this->get( $key ) );
		}

		return null;
	}
}
