<?php

namespace Lx\Fna;

require_once __DIR__.'/../src/Wrapper.php';

class Dummy {
	public function foo($a, $b, $c = "default value") {

	}

	public function __invoke($a, $b, $c) {

	}
}

function foo($a, $b, $c) {

}

/**
 * Class WrapperProxy
 *
 * Proxy class for accessing protected properties and methods in Lx\Wrapper
 *
 * @package Lx\Fna
 */
class WrapperProxy extends Wrapper {
	public function _call($method, $arguments = array()) {
		return call_user_func_array(array($this, $method), $arguments);
	}

	public function _set($name, $value) {
		$this->$name = $value;
	}

	public function _get($name) {
		return $this->$name;
	}
}

/**
 * Class WrapperTest
 *
 * @package Lx\Fna
 */
class WrapperTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @testdox Wrapper::__construct() calls Wrapper::setCallback properly
	 *
	 * @test
	 * @covers \Lx\Fna\Wrapper::__construct
	 */
	public function constructorSetsCallback() {
		$sut = $this->getMock(__NAMESPACE__.'\WrapperProxy', array('setCallback'), array(), '', false);
		$sut->expects($this->once())->method('setCallback')->with('foo');
		$sut->__construct('foo');
	}

	/**
	 * @testdox Wrapper::setCallback() throws \InvalidArgumentException when invalid callback provided
	 *
	 * @test
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Invalid callback
	 */
	public function setCallbackThrowsExceptionOnInvalidCallback() {
		$sut = $this->getMock(__NAMESPACE__.'\WrapperProxy', array('__construct'), array(), '', false);
		$sut->_call('setCallback', array('nonexisting'));
	}

	/**
	 * @testdox Wrapper::setCallback() properly sets property callback and reflection_parameter
	 *
	 * @test
	 */
	public function setCallbackSetsCallback() {
		$reflection = $this->getMock('\ReflectionFunction', array('getParameters'), array(), '', false);
		$reflection->expects($this->once())->method('getParameters')->will($this->returnValue('foo'));

		$sut = $this->getMock(__NAMESPACE__.'\WrapperProxy', array('getCallbackReflection'), array(), '', false);
		$sut->expects($this->once())->method('getCallbackReflection')->will($this->returnValue($reflection));

		$sut->_call('setCallback', array(function(){}));

		$this->assertEquals('foo', $sut->_get('reflection_parameter'));
		$this->assertInstanceOf('\Closure', $sut->_get('callback'));
	}

	/**
	 * Data provider for self::getCallbackReflection()
	 *
	 * @return array
	 */
	public function getCallbackValidProvider() {
		return array(
			array(function() {}, '\ReflectionMethod'),
			array(__NAMESPACE__.'\Dummy::foo', '\ReflectionMethod'),
			array(array(__NAMESPACE__.'\Dummy', 'foo'), '\ReflectionMethod'),
			array(array(new Dummy(), 'foo'), '\ReflectionMethod'),
			array(new Dummy(), '\ReflectionMethod'),
			array(__NAMESPACE__.'\foo', '\ReflectionFunction'),
		);
	}

	/**
	 * @testdox Wrapper::getCallbackReflection() returns correct reflection class for provided callback
	 *
	 * @param callable $callback
	 * @param string $reflection
	 *
	 * @test
	 * @dataProvider getCallbackValidProvider
	 */
	public function getCallbackReflection($callback, $reflection) {
		$sut = $this->getMock(__NAMESPACE__.'\WrapperProxy', array('__construct'), array(), '', false);
		$this->assertInstanceOf($reflection, $sut->_call('getCallbackReflection', array($callback)));
	}

	/**
	 * @testdox Wrapper::getCallbackReflection() throws \InvalidArgumentException on invalid callback
	 *
	 * @test
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Invalid callback
	 */
	public function getCallbackReflectionThrowsExceptionOnInvalidCallback() {
		$sut = $this->getMock(__NAMESPACE__.'\WrapperProxy', array('__construct'), array(), '', false);
		$sut->_call('getCallbackReflection', array(0));
	}


	/**
	 * Data provider for self::getArrayType()
	 *
	 * @return array
	 */
	public function arrayTypeProvider() {
		return array(
			array(array(1, 2, 3), Wrapper::ARRAY_TYPE_LIST),
			array(array("one" => 1, "two" => "2", "three" => "3"), Wrapper::ARRAY_TYPE_DICT),
			array(array("one" => "1", "2", "three" => "3"), Wrapper::ARRAY_TYPE_MIXED),
			array(array(), Wrapper::ARRAY_TYPE_LIST),
		);
	}

	/**
	 * @testdox Wrapper::getArrayType() returns the correct type for the provided array
	 *
	 * @param array $array
	 * @param int $type
	 *
	 * @test
	 * @dataProvider arrayTypeProvider
	 */
	public function getArrayType($array, $type) {
		$sut = $this->getMock(__NAMESPACE__.'\WrapperProxy', array('__construct'), array(), '', false);
		$this->assertEquals($type, $sut->getArrayType($array));
	}

	/**
	 * @testdox Wrapper::prepareArguments() throws \InvalidArgumentException on mixed arrays
	 *
	 * @test
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Unable to handle mixed arrays
	 */
	public function prepareArgumentsThrowsExceptionOnMixedArray() {
		$sut = $this->getMock(__NAMESPACE__.'\WrapperProxy', array('__construct', 'getArrayType'), array(), '', false);
		$sut->expects($this->once())->method('getArrayType')->will($this->returnValue(Wrapper::ARRAY_TYPE_MIXED));

		$sut->_call('prepareArguments', array(new \ReflectionMethod($this, __FUNCTION__), array()));
	}

	/**
	 * @testdox Wrapper::prepeareArguments() returns unmodified argument list on index based array
	 *
	 * @test
	 */
	public function prepareArgumentsReturnsUnmodifiedOnList() {
		$array = array("foo", "bar" => "baz", 42);
		$sut = $this->getMock(__NAMESPACE__.'\WrapperProxy', array('__construct', 'getArrayType'), array(), '', false);
		$sut->expects($this->once())->method('getArrayType')->with($array)->will($this->returnValue(Wrapper::ARRAY_TYPE_LIST));

		$result = $sut->_call('prepareArguments', array(new \ReflectionMethod($this, __FUNCTION__), $array));
		$this->assertEquals($array, $result);
	}

	/**
	 * @testdox Wrapper::prepareArguments() converts key based to index based array in the right order
	 *
	 * @test
	 */
	public function prepareArgumentsOrdersCorrectly() {
		$arguments = array("c" => "c", "b" => "b", "a" => "a");
		$expected = array("a", "b", "c");

		$reflection = new \ReflectionMethod(new Dummy(), 'foo');

		$sut = $this->getMock(__NAMESPACE__.'\WrapperProxy', array('__construct', 'getArrayType'), array(), '', false);
		$sut->expects($this->once())->method('getArrayType')->with($arguments)->will($this->returnValue(Wrapper::ARRAY_TYPE_DICT));

		$result = $sut->_call('prepareArguments', array($reflection->getParameters(), $arguments));

		$this->assertEquals($expected, $result);
	}

	/**
	 * @testdox Wrapper::prepareArguments() returns method's arguments' default values when ommited in parameter
	 *
	 * @test
	 * @todo IMHO better test isolation is required
	 */
	public function prepareArgumentsHandlesDefaultValues() {
		$arguments = array("a" => "a", "b" => "b");
		$expected = array("a", "b", "default value");

		$reflection = new \ReflectionMethod(new Dummy(), 'foo');

		$sut = $this->getMock(__NAMESPACE__.'\WrapperProxy', array('__construct', 'getArrayType'), array(), '', false);
		$sut->expects($this->once())->method('getArrayType')->with($arguments)->will($this->returnValue(Wrapper::ARRAY_TYPE_DICT));

		$result = $sut->_call('prepareArguments', array($reflection->getParameters(), $arguments));

		$this->assertEquals($expected, $result);
	}

	/**
	 * @testdox Wrapper::prepareArguments() throws \InvalidArgumentException on missing named parameter
	 *
	 * @test
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Missing parameter 'b' on position 1
	 */
	public function prepareArgumentsThrowsExceptionOnMissingParam() {
		$arguments = array("a" => "a", "c" => "c");

		$reflection = new \ReflectionMethod(new Dummy(), 'foo');

		$sut = $this->getMock(__NAMESPACE__.'\WrapperProxy', array('__construct', 'getArrayType'), array(), '', false);
		$sut->expects($this->once())->method('getArrayType')->with($arguments)->will($this->returnValue(Wrapper::ARRAY_TYPE_DICT));

		$sut->_call('prepareArguments', array($reflection->getParameters(), $arguments));
	}


	/**
	 * @testdox Wrapper::__invoke() properly calls registered callback
	 *
	 * @test
	 */
	public function invokeCallsCallback() {
		$callback = function ($a) {
			return func_get_args();
		};

		$arguments = array('a' => 'a');
		$expected = array('a');

		$sut = $this->getMock(__NAMESPACE__.'\WrapperProxy', array('__construct', 'prepareArguments'), array(), '', false);
		$sut->expects($this->once())->method('prepareArguments')->will($this->returnValue($expected));
		$sut->_set('callback', $callback);

		$result = $sut->_call('__invoke', array($arguments));

		$this->assertEquals($expected, $result);


	}
}
