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

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Helper {
	/**
	 * @param  string $path
	 * @param  int    $perm
	 * @param  bool   $throwException
	 * @return mixed
	 */
	public static function createDirectory($path, $perm = 0777) {
		$path = self::normalize($path);

		if (!is_dir($path)) {
			$base = dirname($path);

			// create base path
			if (!self::createDirectory($base, $perm)) return false;

			// try to create the directory
			if (@mkdir($path)) {
				@chmod($path, $perm);
				clearstatcache();
			}
			else {
				trigger_error('mkdir('.$path.') failed.', E_USER_WARNING);
				return false;
			}
		}

		return $path;
	}

	/**
	 * @param  string  $dir
	 * @param  boolean $files
	 * @param  boolean $directories
	 * @param  boolean $dotFiles
	 * @param  boolean $absolute
	 * @param  string  $sortFunction
	 * @return array
	 */
	public static function listPlain($dir, $files = true, $directories = true, $dotFiles = false, $absolute = false, $sortFunction = 'natsort') {
		if (!$files && !$directories) return array();
		if (!is_dir($dir)) return false;

		$handle = @opendir($dir);
		$list   = array();

		if (!$handle) {
			return false;
		}

		while ($file = readdir($handle)) {
			if ($file == '.' || $file == '..') continue;
			if ($file[0] == '.' && !$dotFiles) continue;

			$abs = self::join($dir, $file);

			if (is_dir($abs)) {
				if ($directories) $list[] = $absolute ? $abs : $file;
			}
			else {
				if ($files) $list[] = $absolute ? $abs : $file;
			}
		}

		closedir($handle);

		if (!empty($sortFunction)) {
			$sortFunction($list);
			$list = array_values($list);
		}

		return $list;
	}

	/**
	 * @param  string  $dir
	 * @param  boolean $dotFiles
	 * @param  boolean $absolute
	 * @return array
	 */
	public static function listRecursive($dir, $dotFiles = false, $absolute = false) {
		if (!is_dir($dir)) return false;

		// use the realpath of the directory to normalize the filenames
		$iterator = new RecursiveDirectoryIterator(realpath($dir));
		$iterator = new RecursiveIteratorIterator($iterator);
		$list     = array();
		$baselen  = strlen(rtrim(realpath($dir), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);

		foreach ($iterator as $filename => $fileInfo) {
			if ($iterator->isDot()) continue;

			if ($dotFiles && $absolute) {
				$list[] = $filename;
				continue;
			}

			// use the fast way to find dotfiles
			if (!$dotFiles && substr_count($filename, DIRECTORY_SEPARATOR.'.') > 0) {
				continue;
			}

			$list[] = $absolute ? $filename : substr($filename, $baselen);
		}

		natcasesort($list);
		return array_values($list);
	}

	/**
	 * @param  string  $dir
	 * @param  boolean $force
	 * @return boolean
	 */
	public static function delete($dir, $force = false) {
		if (!is_dir($dir)) return true;

		$empty = count(self::listPlain($dir, true, true, true, false, null)) === 0;

		if (!$empty && (!$force || !self::deleteFiles($dir, true))) {
			return false;
		}

		$retval = rmdir($dir);
		clearstatcache();

		return $retval;
	}

	/**
	 * @param  string  $dir
	 * @param  boolean $recursive
	 * @return boolean
	 */
	public static function deleteFiles($dir, $recursive = false) {
		if (is_dir($dir)) {
			$level = error_reporting(0);

			if ($recursive) {
				// don't use listRecursive() because CHILD_FIRST matters
				$iterator = new RecursiveDirectoryIterator($dir);
				$iterator = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

				foreach ($iterator as $file) {
					if ($file->isDir()) {
						rmdir($file->getPathname());
					}
					else {
						unlink($file->getPathname());
					}
				}
			}
			else {
				$files = self::listPlain($dir, true, $recursive, true, true, null);

				if ($files) {
					array_map('unlink', $files);
				}
			}

			error_reporting($level);

			if (count(self::listPlain($dir, true, false, true, true, null)) > 0) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Copies the content of one directory to another directory.
	 *
	 * @param  string $source
	 * @param  string $destination
	 * @return boolean
	 */
	public static function copyTo($source, $destination, $overwrite = true) {
		if (!is_dir($source)) return false;

		$destination = self::createDirectory($destination);
		if ($destination === false) return false;

		$files = self::listPlain($source, true, true, false, false);

		foreach ($files as $file) {
			$src = self::join($source, $file);
			$dst = self::join($destination, $file);

			if (is_dir($src)) {
				$dst = self::createDirectory($dst);
				if ($dst === false) return false;

				$recursion = self::copyTo($src, $dst, $overwrite);
				if ($recursion === false) return false;
			}
			elseif (is_file($src) && (!file_exists($dst) || $overwrite)) {
				if (copy($src, $dst)) chmod($dst, 0664);
				else return false;
			}
		}

		return true;
	}

	/**
	 * @param  string $paths
	 * @return string
	 */
	public static function join($paths) {
		$paths = func_get_args();
		$isAbs = $paths[0][0] == '/' || $paths[0][0] == '\\';

		foreach ($paths as $idx => &$path) {
			if ($path === null || $path === false || $path === '') {
				unset($paths[$idx]);
				continue;
			}

			$path = trim(self::normalize($path), DIRECTORY_SEPARATOR);
		}

		return ($isAbs ? DIRECTORY_SEPARATOR : '').implode(DIRECTORY_SEPARATOR, $paths);
	}

	/**
	 * @param  string $path
	 * @return string
	 */
	public static function normalize($path) {
		static $s = DIRECTORY_SEPARATOR;
		static $p = null;

		if ($p === null) {
			$p = '#'.preg_quote($s, '#').'+#';
		}

		$path  = str_replace(array('\\', '/'), $s, $path);
		$path  = rtrim($path, $s);

		if (strpos($path, $s.$s) === false) {
			return $path;
		}

		$parts = preg_split($p, $path);
		return implode($s, $parts);
	}
}
