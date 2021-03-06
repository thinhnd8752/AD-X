<?php

/**
 * AD-X
 *
 * Licensed under the BSD (3-Clause) license
 * For full copyright and license information, please see the LICENSE file
 *
 * @copyright		2012-2013 Robert Rossmann
 * @author			Robert Rossmann <rr.rossmann@me.com>
 * @link			https://github.com/Alaneor/AD-X
 * @license			http://choosealicense.com/licenses/bsd-3-clause		BSD (3-Clause) License
 */


namespace ADX\Core;

/**
 * Encapsulates the data returned by Task to simplify data traversing
 *
 * This class behaves exactly like an array, except that you cannot modify it's contents
 * ( immutable array ). You can loop through it, access individual objects using
 * numeric indexes and traverse through the resulset using provided methods, applying a callback
 * on each object in the resultset.
 *
 * <h2>Example:</h2>
 * <code>
 * // ... Prepare the Link object and configure $task ( instance of Task )
 * $result = $task->get_result(); // Returns the Result object
 *
 * $first_obj = $result->first();	// Get the first object in the resultset
 *
 * // Apply a filter on the result using the Result::filter() method
 * $filtered = $result->filter( function( $object )	// The current object will be passed as a parameter of the callback
 * {
 * 	if ( $object->name->value( 0 ) == "Robert Rossmann" ) return $object;	// Returning an object in the callback will put it into the filtered resultset
 * });
 *
 * // Run the filter() method in a specific scope by specifying the $newthis parameter
 * $filtered = $result->filter( $myInstanceOfSomeClass, function( $object )
 * {
 * 	echo get_class( $this );	// SomeClass ( Imaginary example )
 * });
 *
 * // Loop through the resultset with the good old foreach
 * foreach ( $result as $object )
 * {
 * 	// Do something with $object..
 * 	echo $object->name->value( 0 ) . "\n";
 * }
 * </code>
 *
 * @see			Task
 * @see			<a href="http://www.php.net/manual/en/class.closure.php">PHP - Closure</a>
 */
class Result implements \ArrayAccess, \Iterator, \Countable
{
	protected $data;					// Container for the objects

	// Iterator properties
	protected $iteratorPosition	= 0;
	// Countable properties
	protected $count			= 0;

	/**
	 * Create an instance of the class using provided data as array
	 *
	 * @param		array|mixed		The data to be used in the object ( usually,
	 * 								an array that contains instances of {@link Object} or a single Object )
	 */
	public function __construct( $data )
	{
		$this->data		= (array)$data;
		$this->count	= count( $this->data );
	}

	/**
	 * Extract the data from the object as a regular array
	 *
	 * @return		array		The array with all items in it
	 */
	public function to_array()
	{
		return $this->data;
	}

	/**
	 * Return the first item in the resultset
	 *
	 * @return		mixed|null		The first item in the resultset, as returned from the directory
	 * 								server or null if the resultset is empty
	 */
	public function first()
	{
		return isset( $this->data[0] ) ? $this->data[0] : null;
	}

	/**
	 * Loop through all objects in the resultset, applying a callback on it
	 *
	 * @param		\Object		An optional object that the callback will be bound to
	 * 							( the object will be assigned to $this )
	 * @param		function	The callback function to be applied on each object
	 */
	public function each( $newthis = null, $callback = null )
	{
		$this->_apply_callback( $newthis, $callback );
	}

	/**
	 * Filter the resultset by applying a callback that returns a subset of objects
	 *
	 * Use this function to loop through the objects in the resultset, evaluate each object
	 * in turn and return the objects that you want to have in a new Result object.
	 *
	 * @param		\Object		An optional object that the callback will be bound to
	 * 							( the object will be assigned to $this )
	 * @param		function	The callback function to be applied on each object
	 *
	 * @return		self		A subset of items that your callbacks returned, encapsulated in the Result class
	 */
	public function filter( $newthis = null, $callback = null )
	{
		return new static( $this->_apply_callback( $newthis, $callback ) );
	}

	/**
	 * Get an array of all unique values of a specified attribute within the resultset
	 *
	 * @param		string		The ldap name of the attribute to be uniquified
	 *
	 * @return		array		The array of all unique items within the resultset
	 */
	public function unique( $attribute )
	{
		$items = [];

		foreach ( $this->data as $object )
		{
			// Extract the attribute's data and merge it with the rest

			$data = $object->$attribute;
			if ( $data instanceof Attribute )	$data	= $data->value();	// For Attributes, extract their values
			if ( is_string( $data ) )			$data	= [$data];			// For strings, put them into array

			// Put the data into the $items var
			$items = array_merge( $items, $data );
		}

		// Uniquify the data and fix indexes, then return the data
		return array_values( array_unique( $items ) );
	}

	protected function _apply_callback( $newthis = null, $callback = null )
	{
		// If only one parameter was passed in, the $newthis is in fact the callback
		if ( $callback === null && $newthis instanceof \Closure )
		{
			$callback = $newthis;
			unset( $newthis );
		}
		else $callback = $callback->bindTo( $newthis );

		$return_data = array();

		// Loop through all objects and apply the callback
		foreach ( $this->data as $object )
		{
			$return_object = $callback( $object );
			if ( $return_object ) $return_data[] = $return_object;
		}

		return $return_data;
	}

	// ArrayAccess interface implementation

	/**
	 * @internal
	 */
	final public function offsetSet( $offset, $value )
	{
		throw new InvalidOperationException( 'The Result object is immutable' );
	}

	/**
	 * @internal
	 */
	public function offsetExists( $offset )
	{
		return isset( $this->data[$offset] );
	}

	/**
	 * @internal
	 */
	final public function offsetUnset( $offset )
	{
		throw new InvalidOperationException( 'The Result object is immutable' );
	}

	/**
	 * @internal
	 */
	public function offsetGet( $offset )
	{
		return $this->data[$offset];
	}


	// Iterator interface implementation

	/**
	 * @internal
	 */
	public function rewind()
	{
		$this->iteratorPosition	= 0;
	}

	/**
	 * @internal
	 */
	public function current()
	{
		return $this->data[$this->iteratorPosition];
	}

	/**
	 * @internal
	 */
	public function key()
	{
		return $this->iteratorPosition;
	}

	/**
	 * @internal
	 */
	public function next()
	{
		$this->iteratorPosition++;
	}

	/**
	 * @internal
	 */
	public function valid()
	{
		return isset( $this->data[$this->iteratorPosition] );
	}


	// Countable interface implementation

	/**
	 * @internal
	 */
	public function count()
	{
		return $this->count;
	}
}
