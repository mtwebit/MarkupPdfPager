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
// TODO remove unnecessary fields
    return array(
      'pdf_file_field' => 'pdf_file',
      'pdf_pageoffset_field' => 'page_offset',
    );
  }

  public function getInputfields() {
    $inputfields = parent::getInputfields();
    

/********************  Field name settings ****************************/
    $fieldset = $this->wire('modules')->get('InputfieldFieldset');
    $fieldset->label = __('Field setup');

    $f = $this->modules->get('InputfieldSelect');
    $f->attr('name', 'pdf_file_field');
    $f->label = 'Field that contains a PDF file.';
    $f->description = __('For storing a single PDF file in a File field.');
    $f->options = array();
    $f->required = true;
    $f->columnWidth = 50;
    foreach ($this->wire('fields') as $field) {
      if (!$field->type instanceof FieldtypeFile) continue;
      $f->addOption($field->name, $field->label);
    }
    $fieldset->add($f);

    $f = $this->modules->get('InputfieldSelect');
    $f->attr('name', 'pdf_pageoffset_field');
    $f->label = 'Page offset field.';
    $f->description = __('Optional custom field on the File field for storing page number offset.');
    $f->options = array();
    $f->required = false;
    $f->columnWidth = 50;
    foreach ($this->wire('fields') as $field) {
      if (!$field->type instanceof FieldtypeInteger) continue;
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

    return $inputfields;
  }
}
