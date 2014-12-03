<?php

namespace Fna;

class CallbackCollection {
	public function foo($a, $b, $c = 'with default value') {

	}

	public static function bar($a, $b, $c = 'with default value') {

	}

	public function __invoke($a, $b, $c = 'with default value') {

	}
}

function Foo($a, $b, $c) {

}

/**
 * Class WrapperTest
 *
 * @package Fna
 */
class WrapperTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @return array
	 */
	public function invalidCallbackProvider() {
		return array(
			array(true),
		        array(null),
		        array(array('fna')),
		        array('nonexistingfunction'),
		        array(array(new \stdClass, 'nonexistingmethod')),
		        array('\stdClass::nonexistingmethod'),
		);
	}

	/**
	 * @dataProvider invalidCallbackProvider
	 *
	 * @expectedException \Fna\Exception\InvalidCallbackException
	 * @expectedExceptionMessage Invalid callback
	 */
	public function testConstructorFailsForInvalidCallback($callback) {
		new Wrapper($callback);
	}

	public function testConstructorSucceedsForFunction() {
		$this->assertInstanceOf('\Fna\Wrapper', new Wrapper('\Fna\Foo'));
	}

	public function testConstructorSucceedsForMethod() {
		$object = new CallbackCollection();
		$this->assertInstanceOf('\Fna\Wrapper', new Wrapper(array($object, 'foo')));
	}

	public function testConstructorSucceedsForStaticMethod() {
		$this->assertInstanceOf('\Fna\Wrapper', new Wrapper('\Fna\CallbackCollection::bar'));
	}

	public function testConstructorSucceedsForClosure() {
		$this->assertInstanceOf('\Fna\Wrapper', new Wrapper(function() {}));
	}

	public function testConstructorSuccedsForInvokableObject() {
		$this->assertInstanceOf('\Fna\Wrapper', new Wrapper(new CallbackCollection()));
	}

	/**
	 * @dataProvider invalidCallbackProvider
	 *
	 * @expectedException \Fna\Exception\InvalidParameterException
	 * @expectedExceptionMessage Unable to handle mixed arrays
	 */
	public function testFailsForMixedArray() {
		$mock = $this->getMock('Fna\CallbackCollection');
		$mock->expects($this->never())->method('__invoke');

		$wrapper = new Wrapper($mock);
		$wrapper->__invoke(array('a' => 'a', 'b', 'c'));
	}

	public function testSucceedsForParamList() {
		$mock = $this->getMock('Fna\CallbackCollection');
		$mock->expects($this->once())
			->method('__invoke')
			->with('a', 'b', 'c');

		$wrapper = new Wrapper($mock);
		$wrapper->__invoke(array('a', 'b', 'c'));
	}

	public function testSucceedsForParamMap() {
		$mock = $this->getMock('Fna\CallbackCollection');
		$mock->expects($this->once())
			->method('__invoke')
			->with('a', 'b', 'c');

		$wrapper = new Wrapper($mock);
		$wrapper->__invoke(array('c' => 'c', 'a'=> 'a', 'b' => 'b'));
	}

	public function testSucceedsForDefaultValues() {
		$mock = $this->getMock('Fna\CallbackCollection');
		$mock->expects($this->once())
			->method('__invoke')
			->with('a', 'b', 'with default value');

		$wrapper = new Wrapper($mock);
		$wrapper->__invoke(array('b' => 'b', 'a'=> 'a'));
	}

	/**
	 * @expectedException \Fna\Exception\InvalidParameterException
	 * @expectedExceptionMessage Missing parameter 'a' on position 0
	 */
	public function testFailsForMissingArgument() {
		$mock = $this->getMock('Fna\CallbackCollection');
		$mock->expects($this->never())->method('__invoke');

		$wrapper = new Wrapper($mock);
		$wrapper->__invoke(array('b' => 'b'));
	}
}
