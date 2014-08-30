<?php

namespace PhpSpec\Util;

class MethodAnalyser
{
    /**
     * @param string $class
     * @param string $method
     *
     * @return boolean
     */
    public function methodIsEmpty($class, $method)
    {
        return $this->reflectionMethodIsEmpty(new \ReflectionMethod($class, $method));
    }

    public function reflectionMethodIsEmpty(\ReflectionMethod $method)
    {
        $code = $this->getCodeBody($method);
        $codeWithoutComments = $this->stripComments($code);

        return $this->codeIsOnlyBlocksAndWhitespace($codeWithoutComments);
    }

    /**
     * @param ReflectionMethod $reflectionMethod
     *
     * @return string
     */
    private function getCodeBody(\ReflectionMethod $reflectionMethod)
    {
        $reflectionClass = $reflectionMethod->getDeclaringClass();

        $length = $reflectionMethod->getEndLine() - $reflectionMethod->getStartLine();
        $lines = file($reflectionClass->getFileName());

        if ($length == 0 ) {
            return preg_replace('/.*function.*{/', '', $lines[$reflectionMethod->getStartLine()-1]);
        }

        return join("\n", array_slice($lines, $reflectionMethod->getStartLine(), $length));
    }

    /**
     * @param $code
     * @return mixed
     */
    private function stripComments($code)
    {
        $tokens = token_get_all('<?php ' . $code);

        $comments = array_map(
            function ($token) {
                return $token[1];
            },
            array_filter($tokens,
                function ($token) {
                    return is_array($token)
                    && array_key_exists(0, $token)
                    && in_array(token_name($token[0]), array('T_COMMENT', 'T_DOC_COMMENT'));
                })
        );

        $commentless = str_replace($comments, '', $code);
        return $commentless;
    }

    /**
     * @param $codeWithoutComments
     * @return bool
     */
    private function codeIsOnlyBlocksAndWhitespace($codeWithoutComments)
    {
        return (bool)preg_match('/^[\s{}]*$/s', $codeWithoutComments);
    }
}
