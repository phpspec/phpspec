<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Process;

/**
 * @internal
 * How a php is set up: its ini settings and the extensions it loaded. Held up
 * against a php started afresh on the same binary and php.ini, it says what
 * that one lacks: what a command line gave this one, or what changed while it
 * ran. A worker started with it runs as its parent does.
 */
final readonly class PhpSetup
{
    /**
     * @param array<string, string|null> $settings the ini settings, by name
     * @param list<string> $extensions the extensions loaded, Zend ones among them
     * @param list<string> $zendExtensions the Zend extensions loaded
     */
    public function __construct(
        private array $settings,
        private array $extensions = [],
        private array $zendExtensions = [],
    ) {}

    /**
     * The php options that set up a php started as $fresh as this one: the
     * extensions it lacks, loaded by their file names, then each setting it
     * does not have, those of the extensions it lacks among them.
     *
     * @return list<string>
     */
    public function over(PhpSetup $fresh): array
    {
        $zend = array_diff($this->files($this->zendExtensions), $this->files($fresh->zendExtensions));
        $plain = array_diff($this->files($this->extensions), $this->files($fresh->extensions), $this->files($this->zendExtensions));
        $options = [];

        foreach ($plain as $file) {
            array_push($options, '-d', 'extension=' . $file);
        }

        foreach ($zend as $file) {
            array_push($options, '-d', 'zend_extension=' . $file);
        }

        foreach ($this->settings as $name => $value) {
            if (!array_key_exists($name, $fresh->settings) || $fresh->settings[$name] !== $value) {
                array_push($options, '-d', $name . '=' . $value);
            }
        }

        return $options;
    }

    /**
     * The file each extension loads from, as its name gives it: "Zend
     * OPcache" from opcache, "SimpleXML" from simplexml.
     *
     * @param list<string> $extensions
     * @return list<string>
     */
    private function files(array $extensions): array
    {
        return array_map(static function (string $name): string {
            $words = explode(' ', $name);

            return strtolower(end($words));
        }, $extensions);
    }
}
