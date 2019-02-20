<?php namespace ProcessWire;
// DEBUG disable file compiler for this file
// FileCompiler=0

/*
 * MarkupPdfPager module - configuration
 * 
 * Provides embedded rendering, indexing and search for PDF documentds.
 * 
 * Copyright 2018 Tamas Meszaros <mt+git@webit.hu>
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */

class MarkupPdfPagerConfig extends ModuleConfig {

  public function getDefaults() {
    return array(
      'pdf_file_field' => 'pdf_file',
      'pdf_pageindex_field' => 'pdf_page_texts',
      'pdfseparate' => '/usr/bin/pdfseparate',
      'pdftotext' => '/usr/bin/pdftotext',
      'reindex' => 0,
      'indexmissing' => 0,
    );
  }

  public function getInputfields() {
    $inputfields = parent::getInputfields();

    $fieldset = $this->wire('modules')->get('InputfieldFieldset');
    $fieldset->label = __('Requirements');

    if (!$this->modules->isInstalled('Tasker')) {
      $f = $this->modules->get('InputfieldMarkup');
      $this->message('Tasker module is missing.', Notice::warning);
      $f->value = '<p>Tasker module is missing. It can help in processing large PDF documents.</p>';
      $f->columnWidth = 50;
      $fieldset->add($f);
    }

    $inputfields->add($fieldset);


/********************  Field name settings ****************************/
    $fieldset = $this->wire('modules')->get("InputfieldFieldset");
    $fieldset->label = __("Field setup");

    $f = $this->modules->get('InputfieldSelect');
    $f->attr('name', 'pdf_file_field');
    $f->label = 'Field that contains PDF files.';
    $f->description = __('Use the FieldtypePDF module to create the field.');
    $f->options = array();
    $f->required = true;
    $f->columnWidth = 50;
    foreach ($this->wire('fields') as $field) {
      if (!$field->type instanceof FieldtypeFile) continue;
      $f->addOption($field->name, $field->label);
    }
    $fieldset->add($f);

    $f = $this->modules->get('InputfieldSelect');
    $f->attr('name', 'pdf_pageindex_field');
    $f->label = 'Field that contains the search index.';
    $f->description = __('Create a hidden repeater field that contains a page_number (Integer) and a page_text (Textarea).');
    $f->options = array();
    $f->required = true;
    $f->columnWidth = 50;
    foreach ($this->wire('fields') as $field) {
      if (!$field->type instanceof FieldtypeRepeater) continue;
      $f->addOption($field->name, $field->label);
    }
    $fieldset->add($f);
    $inputfields->add($fieldset);

/********************  Executables ****************************/
    $fieldset = $this->wire('modules')->get("InputfieldFieldset");
    $fieldset->label = __("PDF conversion tools");

    $f = $this->modules->get('InputfieldText');
    $f->attr('name', 'pdfseparate');
    $f->label = __('PDFseparate executable.');
    $f->description = __('Location of the executable');
    $f->required = true;
    $f->columnWidth = 50;
    $fieldset->add($f);

    $f = $this->modules->get('InputfieldText');
    $f->attr('name', 'pdftotext');
    $f->label = __('PDFtotext executable.');
    $f->description = __('Location of the executable');
    $f->required = true;
    $f->columnWidth = 50;
    $fieldset->add($f);
    $inputfields->add($fieldset);

/********************  Other options ****************************/
    $fieldset = $this->wire('modules')->get("InputfieldFieldset");
    $fieldset->label = __("Run-time options");

    $f = $this->modules->get('InputfieldCheckbox');
    $f->attr('name', 'reindex');
    $f->label = __('Reindex all pages');
    $f->description = __('Clear existing page indices and process all PDF fields again.');
    $f->columnWidth = 50;
    $fieldset->add($f);

    $f = $this->modules->get('InputfieldCheckbox');
    $f->attr('name', 'indexmissing');
    $f->label = __('Index all missing documents');
    $f->description = __('Create missing indices for PDF documents.');
    $f->columnWidth = 50;
    $fieldset->add($f);

    // TODO conversion options

    $inputfields->add($fieldset);

    return $inputfields;
  }
}
