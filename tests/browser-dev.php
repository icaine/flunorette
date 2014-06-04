<?php

/**
 * Ability to debug in browser with all the advantages of Tracy (Nette\Debugger)
 */
use Flunorette\Bridges\Nette\Diagnostics\ConnectionPanel;
use Nette\Diagnostics\Debugger;
use Nette\Diagnostics\IBarPanel;



if (isset($_SERVER['REMOTE_ADDR'])) {

	/**
	 * Causes output in browsers to appear as text/plain
	 * without messing with debugger or output itself
	 */
	class FakeStyle implements IBarPanel {

		public function getPanel() {
			return '';
		}

		public function getTab() {
			$str = "<style> * { white-space: pre; font-family: Monospace } </style>";
			$str .= "<style> #nette-debug *, #tracy-debug *, #netteBluescreen *, #tracyBluescreen * { white-space: normal; } </style>";
			$str .= "<style> #nette-debug pre, #nette-debug code, #tracy-debug pre, #tracy-debug code { white-space: pre; } </style>";
			return '<span>FakeStyle</span>' . $str;
		}

	}

	Debugger::$strictMode = TRUE;
	Debugger::enable(Debugger::DEVELOPMENT);

	$panel = new ConnectionPanel();

	if (null == Debugger::$bar) {
		Debugger::getBar()->addPanel($panel);
		Debugger::getBar()->addPanel(new FakeStyle);
	} else {
		Debugger::$bar->addPanel($panel);
		Debugger::$bar->addPanel(new FakeStyle);
	}




	//Some debug helper functions

	function vd($param, $title = null) {
		echo "<pre><b>$title</b>\n";
		Debugger::dump($param);
		echo '</pre>';
		return $param;
	}

	function d($param, $title = null) {
		static $c = array();
		Debugger::barDump($param, ($title ? : '- ') . '[' . @ ++$c[$title] . '] (' . strtoupper(gettype($param)) . ')');
		return $param;
	}

	function p($param) {
		print_r($param);
		echo "\n";
	}

	function t($name = null) {
		return Debugger::timer($name);
	}

	function et($name = null) {
		vd((t($name) * 1000) . ' ms', $name);
	}

}
