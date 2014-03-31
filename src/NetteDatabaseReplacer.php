<?php

namespace Flunorette;

class NetteDatabaseReplacer {

	static public function replace() {
		class_alias('Flunorette\\Bridges\\Nette\\Diagnostics\\ConnectionPanel', 'Nette\\Database\\Diagnostics\\ConnectionPanel', true);

		class_alias('Flunorette\\Connection', 'Nette\\Database\\Connection', true);
		class_alias('Flunorette\\SqlLiteral', 'Nette\\Database\\SqlLiteral', true);
		class_alias('Flunorette\\Statement', 'Nette\\Database\\Statement', true);

		class_alias('Flunorette\\IReflection', 'Nette\\Database\\IReflection', true);
		class_alias('Flunorette\\ConventionalReflection', 'Nette\\Database\\Reflection\\ConventionalReflection', true);
		class_alias('Flunorette\\DiscoveredReflection', 'Nette\\Database\\Reflection\\DiscoveredReflection', true);

		class_alias('Flunorette\\ActiveRow', 'Nette\\Database\\Table\\ActiveRow', true);
		class_alias('Flunorette\\Selection', 'Nette\\Database\\Table\\Selection', true);
		class_alias('Flunorette\\GroupedSelection', 'Nette\\Database\\Table\\GroupedSelection', true);
	}

}
