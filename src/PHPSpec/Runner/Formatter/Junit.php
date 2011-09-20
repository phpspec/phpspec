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
 *
 *
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="TestDummy" file="/home/mmueller/Development/Checkouts/trivago-php/tests/unit/TestDummy.php" tests="1" assertions="1" failures="0" errors="0" time="0.005316">
    <testcase name="testNothing" class="TestDummy" file="/home/mmueller/Development/Checkouts/trivago-php/tests/unit/TestDummy.php" line="12" assertions="1" time="0.005316"/>
  </testsuite>
</testsuites>
 */
namespace PHPSpec\Runner\Formatter;

use \PHPSpec\Util\Backtrace,
    \PHPSpec\Specification\Result\DeliberateFailure,
	\PHPSpec\Runner\Reporter;

class Junit extends Progress {

	/**
	 * @var \SimpleXMLElement
	 */
	private $_xml;

	public function __construct(Reporter $reporter)
	{
		parent::__construct($reporter);
		$this->_xml = new \SimpleXMLElement("<testsuites></testsuites>");
	}

	/**
	 * Prints the report in a specific format
	 */
	public function output()
	{
		$this->_xml->asXML('junit.xml');
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Receive update from subject
	 * @link http://php.net/manual/en/splobserver.update.php
	 * @param \PHPSpec\Runner\Reporter $subject <p>
	 * The SplSubject notifying the observer of an update.
	 * </p>
	 * @return void
	 */
	public function update(\SplSubject $subject)
	{
		/* @var $subject  */
		if ($subject->hasPendingExamples())
		{
			return;
		}
		$subject->getRuntime()
	}

	/**
	 * @return SimpleXMLElement
	 */
	private function createCase($name, $class, $file, $line, $assertions, $executionTime)
	{

	}

	/**
	 * @param $name
	 * @param $file
	 * @param $testcount
	 * @param $assertions
	 * @param $failures
	 * @param $errors
	 * @param $executionTime
	 * @return SimpleXMLElement
	 */
	private function createSuite($name, $file, $testcount, $assertions, $failures, $errors, $executionTime)
	{

	}

}
