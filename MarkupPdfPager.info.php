<?php namespace ProcessWire;
// DEBUG disable file compiler for this file
// FileCompiler=0

/*
 * MarkupPdfPager module information
 * 
 * Provides embedded rendering, indexing and search for PDF documentds.
 * 
 * Copyright 2018 Tamas Meszaros <mt+git@webit.hu>
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */

$info = array(
  'title' => 'MarkupPdfPager',
  'version' => '0.7.1',
  'summary' => 'The module provides functions to render PDF documents page by page. It also extracts their texts and creates a search index.',
  'href' => 'https://github.com/mtwebit/MarkupPdfPager',
  'singular' => true, // contains hooks
  'autoload' => true, // attaches to hooks
  'icon' => 'file-pdf', // fontawesome icon
);
