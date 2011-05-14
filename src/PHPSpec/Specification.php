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
 * to license@phpspec.net so we can send you a copy immediately.
 *
 * @category  PHPSpec
 * @package   PHPSpec
 * @copyright Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                    Marcello Duarte
 * @license   http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
namespace PHPSpec;

/**
 * @see \PHPSpec\Specification\Object
 */
use \PHPSpec\Specification\Object;

/**
 * @see \PHPSpec\Specification\Scalar
 */
use \PHPSpec\Specification\Scalar;

/**
 * @see \PHPSpec\Specification\Closure
 */
use \PHPSpec\Specification\Closure;

/**
 * @see \PHPSpec\Runner\FailedMatcherException
 */
use \PHPSpec\Runner\FailedMatcherException;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Specification
{

    /**
     * An object representation of a expectation representing
     * "should" or "should not" states
     *
     * @var \PHPSpec\Expectation
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
     * @var boolean
     */
    protected $_matcherResult = null;

    /**
     * The Matcher object used to ascertain whether an expected
     * and actual match for the given Matcher's criteria (e.g
     * equals, instanceof, greater then, etc.)
     *
     * \PHPSpec\Matcher\Interface
     */
    protected $_matcher = null;

    /**
     * Public static factory method to create a new Specification (DSL)
     * object based on the given object or scalar value.
     *
     * Accepts a variable number of parameters in the case of object
     * values for the classname and any constructor arguments.
     *
     * @return \PHPSpec\Specification
     */
    public static function getSpec()
    {
        $args = func_get_args();
        $value = $args[0];
        if (is_callable($value)) {
            $spec = new Closure($value);
        } elseif ((is_string($value) && class_exists($value, true)) ||
            is_object($value)) {
            $class = new \ReflectionClass("\\PHPSpec\\Object\\Interrogator");
            $interrogator = $class->newInstanceArgs($args);
            $spec = new Object($interrogator);
        } else {
            $scalarValue = array_shift($args);
            $spec = new Scalar($scalarValue);
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
            'equal', 'be', 'beEqualTo', 'beAnInstanceOf', 'beGreaterThan',
            'beTrue', 'beFalse', 'beEmpty', 'beLessThan',
            'beGreaterThanOrEqualTo', 'beLessThanOrEqualTo', 'beSet', 'beNull',
            'beOfType', 'beIdenticalTo', 'match', 'throwException', 'beSuccess',
            'haveText'
        );
        if (in_array($method, $matchers)) {
            $this->setExpectedValue($args);
            $this->_createMatcher($method);
            $this->_performMatching();
            return true;
        }

        // check for any predicate style matching
        $result = preg_match(
            "/^((?:be|have)(?:A|An)?)(.*)/", $method, $matches
        );
        if ($result && empty($args) && $this instanceof Object) {
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
            $reflectedObject = new \ReflectionObject($predicateObject);
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
     * Diverts property requests to relevant methods similarly to __call()
     * (which allows the dropping of method braces) or returns an instance of
     * self. So "$spec->be" equates to "$spec->be()".
     *
     * @param string $name The method name to call (without braces)
     * @return \PHPSpec\Specification An instance of self
     */
    public function __get($name)
    {
        if (in_array(
            $name, array('should', 'shouldNot', 'a', 'an', 'of', 'be')
        )) {
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
     * Returns the Expectation object ruling how Matcher results are
     * interpreted (whether a matcher boolean result counts as a pass
     * or a fail).
     *
     * @return \PHPSpec\Expectation
     */
    public function getExpectation()
    {
        return $this->_expectation;
    }

    /**
     * Sets an Expected value with which to instantiate any new Matcher
     *
     * @param mixed $value
     * @return null
     */
    public function setExpectedValue($value)
    {
        $this->_expectedValue = $value;
    }

    /**
     * Gets an Expected value with which to instantiate any new Matcher
     *
     * @return mixed
     */
    public function getExpectedValue()
    {
        return $this->_expectedValue;
    }

    /**
     * Sets an Actual value with which to call a Matcher operation
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
     * Gets an Actual value with which to call a Matcher operation
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
     * Returns a boolean to indicate whether the DSL encountered
     * Matcher has been run yet to provide a result
     *
     * @return boolean
     */
    public function hasMatcherResult()
    {
        return isset($this->_matcherResult);
    }

    /**
     * Sets the boolean result from running a Matcher
     *
     * @param bool $result
     * @return null
     */
    public function setMatcherResult($result)
    {
        $this->_matcherResult = $result;
    }

    /**
     * Gets the boolean result from running a Matcher
     *
     * @param bool $result
     * @return null
     */
    public function getMatcherResult()
    {
        return $this->_matcherResult;
    }

    /**
     * Gets the textual description of a failed Matcher where
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
     * Gets the textual description of a failed Matcher where
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
     * Sets the overall class which manages the running of specs. The usual
     * default here is assumed to be \PHPSpec\Runner\Base.
     *
     * @param object A runner object
     * @todo Do we not yet have a Runner interface to reference?
     */
    public function setRunner($runner)
    {
        $this->_runner = $runner;
    }

    /**
     * Creates a new Matcher object based on calls
     * to the DSL grammer. (factory)
     *
     * @param DSL method call which was found to be a Matcher reference
     * @return null
     * @todo Refactor Matcher inclusion into a more extensible system
     */
    protected function _createMatcher($method)
    {
        $matcherClass = ucfirst($method);
        try {
            // eval is here because of a bug in 5.3.2
            eval(
                '$matcher = new \PHPSpec\Matcher\\' . $matcherClass . '(null);'
            );
            $reflectedMatcher = new \ReflectionClass($matcher);
            $expected = !is_array($this->getExpectedValue()) ?
                        array($this->getExpectedValue()) :
                        $this->getExpectedValue();            
            $this->_matcher = $reflectedMatcher->newInstanceArgs($expected);
        } catch(\PHPSpec\Exception $e) {
            die($e->getMessage());
            $factory = '$this->_matcher = new \PHPSpec\Context\Zend\Matcher\\' .
                       $matcherClass . '($this->getExpectedValue());';
            eval($factory);
        }
    }

    /**
     * Performs a Matcher operation and set the returned boolean result for
     * further analysis, e.g. comparison to the boolean Expectation.
     *
     * @param array $args
     * @return null
     */
    protected function _performMatching()
    {
        $args = $this->getActualValue();
        if (!is_array($this->getActualValue())) {
            $args = array($this->getActualValue());
        }
        $result = call_user_func_array(
            array($this->_matcher, 'matches'), $args
        );
        $this->setMatcherResult($result);
        if (!$result) {
            throw new FailedMatcherException();
        }
    }

}
