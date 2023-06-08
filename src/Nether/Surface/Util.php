<?php

namespace Nether\Surface;

class Util {

	static public function
	MakePathableKey(string $Input):
	string {
	/*//
	utility method i have in almost all of my projects to take input uris and
	spit out versions that would break anything if we tried to use it in a
	file path. so its a super sanitiser only allowing alphas, numerics,
	dashes, periods, and forward slashes. does not allow dot stacking
	to prevent traversal foolery.
	//*/

		$Output = strtolower(trim($Input));

		// allow things that could be nice clean file names.

		$Output = preg_replace(
			'#[^a-zA-Z0-9\-\/\.]#', '',
			$Output
		);

		// disallow traversal foolery.

		$Output = preg_replace(
			'#[\.]{2,}#', '',
			$Output
		);

		$Output = preg_replace(
			'#[\/]{2,}#', '/',
			$Output
		);

		return $Output;
	}

	static public function
	ParseQueryString(?string $Input):
	array {
	/*//
	@date 2022-03-31
	wrap parse_str to make it not stupid by dealing with null and the
	stupid need to do a c-style strcpy instead of returning.
	//*/

		if($Input === NULL)
		return [];

		////////

		$StupidFuckingTempVariable = NULL;

		parse_str(
			$Input,
			$StupidFuckingTempVariable
		);

		return $StupidFuckingTempVariable;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	static public function
	VarDump(mixed $Input):
	void {
	/*//
	@date 2022-03-31
	wrap var_dump to make it more readable.
	//*/

		ob_start();
		var_dump($Input);
		$Output = ob_get_clean();

		// fixes the annoying newline after the arrow.

		$Output = preg_replace(
			'/\]=>\n\h+/', '] => ',
			$Output
		);

		// convert indention to tabs.

		$Output = preg_replace_callback(
			'#^(\h+)#ms',
			(
				fn(array $Result)
				=> str_repeat("\t", strlen($Result[1]) / 2)
			),
			$Output
		);

		echo $Output;
		return;
	}

	static public function
	VarDumpPre(mixed $Input):
	void {
	/*//
	@date 2022-03-31
	wrap var_dump to make it more readable in html.
	//*/

		echo '<pre>';
		static::VarDump($Input);
		echo '</pre>';

		return;
	}

}
