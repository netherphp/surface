<?php

namespace Nether\Surface;
use Nether;

class Library
extends Nether\Common\Library {

	public const
	ConfThemes    = 'Nether.Surface.Themes',
	ConfThemeRoot = 'Nether.Surface.Theme.Root';

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	OnLoad(...$Argv):
	void {

		static::$Config->BlendRight([
			static::ConfThemeRoot => './themes',
			static::ConfThemes    => [ 'local', 'default' ]
		]);

		return;
	}

}
