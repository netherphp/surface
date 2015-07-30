# Nether Surface

[![nether.io](https://img.shields.io/badge/nether-surface-C661D2.svg)](http://nether.io/)
[![Build Status](https://travis-ci.org/netherphp/surface.svg)](https://travis-ci.org/netherphp/surface)
[![Packagist](https://img.shields.io/packagist/v/netherphp/surface.svg)](https://packagist.org/packages/netherphp/surface)
[![Packagist](https://img.shields.io/packagist/dt/netherphp/surface.svg)](https://packagist.org/packages/netherphp/surface)



## Installing

Require this package in your composer.json. This will also include Object, Stash, and Option.

	require { "netherphp/surface": "^1.1.0" }

Then install it or update into it.

	$ composer install --no-dev
	$ composer update --no-dev



## Creating a Surface Theme

* Project Root: `/opt/website/www`
* Surface Theme Dir: `/opt/website/www/themes`
* Default Theme: `/opt/website/www/themes/default`
* Main Template: `/opt/website/www/themes/default/design.phtml`
* Surface Area Dir: `/opt/website/www/themes/default/area/`

Your `design.phtml` is what defines the entire theme. Once you make that, it is
pretty open ended what you do next. That file will basically be your html, head,
and body tags. Take for example this super simple template.

	<html>
	<head>
		<title><?php $this->Show('page-title') ?></title>
	</head>
	<body>
	<?php $this->ShowArea('header') ?>

	<div>
		<?php $this->Show('stdout') ?>
	</div>

	<?php $this->ShowArea('footer') ?>
	</body>
	</html>

This example will show the page title which could have been defined by the
app at any time, as well as include the files `header.phtml` and `footer.phtml`
which it will attempt to find in the `/opt/website/www/themes/default/area`
directory. In the main area of the page it dumped the main application output.

**Note: The `$this` will only work if you are on PHP 5.6+ or newer. In order
to access Surface on older versions of PHP you can use the $surface variable
instead, which is created in the template scopes for you.**

	<html>
	<head>
		<title><?php $surface->Show('page-title') ?></title>
	...

All my examples are going to assume PHP 5.6 because I can have nice things.



## Starting Surface

Surface uses Nether Option to handle base configuration. As a bare minimum it
needs to know two things about your application: the file path to your web
root, and the URI path to the web root.

	Nether\Option::Set([
		'nether-web-root' => '/opt/website/www',
		'nether-web-path' => '/'
	]);

	new Nether\Surface;

This tells surface to look in /opt/website/www for the `themes` directory it
will work with, and that the browser will be able to find the `themes`
on the web root. This translates into `/opt/website/www/themes` = `/themes/`.

Afterwards it created a new instance of the Nether Surface engine and
hillariously forgot about it. But not really. By default Surface will stash
itself. So this code above you can drop that into the configuration that
happens before your app even begins and surface will be ready and waiting.

If you do nothing else, whenever your application ends Surface will
automatically throw itself together and chuck out the page at the very last
moment.



## Using Surface

At any point in your application you can access that Surface instance
through the Nether Stash.

	Nether\Stash::Get('surface')
	->Set('name',$user->Name)
	->Set('email',$user->Email)
	->ShowArea('forms/user-change-info');
	
This will make the values of `name` and `email` available to the theme engine
and then print out the `{$theme}/area/forms/user-change-info.phtml` surface
area.

At any point inside the template (.phtml) files you can access surface via
the `$surface` variable. If you are on PHP 5.6+ you can use `$this` instead.

	<form>
		<div>
			Your Name:
			<input type="text" name="name" value="<?php $this->Show('name') ?>" />
		</div>
		<div>
			Your Email:
			<input type="text" name="email" value="<?php $this->Show('email') ?>" />
		</div>
	</form>

The show method will automatically `htmlentities()` the data for you. If you
need the data straight up use `echo $this->Get('key')` instead.



## Testing

This project uses PHPUnit to test.

	composer install
	phpunit --bootstrap vendor/autoload.php tests


