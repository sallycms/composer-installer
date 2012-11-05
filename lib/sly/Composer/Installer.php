<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace sly\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

class Installer extends LibraryInstaller {
	public function getInstallPath(PackageInterface $package) {
		switch ($package->getType()) {
			case 'sallycms-addon':
				$path = 'sally/addons/'.$package->getName();
				break;
			case 'sallycms-asset':
				$path = 'sally/assets/'.$package->getName();
		}
		return $path;
	}

	public function supports($packageType) {
		$supported = array('sallycms-addon', 'sallycms-asset');
		return in_array($packageType, $supported);
	}
}
