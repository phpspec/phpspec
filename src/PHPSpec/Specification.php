<?php
/**
 * PHPSpec
 *
 * LICENSE
 *
 * This file is subject to the GNU Lesser General Public License Version 3
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/lgpl-3.0.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@phpspec.org so we can send you a copy immediately.
 *
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 P�draic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 P�draic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class PHPSpec_Specification
{

    /**
     * An object representation of a expectation representing
     * "should" or "should not" states
     *
     * @var PHPSpec_Expectation
     */
    protected $_expectation = null;

    /**
     * The Expected value entered using the DSL. This is used
     * to instantiate the relevant Matcher object.
     *
     * @var mixed
     */
    protected $_expectedValue = null;

    /**
     * The Actual value entered using the DSL. This is used
     * to run the Matcher and ascertain a passing or failing
     * matching operation.
     *
     * @var mixed
     */
    protected $_actualValue = null;

    /**
     * The overall result of the Matcher operation as
     * a boolean
     *
     * @var bool
     */
    protected $_matcherResult = null;

    /**
     * The Matcher object used to ascertain whether an expected
     * and actual match for the given Matcher's criteria (e.g
     * equals, instanceof, greater then, etc.)
     *
     * PHPSpec_Matcher_Interface
     */
    protected $_matcher = null;

    /**
     * Public static factory method to create a new Specification (DSL)
     * object based on the given object or scalar value.
     *
     * Accepts a variable number of parameters in the case of object
     * values for the classname and any constructor arguments.
     *
     * @return PHPSpec_Specification
     */
    public static function getSpec()
    {
        $args = func_get_args();
        $value = $args[0];
        if ((is_string($value) && class_exists($value, true)) || is_object($value)) {
            $class = new ReflectionClass('PHPSpec_Object_Interrogator');
            $interrogator = $class->newInstanceArgs($args);
            $spec = new PHPSpec_Specification_Object($interrogator);
        } else {
            $scalarValue = array_shift($args);
            $spec = new PHPSpec_Specification_Scalar($scalarValue);
        }

        return $spec;
    }

    /**
     * Magic __call method for detecting DSL grammer elements and
     * diverting them to the relevant real methods we are utilising
     * or returning an instance of self if simply a "link" grammer
     * term which exists for the purpose of enabling a natural
     * English grammer.
     *
     * @param string $method
     * @param array $args
     * @return mixed An instance of self or TRUE if a Matcher was run
     */
    public function __call($method, $args)
    {
        // check for an expectation type
        if (in_array($method, array('should', 'shouldNot'))) {
            $this->_expectation->$method();
            return $this;
        }

        // pass through on empty be() calls as syntactic sugar
        if (in_array($method, array('be'))) {
            if (empty($args)) {
                return $this;
            }
        }

        // check for Matcher references
        $matchers = array(
            'equal', 'be', 'beEqualTo', 'beAnInstanceOf', 'beGreaterThan', 'beTrue', 'beFalse', 'beEmpty', 'beLessThan', 'beGreaterThanOrEqualTo', 'beLessThanOrEqualTo', 'beSet', 'beNull', 'beOfType', 'beIdenticalTo', 'match', 'throw'
        );
        if (in_array($method, $matchers)) {
            $this->setExpectedValue(array_shift($args));
            $this->_createMatcher($method);
            $this->_performMatching($args);
            return true;
        }

        // check for any predicate style matching
        $result = preg_match("/^((?:be|have)(?:A|An)?)(.*)/", $method, $matches);
        if ($result && empty($args) && $this instanceof PHPSpec_Specification_Object) {
            $predicate = $matches[1];
            $predicateSuffix = $matches[2];

            if (strpos($predicate, 'have') !== false) {
                $predicateMethodPrefixes = array('has', 'hasA', 'hasAn');
            } else {
                $predicateMethodPrefixes = array('is', 'isA', 'isAn');
            }
            $predicatePossibleMatches = array();
            foreach ($predicateMethodPrefixes as $prefix) {
                $predicatePossibleMatches[] = $prefix . $predicateSuffix;
            }

            $predicateObject = $this->getInterrogator()
                ->getSourceObject(); // it's buried deep ;)
            $reflectedObject = new ReflectionObject($predicateObject);
            $methods = $reflectedObject->getMethods();
            foreach ($predicatePossibleMatches as $possible) {
                foreach ($methods as $m) {
                    if ($possible == $m->getName()) {
                        $methodToPredicate = $m->getName();
                        break 2;
                    }
                }
            }
            if (!empty($methodToPredicate) && strlen($methodToPredicate) >= 2) {
                $this->setExpectedValue(true);
                $this->_createMatcher('predicate');
                $this->_matcher->setMethodName($methodToPredicate);
                $this->_matcher->setObject($predicateObject);
                $this->_matcher->setPredicateCall($method);
                $this->_performMatching();
                return true;
            }
        }

    }

    /**
     * Very similar to __call(), simply diverts property requests to relevant
     * methods (which allows the dropping of method braces) or returns an
     * instance of self. So "$spec->be" equates to "$spec->be()".
     *
     * @param string $name The method name to call (without braces)
     * @return PHPSpec_Specification An instance of self
     */
    public function __get($name)
    {
        if (in_array($name, array('should', 'shouldNot', 'a', 'an', 'of', 'be'))) {
            if (in_array($name, array('should', 'shouldNot', 'be'))) {
                switch ($name) {
                    case 'should':
                        $this->should();
                        break;
                    case 'shouldNot':
                        $this->shouldNot();
                        break;
                    case 'be':
                        $this->be();
                        break;
                }
            }
            return $this;
        }
    }

    /**
     * Return the Expectation object ruling how Matcher results are
     * interpreted (whether a matcher boolean result counts as a pass
     * or a fail).
     *
     * @return PHPSpec_Expectation
     */
    public function getExpectation()
    {
        return $this->_expectation;
    }

    /**
     * Set an Expected value with which to instantiate any new Matcher
     *
     * @param mixed $value
     * @return null
     */
    public function setExpectedValue($value)
    {
        $this->_expectedValue = $value;
    }

    /**
     * Get an Expected value with which to instantiate any new Matcher
     *
     * @return mixed
     */
    public function getExpectedValue()
    {
        return $this->_expectedValue;
    }

    /**
     * Set an Actual value with which to call a Matcher operation
     * and ascertain whether Expected and Actual values match.
     *
     * @param mixed $value
     * @return null
     */
    public function setActualValue($value)
    {
        $this->_actualValue = $value;
    }

    /**
     * Get an Actual value with which to call a Matcher operation
     * and ascertain whether Expected and Actual values match.
     *
     * @param mixed $value
     * @return null
     */
    public function getActualValue()
    {
        return $this->_actualValue;
    }

    /**
     * A boolean return to indicate whether the DSL encountered
     * Matcher has been run yet to provide a result
     *
     * @return bool
     */
    public function hasMatcherResult()
    {
        return isset($this->_matcherResult);
    }

    /**
     * Set the boolean result from running a Matcher
     *
     * @param bool $result
     * @return null
     */
    public function setMatcherResult($result)
    {
        $this->_matcherResult = $result;
    }

    /**
     * Get the boolean result from running a Matcher
     *
     * @param bool $result
     * @return null
     */
    public function getMatcherResult()
    {
        return $this->_matcherResult;
    }

    /**
     * Get the textual description of a failed Matcher where
     * we expected the result to be TRUE (i.e. matched) based
     * on a positive "should" Expectation.
     *
     * That is to say, "$foo should be 1" was expected to result in
     * in a positive match but instead failed.
     *
     * @return string
     */
    public function getMatcherFailureMessage()
    {
        return $this->_matcher->getFailureMessage();
    }

    /**
     * Get the textual description of a failed Matcher where
     * we expected the result to be FALSE (i.e. unmatched) based
     * on a negative "should not" Expectation.
     *
     * That is to say, "$foo should not be 1" was expected to result in
     * in a failed match but instead passed.
     *
     * @return string
     */
    public function getMatcherNegativeFailureMessage()
    {
        return $this->_matcher->getNegativeFailureMessage();
    }

    /**
     * Set the overall class which manages the running of specs. The usual
     * default here is assumed to be PHPSpec_Runner_Base.
     *
     * @param object A runner object
     * @todo Do we not yet have a Runner interface to reference?
     */
    public function setRunner($runner)
    {
        $this->_runner = $runner;
    }

    /**
     * A factory method to create a new Matcher object based on calls
     * to the DSL grammer.
     *
     * @param DSL method call which was found to be a Matcher reference
     * @return null
     */
    protected function _createMatcher($method)
    {
        $matcherClass = 'PHPSpec_Matcher_' . ucfirst($method);
        $this->_matcher = new $matcherClass( $this->getExpectedValue() );
    }

    /**
     * Perform a Matcher operation and set the returned boolean result for
     * further analysis, e.g. comparison to the boolean Expectation.
     *
     * @param array $args
     * @return null
     */
    protected function _performMatching($args = null)
    {
        if (!is_null($args) && is_array($args)) {
            $matchArgs = array($this->getActualValue()) + $args;
        } else {
            $matchArgs = array($this->getActualValue());
        }
        $result = call_user_func_array(array($this->_matcher, 'matches'), $matchArgs);
        $this->setMatcherResult($result);
        if (!$result) {
            throw new PHPSpec_Runner_FailedMatcherException();
        }
    }

}
