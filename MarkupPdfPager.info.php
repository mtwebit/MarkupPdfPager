<?php namespace ProcessWire;
// DEBUG disable file compiler for this file
// FileCompiler=0

/*
 * MarkupPdfPager module information
 * 
 * Page-oriented embedded renderer for PDF documents.
 * 
 * Copyright 2018-2021 Tamas Meszaros <mt+git@webit.hu>
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */

$info = array(
  'title' => 'MarkupPdfPager',
  'version' => '0.9.1',
  'summary' => 'The module provides functions to render PDF documents page by page in HTML.',
  'href' => 'https://github.com/mtwebit/MarkupPdfPager',
  'singular' => true, // contains hooks
  'autoload' => true, // attaches to hooks
  'icon' => 'file-pdf-o', // fontawesome icon
);
