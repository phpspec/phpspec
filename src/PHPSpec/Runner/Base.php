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
 * @copyright  Copyright (c) 2007 Pádraic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 Pádraic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class PHPSpec_Runner_Base implements Countable
{

    protected $_collection = null;
    protected $_result = null;

    public function __construct(PHPSpec_Runner_Collection $collection)
    {
        $this->_collection = $collection;
    }

    public static function execute(PHPSpec_Runner_Collection $collection, PHPSpec_Runner_Result $result = null)
    {
        $exampleRunner = new self($collection);
        if (!is_null($result)) {
            $exampleRunner->setResult($result);
        }
        $exampleRunner->executeExamples();
        return $exampleRunner;
    }

    public function executeExamples()
    {
        $result = $this->getResult();
        $this->_collection->execute($result);
    }

    public function setResult(PHPSpec_Runner_Result $result)
    {
        $this->_result = $result;
    }

    public function getCollection()
    {
        return $this->_collection;
    }

    public function getResult()
    {
        if (is_null($this->_result)) {
            $this->setResult( new PHPSpec_Runner_Result() );
        }
        return $this->_result;
    }

    public function count()
    {
        return count($this->_collection);
    }
}