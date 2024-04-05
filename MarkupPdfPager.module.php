<?php namespace ProcessWire;
// DEBUG disable file compiler for this file
// FileCompiler=0

/*
 * MarkupPdfPager module
 * 
 * Provides embedded PDF rendering with optional search.
 * 
 * Copyright 2018-2021 Tamas Meszaros <mt+git@webit.hu>
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
 
class MarkupPdfPager extends WireData implements Module {
  private $assetsURL;

  /**
   * Returns a HTML sniplet that can be used to insert a PDF page renderer to a page
   * 
   * @param $pdfPage ProcessWire Page object (the page contains the PDF file field)
   * @param $searchURL URL to the search page, empty if not supported
   * @param $jumpPage jump to this page upon displaying
   * @param $scale scale up/down the PDF viewport
   * @returns HTML sniplet to insert to a page
   * The method also alters elements of the $taskData array.
   */
  public function renderPdfCanvas($pdfPage, $searchURL = '', $jumpPage = 1, $scale = 1.2) {
    $this->assetsURL = $this->config->urls->siteModules . 'MarkupPdfPager/assets/';

    // check config
    if (!$this->pdf_file_field) return NULL;
    $file=$pdfPage->{$this->pdf_file_field};
    if ($file == NULL) return NULL;
  
    // set the page number offset
    if (!$this->pdf_pageoffset_field || !$pdfPage->{$this->pdf_file_field}->{$this->pdf_pageoffset_field})
      $page_offset = 0;
    else
      $page_offset = $pdfPage->{$this->pdf_file_field}->{$this->pdf_pageoffset_field};

    // include code for marking search results
    if ($searchURL) $markerHtml = $this->renderResultMarkerTool();
    else $markerHtml = '';

    return $markerHtml . "

    <canvas id=\"pdf-canvas\"></canvas>

    <script type=\"text/javascript\">
      var PDF_URL=\"{$file->url}\";
      var DOC_ID=\"{$pdfPage->id}\";
      var search_url=\"{$searchURL}\";
      var page_offset={$page_offset};
      var pageNum = {$jumpPage};
      var base_scale = {$scale};
    </script>

";
  }

  /**
   * Returns a HTML sniplet that can be used to insert the Mark.js tool into a page
   */
  public function renderResultMarkerTool() {
    return "
<script src=\"{$this->assetsURL}js/mark/jquery.mark.js\"></script>
";
  }


  /**
   * Returns a HTML sniplet that can be used to insert the Mark.js tool into a page
   */
  public function renderPdfPagerTool() {
    return "
    <link type=\"text/css\" href=\"{$this->assetsURL}css/module.css\" rel=\"stylesheet\">
    <link type=\"text/css\" href=\"{$this->assetsURL}js/pdf.js/web/text_layer_builder.css\" rel=\"stylesheet\">
    <script src=\"{$this->assetsURL}js/pdf.js/build/pdf.js\"></script>
    <script type=\"text/javascript\" src=\"{$this->assetsURL}js/pdf_pager.js\"></script>
";
  }

}
