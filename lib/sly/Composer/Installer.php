<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Script\Event;
use Composer\Installer\PackageEvent;

class Installer extends LibraryInstaller {
	public static $supported = array('sallycms-addon', 'sallycms-asset', 'sallycms-app');

	/**
	 * Decides if the installer supports the given type
	 *
	 * @param  string $packageType
	 * @return bool
	 */
	public function supports($packageType) {
		return in_array($packageType, self::$supported);
	}

	/**
	 * Returns the installation path of a package
	 *
	 * @param  PackageInterface $package
	 * @return string           path
	 */
	public function getInstallPath(PackageInterface $package) {
		return self::getPkgPath($package);
	}

	/**
	 * Make sure the sally/ directory is protected against HTTP access in Sally 0.9+
	 */
	public static function onPostInstall(Event $event) {
		$rootVersion = $event->getComposer()->getPackage()->getVersion();

		if (version_compare($rootVersion, '0.9.0', '>=')) {
			$htaccess = 'sally/.htaccess';
			$content  = "order deny,allow\ndeny from all";

			if (!file_exists($htaccess)) {
				$io = $event->getIO();
				$io->write('<info>Securing sally/ against HTTP access</info>', false);
				$io->write(' <warning>(If you do not use Apache, make sure nobody can access sally/ via HTTP!)</warning>');
				file_put_contents($htaccess, $content);
			}
		}
	}

	public static function onPostPkgInstall(PackageEvent $event) {
		$op   = $event->getOperation();
		$pkg  = $op->getJobType() === 'install' ? $op->getPackage() : $op->getTargetPackage();
		$type = $pkg->getType();

		if (!in_array($type, self::$supported)) {
			return;
		}

		$pkgDir = self::getPkgPath($pkg);

		// re-initializing assets is only required for <0.9 systems

		$rootVersion = $event->getComposer()->getPackage()->getVersion();

		if (version_compare($rootVersion, '0.9.0', '<')) {
			$name   = $pkg->getName();
			$srcDir = $pkgDir.'/assets';
			$dstDir = 'data/dyn/public/'.$name;

			if (is_dir($srcDir)) {
				$io = $event->getIO();
				$io->write('    Updating assets...', false);

				Helper::deleteFiles($dstDir, true);
				Helper::copyTo($srcDir, $dstDir);

				$io->write(' done.');

				// remove affected asset-cache directories
				$io->write('    Wiping asset cache...', false);

				$patterns = array(
					'public/gzip',    'public/plain',    'public/deflate',
					'protected/gzip', 'protected/plain', 'protected/deflate',
				);

				foreach ($patterns as $pattern) {
					$cacheDir = "data/dyn/public/sally/static-cache/$pattern/$dstDir";
					Helper::deleteFiles($cacheDir, true);
				}

				$io->write(' done.');
				$io->write('');
			}
		}

		if ($pkg->getType() === 'sallycms-addon') {
			$srcDir = $pkgDir.'/develop';
			$dstDir = 'develop';

			$extra = $event->getComposer()->getPackage()->getExtra();
			$sallycms = is_array($extra) && array_key_exists('sallycms', $extra) && is_array($extra['sallycms']) ? $extra['sallycms'] : array();
			$installDevelopFiles = array_key_exists('install-develop-files', $sallycms) ? $sallycms['install-develop-files'] : true;

			if (is_dir($srcDir) && $installDevelopFiles) {
				$io = $event->getIO();
				$io->write('    Installing develop files...', false);

				Helper::copyTo($srcDir, $dstDir, false);

				$io->write(' done.');
			}
		}
	}

	protected static function getPkgPath(PackageInterface $package) {
		switch ($package->getType()) {
			case 'sallycms-addon':
				$path = 'sally/addons/'.$package->getName();
				break;

			case 'sallycms-asset':
				$path = 'sally/assets/'.$package->getName();
				break;

			case 'sallycms-app':
				$parts = explode('/', $package->getName());
				$path  = 'sally/'.end($parts);
		}

		return $path;
	}
}
