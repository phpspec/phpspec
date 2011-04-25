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
namespace PHPSpec\Runner;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Base implements \Countable
{

    /**
     * Collection of examples to run
     * 
     * @var \PHPSpec\Runner\Collection
     */
    protected $_collection = null;
    
    /**
     * @var \PHPSpec\Runner\Result
     */
    protected $_result = null;

    /**
     * Base Runner is constructed with a collection of examples
     * 
     * @param \PHPSpec\Runner\Collection $collection
     */
    public function __construct(Collection $collection)
    {
        $this->_collection = $collection;
    }

    /**
     * Executes all examples and sets the result in the result object in the
     * runner if any is given
     * 
     * @param Collection $collection
     * @param Result $result
     * @return \PHPSpec\Runner\Base
     */
    public static function execute(Collection $collection,
                                   Result $result = null)
    {
        $exampleRunner = new self($collection);
        if (!is_null($result)) {
            $exampleRunner->setResult($result);
        }
        $exampleRunner->executeExamples();
        return $exampleRunner;
    }

    /**
     * Executes all examples in the internal collection object
     */
    public function executeExamples()
    {
        $result = $this->getResult();
        $this->_collection->execute($result);
    }

    /**
     * Sets the result object
     * 
     * @param \PHPSpec\Runner\Result $result
     */
    public function setResult(Result $result)
    {
        $this->_result = $result;
    }

    /**
     * Returns the collection
     * 
     * @return \PHPSpec\Runner\Collection
     */
    public function getCollection()
    {
        return $this->_collection;
    }

    /**
     * Returns the result object
     * 
     * @return \PHPSpec\Runner\Result
     */
    public function getResult()
    {
        if (is_null($this->_result)) {
            $this->setResult(new Result());
        }
        return $this->_result;
    }

    /**
     * Returns the number of collection in the runner
     * @see Countable::count()
     * 
     * @return integer
     */
    public function count()
    {
        return count($this->_collection);
    }
}