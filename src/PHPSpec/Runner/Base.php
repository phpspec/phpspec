<?php

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
        echo count($this), ' Specs Executed:', PHP_EOL;
        echo $result; //reported plug later
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