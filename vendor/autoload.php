<?php

/*
 *  wordpress-lti - WordPress module to add LTI support
 *  Copyright (C) 2022  Simon Booth, Stephen P Vickers
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License along
 *  with this program; if not, write to the Free Software Foundation, Inc.,
 *  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 *  Contact: s.p.booth@stir.ac.uk
 */

/**
 * This page provides a function to autoload a class file - it will be automatically overwritten when composer is used.
 */
spl_autoload_register(function ($class) {

  // base directory for the class files
  $base_dir = __DIR__ . DIRECTORY_SEPARATOR;

  // Replace the namespace prefix with the base directory, replace namespace
  // separators with directory separators in the relative class name, append
  // with .php
  $file = $base_dir . preg_replace('/[\\\\\/]/', DIRECTORY_SEPARATOR, $class) . '.php';

  // Update location if class requested is from the ceLTIc\LTI class library
  $file = str_replace(DIRECTORY_SEPARATOR . 'ceLTIc' . DIRECTORY_SEPARATOR . 'LTI' . DIRECTORY_SEPARATOR,
      DIRECTORY_SEPARATOR . 'celtic' . DIRECTORY_SEPARATOR . 'lti' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR, $file);

  // Update location if class requested is from the Firebase\php-jwt class library
  $file = str_replace(DIRECTORY_SEPARATOR . 'Firebase' . DIRECTORY_SEPARATOR . 'JWT' . DIRECTORY_SEPARATOR,
      DIRECTORY_SEPARATOR . 'firebase' . DIRECTORY_SEPARATOR . 'php-jwt' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR,
      $file);

  // if the file exists, require it
  if (file_exists($file)) {
    require($file);
  }
});
?>
