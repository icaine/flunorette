<?php

namespace Flunorette\Bridges\Nette;

use Flunorette\SqlPreprocessor;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Statement;

class FlunoretteExtension extends CompilerExtension {

	public $databaseDefaults = array(
		'dsn' => null,
		'user' => null,
		'password' => null,
		'options' => array(),
		'debugger' => true,
		'explain' => true,
		'reflection' => 'Flunorette\\Reflections\\DiscoveredReflection',
		'autowired' => null,
		'transactionCounter' => true,
		'delimiteMode' => SqlPreprocessor::DELIMITE_MODE_DEFAULT,
		'lazy' => true,
		'driverClass' => null
	);

	public function loadConfiguration() {
		$container = $this->getContainerBuilder();
		$config = $this->getConfig();
		$this->setupDatabase($container, $config);
	}

	private function setupDatabase(ContainerBuilder $container, array $config) {
		if (isset($config['dsn'])) {
			$config = array('default' => $config);
		}

		$autowired = true;
		foreach ((array) $config as $name => $info) {
			if (!is_array($info)) {
				continue;
			}
			$this->validate($info, $this->databaseDefaults, 'flunorette');

			$info += array('autowired' => $autowired) + $this->databaseDefaults;
			$autowired = false;

			foreach (array('transactionCounter', 'delimiteMode', 'lazy', 'driverClass') as $option) {
				if (isset($info[$option])) {
					$info['options'][$option] = $info[$option];
				}
			}

			foreach ((array) $info['options'] as $key => $value) {
				if (preg_match('#^PDO::\w+\z#', $key)) {
					unset($info['options'][$key]);
					$info['options'][constant($key)] = $value;
				}
			}

			if (!$info['reflection']) {
				$reflection = null;
			} elseif (is_string($info['reflection'])) {
				$reflection = new Statement(preg_match('#^[a-z]+\z#', $info['reflection']) ?
					'Flunorette\\Reflections\\' . ucfirst($info['reflection']) . 'Reflection' :
					$info['reflection'], strtolower($info['reflection']) === 'discovered' ? array('@self') : array());
			} else {
				$tmp = Compiler::filterArguments(array($info['reflection']));
				$reflection = reset($tmp);
			}

			$connection = $container->addDefinition($this->prefix($name))
				->setClass('Flunorette\\Connection', array($info['dsn'], $info['user'], $info['password'], $info['options']))
				->setAutowired($info['autowired'])
				->addSetup('setCacheStorage')
				->addSetup('Nette\Diagnostics\Debugger::getBlueScreen()->addPanel(?)', array(
				'Flunorette\Bridges\Nette\Diagnostics\ConnectionPanel::renderException'
			));

			if ($container->parameters['debugMode'] && $info['debugger']) {
				$connection->addSetup('Flunorette\\Helpers::createDebugPanel', array($connection, !empty($info['explain']), $name));
			}
		}
	}

	private function validate(array $config, array $expected, $name) {
		if ($extra = array_diff_key($config, $expected)) {
			$extra = implode(", $name.", array_keys($extra));
			throw new \Nette\InvalidStateException("Unknown option $name.$extra.");
		}
	}

}
