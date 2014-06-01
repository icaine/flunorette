<?php

namespace Flunorette\Bridges\Nette;

class NetteDatabaseReplacer {

	static public function replace() {
		class_alias('Flunorette\\Bridges\\Nette\\Diagnostics\\ConnectionPanel', 'Nette\\Database\\Diagnostics\\ConnectionPanel', true);

		class_alias('Flunorette\\Connection', 'Nette\\Database\\Connection', true);
		class_alias('Flunorette\\SqlLiteral', 'Nette\\Database\\SqlLiteral', true);
		class_alias('Flunorette\\Statement', 'Nette\\Database\\Statement', true);

		class_alias('Flunorette\\Reflections\\IReflection', 'Nette\\Database\\IReflection', true);
		class_alias('Flunorette\\Reflections\\ConventionalReflection', 'Nette\\Database\\Reflection\\ConventionalReflection', true);
		class_alias('Flunorette\\Reflections\\DiscoveredReflection', 'Nette\\Database\\Reflection\\DiscoveredReflection', true);

		class_alias('Flunorette\\Selection\\ActiveRow', 'Nette\\Database\\Table\\ActiveRow', true);
		class_alias('Flunorette\\Selection\\Selection', 'Nette\\Database\\Table\\Selection', true);
		class_alias('Flunorette\\Selection\\GroupedSelection', 'Nette\\Database\\Table\\GroupedSelection', true);
	}

}
