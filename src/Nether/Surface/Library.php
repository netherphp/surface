<?php

namespace Nether\Surface;

use SplFileInfo;
use Nether\Object\Datastore;

class Library {

	public const
	ConfThemes    = 'Nether.Surface.Themes',
	ConfThemeRoot = 'Nether.Surface.ThemeRoot';

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	static public function
	Init(Datastore $Config=NULL):
	bool {

		static::PrepareDefaultConfig($Config);

		return TRUE;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	static public function
	PrepareDefaultConfig(?Datastore $Config=NULL):
	Datastore {

		if($Config === NULL)
		$Config = new Datastore;

		$Config->BlendRight([
			static::ConfThemeRoot => './themes',
			static::ConfThemes    => [ 'local', 'default' ]
		]);

		return $Config;
	}

}
