<?php
namespace PhpSpec\Message;

interface MessageInterface
{
    public function setMessage($currentExample);
    public function getMessage();
}