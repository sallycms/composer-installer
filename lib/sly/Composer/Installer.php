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

class Installer extends LibraryInstaller {
	public static $supported = array('sallycms-addon', 'sallycms-asset', 'sallycms-app');

	public function getInstallPath(PackageInterface $package) {
		return self::getPkgPath($package);
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

	public function supports($packageType) {
		return in_array($packageType, self::$supported);
	}

	public static function onPostPkgInstall(Event $event) {
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

			if (is_dir($srcDir)) {
				$io = $event->getIO();
				$io->write('    Installing develop files...', false);

				Helper::copyTo($srcDir, $dstDir, false);

				$io->write(' done.');
			}
		}
	}
}
