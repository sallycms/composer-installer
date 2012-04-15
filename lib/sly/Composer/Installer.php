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
		return 'sally/addons/'.str_replace('/', '-', $package->getName());
	}

	public function supports($packageType) {
		return 'sallycms-addon' === $packageType;
	}
}
