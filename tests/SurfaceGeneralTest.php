<?php

namespace Nether\Surface\Test;
use \Nether;
use \PHPUnit as PHPUnit;

////////
////////

class SurfaceGeneralTest
extends PHPUnit\Framework\TestCase {

	/** @before */
	public function
	PrepareSurfaceEnv() {
	/*//
	dump some settings into the option manager to temper the surface
	instances we create for testing. forcing a few defaults that i
	want to test against just incase the defaults change a little
	in the future.
	//*/

		Nether\Option::Set([
			'nether-web-root'      => dirname(dirname(__FILE__)),
			'nether-web-path'      => '/',
			'surface-theme'        => 'testing',
			'surface-theme-stack'  => ['common'],
			'surface-auto-capture' => FALSE,
			'surface-auto-render'  => FALSE,
			'App.Name'             => 'App Name',
			'app-name'             => 'Old Name'
		]);

		return;
	}

	/** @test */
	public function
	TestThemeProperty() {
		$surface = new Nether\Surface;

		$this->AssertTrue(
			($surface->GetTheme() === 'testing'),
			'sucked up the correct theme from config'
		);

		$surface->SetTheme('still-testing');
		$this->AssertTrue(
			($surface->GetTheme() === 'still-testing'),
			'explicit set theme for surface instance'
		);

		return;
	}

	/** @test */
	public function
	TestThemeStackingFromDefaults() {
		$surface = new Nether\Surface;

		$stack = $surface->GetThemeStack();
		$this->AssertTrue(
			(is_array($stack) && count($stack) === 2),
			'check we got a stack array of two in this case'
		);
		$this->AssertTrue($stack[0] === 'testing');
		$this->AssertTrue($stack[1] === 'common');

		return;
	}

	/** @test */
	public function
	TestThemeStackingExplictArray() {
		$surface = new Nether\Surface;

		$stack = $surface->GetThemeStack(['zomg','bbq']);
		$this->AssertTrue(
			(is_array($stack) && count($stack) === 4),
			'check that we got a stack array of four in this case'
		);
		$this->AssertTrue($stack[0] === 'zomg');
		$this->AssertTrue($stack[1] === 'bbq');
		$this->AssertTrue($stack[2] === 'testing');
		$this->AssertTrue($stack[3] === 'common');

		return;
	}

	/** @test */
	public function
	TestThemeStackingExplicitString() {
		$surface = new Nether\Surface;

		$stack = $surface->GetThemeStack('zomg:bbq');
		$this->AssertTrue(
			(is_array($stack) && count($stack) === 4),
			'check that we got a stack array of four in this case'
		);
		$this->AssertTrue($stack[0] === 'zomg');
		$this->AssertTrue($stack[1] === 'bbq');
		$this->AssertTrue($stack[2] === 'testing');
		$this->AssertTrue($stack[3] === 'common');

		return;
	}

	/** @test */
	public function
	TestThemeFileFinding() {
		$surface = new Nether\Surface;

		$filename = $surface->GetThemeFile('design.nope');
		$this->AssertTrue(
			($filename === false),
			'that we failed to find a non-existant file'
		);

		$filename = $surface->GetThemeFile('design.phtml');
		$this->AssertTrue(
			($filename === "{$surface->GetThemeRoot()}/testing/design.phtml"),
			'that we found an existing file'
		);

		return;
	}

	/** @test */
	public function
	TestStackedThemeFileFinding() {
		$surface = new Nether\Surface;

		$filename = $surface->GetThemeFile('testing-also:design.nope');
		$this->AssertTrue(
			($filename === false),
			'that we failed to find a non-existant file'
		);

		$filename = $surface->GetThemeFile('testing-also:design.phtml');
		$this->AssertTrue(
			($filename === "{$surface->GetThemeRoot()}/testing-also/design.phtml"),
			"we found an existing file via stacked name"
		);

		return;
	}

	/** @test */
	public function
	TestAreaFileFinding() {
		$surface = new Nether\Surface;
		$this->AssertTrue($surface->GetArea('index') === 'this is the index page');
		$this->AssertTrue($surface->GetArea('about') === 'this is the about page');
		return;
	}

	/** @test */
	public function
	TestStackedAreaFileFinding() {
		$surface = new Nether\Surface;
		$this->AssertTrue($surface->GetArea('testing-also:index') === 'this is the index page also');
		$this->AssertTrue($surface->GetArea('testing-also:about') === 'this is the about page also');
		return;
	}

	/** @test */
	public function
	TestSetAndGetData() {
		$surface = new Nether\Surface;
		$surface->Set('test','<b>boing</b>');
		$this->AssertTrue($surface->Get('test') === '<b>boing</b>');
		return;
	}

	/** @test */
	public function
	TestSetAndShowData() {
		$surface = new Nether\Surface;
		$surface->Set('test','<b>boing</b>');

		ob_start();
		$surface->Show('test');
		$this->AssertTrue(
			(ob_get_clean() === '&lt;b&gt;boing&lt;/b&gt;'),
			'by default shows are protected'
		);

		ob_start();
		$surface->Show('test',true);
		$this->AssertTrue(
			(ob_get_clean() === '&lt;b&gt;boing&lt;/b&gt;'),
			'explictly shows are protected'
		);

		ob_start();
		$surface->Show('test',false);
		$this->AssertTrue(
			(ob_get_clean() === '<b>boing</b>'),
			'explictly shows are unprotected'
		);

		return;
	}

	/** @test */
	public function
	TestFinalRenderingSolution() {
		ob_start();
		// main screen turn on.
			$surface = new Nether\Surface;
			$surface->Start();
			$surface->ShowArea('index');
			$surface->Render();
		$content = ob_get_clean();

		$this->AssertTrue($content === 'output: this is the index page');
		return;
	}

	/** @test */
	public function
	TestFinalRenderingSolutionReturn() {
		$surface = new Nether\Surface;
		$surface->Start();
		$surface->ShowArea('index');
		$content = $surface->Render(true);

		$this->AssertTrue($content === 'output: this is the index page');
		return;
	}

	/** @test */
	public function
	TestInvokeSyntax() {

		$Surface = new Nether\Surface;

		$this->AssertTrue($Surface('Null') === NULL);
		$this->AssertTrue($Surface('Test','Value') instanceof Nether\Surface);
		$this->AssertTrue($Surface('Test') === 'Value');

		return;
	}

	/** @test */
	public function
	TestPageTitleDefault() {

		ob_start();
		$Surface = new Nether\Surface;
		$Surface->Start();
		$Surface->Render();
		ob_get_clean();

		$this->AssertEquals(
			'App Name',
			$Surface->Get('Page.Title')
		);

		return;
	}

	/** @test */
	public function
	TestPageTitleDeprecated() {

		unset(Nether\Option::$Storage['App.Name']);

		ob_start();
		$Surface = new Nether\Surface;
		$Surface->Start();
		$Surface->Render();
		ob_get_clean();

		$this->AssertEquals(
			'Old Name',
			$Surface->Get('Page.Title')
		);

		$this->AssertEquals(
			'Old Name',
			$Surface->Get('page-title')
		);

		return;
	}

	/** @test */
	public function
	TestPageTitleSet() {

		ob_start();
		$Surface = new Nether\Surface;
		$Surface->Start();
		$Surface->Set('Page.Title','Page Title');
		$Surface->Render();
		ob_get_clean();

		$this->AssertEquals(
			'Page Title - App Name',
			$Surface->Get('Page.Title')
		);

		return;
	}

	/** @test */
	public function
	TestPageTitleSetDeprecated() {

		unset(Nether\Option::$Storage['App.Name']);

		ob_start();
		$Surface = new Nether\Surface;
		$Surface->Start();
		$Surface->Set('Page.Title','Page Title');
		$Surface->Render();
		ob_get_clean();

		$this->AssertEquals(
			'Page Title - Old Name',
			$Surface->Get('Page.Title')
		);

		$this->AssertEquals(
			'Page Title - Old Name',
			$Surface->Get('page-title')
		);

		return;
	}

}
