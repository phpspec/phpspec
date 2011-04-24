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
namespace PHPSpec\Runner\Filter;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Standard extends \FilterIterator
{

    /**
	 * Check whether the current element of the iterator is acceptable
	 * @link http://www.php.net/manual/en/filteriterator.accept.php
	 * 
	 * @return bool true if the current element is acceptable, otherwise false.
	 */
    public function accept()
    {
        $path = $this->getInnerIterator()->current();
        $fileName = basename($path);
        $filePrefix = substr($fileName, 0, 8);
        $filePostfix = substr($fileName, -8, 4);
        $fileSuffix = substr($fileName, -4, 4);
        if (($filePrefix == 'Describe' || $filePrefix == 'describe' ||
             $filePostfix == 'Spec' || $filePostfix == 'spec') &&
            $fileSuffix == '.php') {
            return true;
        }
        return false;
    }

}