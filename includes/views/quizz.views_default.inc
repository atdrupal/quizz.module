<?php

/*
 * @file
 * Handles default views.
 * Reads views from all the .view.inc files in the views/defaults directory.
 */

/**
 * Implements hook_views_default_views().
 */
function quizz_views_default_views() {
  $files = file_scan_directory(dirname(__FILE__) . '/defaults', '/\.view\.inc/');
  $views = array();
  foreach ($files as $path => $file) {
    $view = require $path;
    $views[$view->name] = $view;
  }
  return $views;
}
