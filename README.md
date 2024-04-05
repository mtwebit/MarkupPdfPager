# MarkupPdfPager
An embedded, page-oriented PDF rendering, transformation and indexing tool for Processwire.

# Status, known bugs
*** This is a half-finished module. ***
* PDF rendering and paging works.
* Indexing and search are disabled.

# Installation
After installing this module you need to install two Javascript libraries.
* [Mozilla PDF.js v1](https://github.com/mozilla/pdf.js/releases/download/v1.10.100/pdfjs-1.10.100-dist.zip) into assets/js/pdf.js/
* [Mark.js](https://github.com/julmot/mark.js) into assets/js/mark


# How to use the module
Create...
* a File field to store a single PDF file.
* (optionally) an Integer field to store page offset info and add this to the PDF File field.
* a template and add the PDF File field to it.

Install the module and set the file and page offset fields.

Use the following code in your template to render the PDF document:
```php
// optional page number to jump to
$p = $sanitizer->text($input->get->p);
if (!$p) $p = 1;

if (strlen($page->pdf_file)) {
	$content = $pdfPager->renderPdfCanvas($page, '', $p) . '
<nav class="navbar nav navbar-light bg-faded hidden-sm-down navbar-fixed-bottom">
<div class="pull-xs-right">
  <button id="prev" class="btn btn-primary btn-sm"><i class="fa fa-chevron-left"></i></button>
  <input type="text" id="page_num" class="nav-item form-control-paintext form-control-sm" value="1" size="3" />
  / <span id="page_count"></span>.
  <button id="next" class="btn btn-primary btn-sm"><i class="fa fa-chevron-right"></i></button>
</div>
</nav>
';

  $content .= $pdfPager->renderPdfPagerTool();
}
```
The page navigation shown above uses Bootstrap v4. The "prev", "next" and "page_num" IDs can be used to control the PDF viewer.
