<?php
namespace PhpSpec\Config;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Yaml;
use RuntimeException;

class Manager
{
    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OptionsConfig
     */
    private $optionsConfig;

    public function setInput(InputInterface $input)
    {
        $this->input = $input;
    }

    public function optionsConfig()
    {
        if (!$this->optionsConfig) {
            $optionsArray = $this->parseConfigurationFile($this->input);
            $this->optionsConfig = new OptionsConfig($optionsArray, $this->input);
        }
        return $this->optionsConfig;
    }

    /**
     * @param InputInterface $input
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    private function parseConfigurationFile(InputInterface $input)
    {
        $paths = array('phpspec.yml','phpspec.yml.dist');

        if ($customPath = $input->getParameterOption(array('-c','--config'))) {
            if (!file_exists($customPath)) {
                throw new RuntimeException('Custom configuration file not found at '.$customPath);
            }
            $paths = array($customPath);
        }

        $config = $this->extractConfigFromFirstParsablePath($paths);

        if ($homeFolder = getenv('HOME')) {
            $config = array_replace_recursive($this->parseConfigFromExistingPath($homeFolder.'/.phpspec.yml'), $config);
        }

        return $config;
    }

    /**
     * @param array $paths
     *
     * @return array
     */
    private function extractConfigFromFirstParsablePath(array $paths)
    {
        foreach ($paths as $path) {
            $config = $this->parseConfigFromExistingPath($path);
            if (!empty($config)) {
                return $this->addPathsToEachSuiteConfig(dirname($path), $config);
            }
        }

        return array();
    }

    /**
     * @param string $path
     *
     * @return array
     */
    private function parseConfigFromExistingPath($path)
    {
        if (!file_exists($path)) {
            return array();
        }

        return Yaml::parse(file_get_contents($path));
    }

    /**
     * @param string $configDir
     * @param array $config
     *
     * @return array
     */
    private function addPathsToEachSuiteConfig($configDir, $config)
    {
        if (isset($config['suites']) && is_array($config['suites'])) {
            foreach ($config['suites'] as $suiteKey => $suiteConfig) {
                $config['suites'][$suiteKey] = str_replace('%paths.config%', $configDir, $suiteConfig);
            }
        }

        return $config;
    }
}