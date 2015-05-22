<?php
/**
 * Fna - Functions with named arguments
 *
 * Copyright (c) 2013-2014, Alexander Wühr <l-x@mailbox.org>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Alexander Wühr nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package     Fna
 * @author      Alexander Wühr <l-x@mailbox.org>
 * @copyright   2013-2014 Alexander Wühr <l-x@mailbox.org>
 * @license     http://opensource.org/licenses/MIT  The MIT License (MIT)
 * @link        https://github.com/l-x/Fna
 */


namespace Fna;

use Fna\Exception\InvalidCallbackException;
use Fna\Exception\InvalidParameterException;

/**
 * Class Wrapper
 *
 * @package     Fna
 * @author      Alexander Wühr <l-x@mailbox.org>
 * @copyright   2013-2014 Alexander Wühr <l-x@mailbox.org>
 * @license     http://opensource.org/licenses/MIT  The MIT License (MIT)
 * @link        https://github.com/l-x/Fna
 */
class Wrapper {

	const ARRAY_TYPE_MIXED = -1;
	const ARRAY_TYPE_LIST = 0;
	const ARRAY_TYPE_DICT = 1;

	/**
	 * Member containing the registered callback
	 *
	 * @var callable
	 */
	protected $callback;


	/**
	 * Member containing the \ReflectionParameter instance of the registered callback
	 *
	 * @var \ReflectionParameter
	 */
	protected $reflection_parameter;

	/**
	 * Constructor
	 *
	 * @param callable $callback
	 */
	public function __construct($callback) {
		$this->setCallback($callback);
	}

	/**
	 * Determines wether an array is index or key based
	 *
	 * @param array $array
	 *
	 * @return int
	 */
	protected function getArrayType(array $array) {
		$indices = count(array_filter(array_keys($array), 'is_string'));

		if ($indices == 0) {
			$type = self::ARRAY_TYPE_LIST;
		} elseif ($indices == count($array)) {
			$type = self::ARRAY_TYPE_DICT;
		} else {
			$type = self::ARRAY_TYPE_MIXED;
		}

		return $type;
	}

	/**
	 * Returns the reflection instance based on the type of the provided callable
	 *
	 * @param callable $callback
	 *
	 * @return \ReflectionFunction|\ReflectionMethod
	 * @throws InvalidCallbackException
	 */
	protected function getCallbackReflection($callback) {
		if (is_string($callback) && strpos($callback, '::') !== false) {
			$callback = explode('::', $callback, 2);
		}
		switch (true) {
			case is_string($callback) && function_exists($callback):
			case $callback instanceof \Closure:
				$reflection_method = new \ReflectionFunction($callback);
				break;
			case is_object($callback) && method_exists($callback, '__invoke'):
				$callback = array($callback, '__invoke');
			case is_array($callback):
				$reflection_method = new \ReflectionMethod($callback[0], $callback[1]);
				break;
		// @codeCoverageIgnoreStart
			default:
				throw new \LogicException('Found something callable that we can\'t reflect');

		}
		// @codeCoverageIgnoreEnd
		return $reflection_method;
	}

	/**
	 * Gets the reflection class for and sets the callback
	 *
	 * @param callable $callback
	 *
	 * @return $this
	 * @throws InvalidCallbackException
	 */
	protected function setCallback($callback) {
		if (!is_callable($callback)) {
			throw new InvalidCallbackException('Invalid callback');
		}

		$this->reflection_parameter =  $this
			     ->getCallbackReflection($callback)
			     ->getParameters()
		;
		$this->callback = $callback;

		return $this;
	}

	/**
	 * Prepares the argument array for use with call_user_func_array
	 *
	 * @param \ReflectionParameter[] $reflection_parameter
	 * @param array $arguments
	 *
	 * @return array
	 * @throws InvalidParameterException
	 */
	protected function prepareArguments($reflection_parameter, $arguments) {
		$array_type = $this->getArrayType($arguments);
		$prepared = array();

		if ($array_type == self::ARRAY_TYPE_LIST) {
			$prepared = $arguments;
		} elseif ($array_type == self::ARRAY_TYPE_DICT) {
			foreach ($reflection_parameter as $parameter) {
				$name = $parameter->getName();

				if (isset($arguments[$name])) {
					$value = $arguments[$name];
				} else if ($parameter->isDefaultValueAvailable()) {
					$value = $parameter->getDefaultValue();
				} else {
					throw new InvalidParameterException("Missing parameter '$name' on position {$parameter->getPosition()}");
				}

				$prepared[] = $value;
			}
		} else {
			throw new InvalidParameterException('Unable to handle mixed arrays');
		}

		return $prepared;
	}

	/**
	 * Magic method for calling the wrapped callback
	 *
	 * @param array $arguments
	 *
	 * @return mixed
	 */
	public function __invoke(array $arguments = array()) {
		return call_user_func_array(
			$this->callback,
			$this->prepareArguments($this->reflection_parameter, $arguments)
		);
	}
}

