<?php namespace ProcessWire;
// DEBUG disable file compiler for this file
// FileCompiler=0

/*
 * MarkupPdfPager module
 * 
 * Provides embedded rendering, indexing and search for PDF documentds.
 * 
 * Copyright 2018 Tamas Meszaros <mt+git@webit.hu>
 * This file licensed under Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
 
class MarkupPdfPager extends WireData implements Module {
  private $redirectUrl = ''; // used for temporaly redirect on page save
  private $assetsURL;
  private $solr_client;

/***********************************************************************
 * MODULE SETUP
 **********************************************************************/

  /**
   * Called only when this module is installed
   * 
   */
  public function ___install() {
  }


  /**
   * Called only when this module is uninstalled
   * 
   */
  public function ___uninstall() {
  }


  /**
   * Initialization
   * 
   * This function attaches a hook for page save and decodes module options.
   */
  public function init() {
    $this->assetsURL = $this->config->urls->siteModules . 'MarkupPdfPager/assets/';
    // check config
    if (!$this->pdf_file_field || !$this->pdf_pageindex_field
        || !$this->pdfseparate || !$this->pdftotext ) {
      $this->error("The module configuration is invalid.");
      return;
    }

    if (!is_executable($this->pdftotext) || !is_executable($this->pdftotext)) {
      $this->error("{$this->pdftotext} is missing.");
      return;
    }

    if (!is_executable($this->pdfseparate) || !is_executable($this->pdfseparate)) {
      $this->error("{$this->pdfseparate} is missing.");
      return;
    }

    if ($this->search_engine == 'solr' && !$this->solr_connect()) {
      return;
    }

    if (false && $this->reindex && $this->indexAll(true /* reindex all */)) {
      // clear the index all checkbox if indexAll() is successful
      wire('modules')->saveConfig('MarkupPdfPager', 'reindex', 0);
    }

    if (false && $this->indexmissing && $this->indexAll()) {
      // clear the index all checkbox if indexAll() is successful
      wire('modules')->saveConfig('MarkupPdfPager', 'indexmissing', 0);
    }

    // Install conditional hooks
    // Note: PW < 3.0.62 has a bug and needs manual fix for conditional hooks:
    // https://github.com/processwire/processwire-issues/issues/261
    // hook after page save to process changes of PDF file fields
    $this->addHookAfter('Page::changed('.$this->pdf_file_field.')', $this, 'handleFileChange');
  }



/***********************************************************************
 * HOOKS
 **********************************************************************/

  /**
   * Hook that creates a task to process the sources
   * Note: it is called several times when the change occurs.
   */
  public function handleFileChange(HookEvent $event) {
    // return when we could not detect a real change
    if (! $event->arguments(1) instanceOf Pagefiles) return;
    $pdfPage = $event->object;

    $this->message('PDF file has changed on "'.$pdfPage->title.'"', Notice::debug);

    // create the necessary tasks and add them to the page after it is saved.
    // TODO enable Tasker
    if ($this->modules->isInstalled('Tasker')) {
      $event->addHookAfter("Pages::saved($pdfPage)",
        function($event) use($pdfPage) {
          // reindex the pdf page if it is changed
          $this->createTasksOnPageSave($pdfPage, true);
          $event->removeHook(null);
        });
    } else {
      // create the text index now
      $this->index($pdfPage);
    }
  }

  /**
   * Create necessary tasks when the page is ready to be saved
   * 
   * @param $pdfPage ProcessWire Page object
   * @param $autoStart true: Start the task now if it is possible, Task: add the new task as a follow-up task
   */
  public function createTasksOnPageSave($pdfPage, $autoStart = false) {
    // constructing tasks
    // these could be long running progs so we can't execute them right now
    // Tasker module is here to help
    $tasker = $this->modules->get('Tasker');

    $data = array(); // task data
    $indexTask = $tasker->createTask(__CLASS__, 'index', $pdfPage, 'Process the PDF file @ '.$pdfPage->title, $data);
    if ($indexTask == NULL) return false; // tasker failed to add a task

    $this->message("Created a task to index the PDF file on page '{$pdfPage->title}'.", Notice::debug);

    // add this new task as a follow-up task to a previous one
    // this is used by the IndexAll() method to create a chain of indexing tasks
    if ($autoStart instanceof Page) {
      $tasker->addNextTask($autoStart, $indexTask);
    } else if ($autoStart === true) {
      $tasker->activateTask($indexTask); // activate the task now
    }

    // if TaskedAdmin is installed, redirect to its admin page for immediate task execution
    if ($autoStart === true && $this->modules->isInstalled('TaskerAdmin')
                   && $this->modules->get('TaskerAdmin')->autoStart) {
      $this->redirectUrl = $taskerAdmin->adminUrl.'?id='.$indexTask->id.'&cmd=run';
      // add a temporary hook to redirect to TaskerAdmin's monitoring page after saving the current page
      $this->pages->addHookBefore('ProcessPageEdit::processSaveRedirect', $this, 'runTasks');
    }

    return $indexTask;
  }

  /**
   * Hook that redirects the user to the tasker admin page
   */
  public function runTasks(HookEvent $event) {
    if ($this->redirectUrl != '') {
      // redirect on page save
      $event->arguments = array($this->redirectUrl);
      $this->redirectUrl = '';
    }
    // redirect is done or not needed, remove this hook
    $event->removeHook(null);
  }


/***********************************************************************
 * INDEXING AND SEARCH FUNCTIONS
 **********************************************************************/

  /**
   * Connect to a Solr server
   */
  public function solr_connect() {
    return true;

    $options = array(
      'hostname' => $this->solr_host,
      'port'     => $this->solr_port,
      'path'     => $this->solr_path,
      'wt'       => 'xml',
    );

    try {
      $this->solr_client = new \SolrClient($options);
      $solrres = $this->solr_client->ping();
    } catch (Exception $e) {
      $this->error("ERROR: could not connect to the Solr server at {$this->solr_host}: " . $e->getMessage());
      return false;
    }
    $this->message("Solr indexer successfully connected to {$this->solr_host}: " . $solrres->getRawResponse());
    return true;
  }


  /**
   * Index a PDF document.
   * 
   * @param $pdfPage ProcessWire Page object (the page contains the PDF file field)
   * @param $taskData task data assoc array
   * @param $params runtime paramteres, e.g. timeout, dryrun, estimation and task object
   * @returns false on error, a result message on success
   * The method also alters elements of the $taskData array.
   */
  public function index($pdfPage, &$taskData = array(), $params = NULL) {

    if ($params != NULL && $this->modules->isInstalled('Tasker')) {
      // get a reference to Tasker and the task
      $tasker = wire('modules')->get('Tasker');
      $task = $params['task'];
    } else $task = false;

    // get the first file
    $file=$pdfPage->{$this->pdf_file_field}->first();
    if ($file==NULL) {
      $this->error("ERROR: input file is no longer present on Page '{$pdfPage->title}'.");
      if ($task) {
        $this->message("Removing task '{$task->title}'.");
        $task->trash();
      }
      return false;
    }

    // initialize task data if this is the first invocation and we're running as a task
    if ($task && $taskData['records_processed'] == 0) {
      // estimate the number of processable records
      $taskData['max_records'] = 1;
      $taskData['records_processed'] = 0;
      $taskData['task_done'] = 0;
      $taskData['milestone'] = 20;
    }

    $this->message("Processing PDF file {$file->filename}.");
    // TODO $this->message("Processing PDF file {$file->name}.", Notice::debug);

    if (method_exists($this, 'index_'.$this->search_engine)) {
      $method = 'index_'.$this->search_engine;
      return $this->$method($pdfPage, $file, $task, $taskData, $params);
    }

    $this->error("Unsupported indexing service '{$this->search_engine}'.");
    return false;
  }

  /**
   * Index a PDF document using an internal method
   * 
   * @param $pdfPage ProcessWire Page object (the page contains the PDF file field)
   * @param $file the PDF file to process
   * @param $task the task object or false if Tasker is not available
   * @param $taskData task data assoc array
   * @param $params runtime paramteres, e.g. timeout, dryrun, estimation and task object
   * @returns false on error, a result message on success
   * The method also alters elements of the $taskData array.
   */
  private function index_solr($pdfPage, $file, $task, &$taskData, $params) {
    $this->message("Solr indexing service activated on page '{$pdfPage}' for file '{$file->name}'.");
    $mime = mime_content_type($file->filename);
    if ($mime === false) {
      $this->error("Solr indexing failed. Could not determine mime type.");
      return false;
    }


// TODO Index each page separately to be able to get page numbers








    // TODO For optimum performance when loading many documents, don’t call the commit command until you are done.
    $solr_options = '&commit=true&stored=true';
    $cfile = new \CURLFile($file->filename, $mime, $file->name);
    $pname = urlencode($file->name);
    $ch = curl_init("http://{$this->solr_host}:{$this->solr_port}/{$this->solr_path}/update/extract?literal.id={$pdfPage->id}&literal.name={$pname}{$solr_options}");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array('myfile' => $cfile));
    $this->message("Sending CURL request to ".curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), Notice::debug);
    $result= curl_exec ($ch);
    if ($result === false) {
      $this->error("Solr indexing failed. ".curl_error($ch));
      curl_close($ch);
      return false;
    }
    $this->message("Solr indexing returned [".curl_getinfo($ch, CURLINFO_RESPONSE_CODE)."] {$result}.", Notice::debug);
    $taskData['task_done'] = 1;
    $this->message($file->name.' has been processed.');
    curl_close($ch);
    return true;
  }

  /**
   * Search PDF documents on selectable pages using a text selector and return an assoc array of page IDs and repeater items.
   * 
   * @param $pageSelector - PW selector to find Pages
   * @param $textSelector - a selector operator and a selector value to match pdf_pageindex_field repeater items
   * @return array( PageIDs array, Solr search result object)
   */
  public function search_solr($pageSelector, $textSelector) {
    $this->message("Solr search started with {$textSelector}.", Notice::debug);
    if ($textSelector == '') return array($this->pages->findIDs($pageSelector), false);
    $filter = '';
    if ($pageSelector != '') {
       $pageIDs = $this->pages->findIDs($pageSelector);
       if (!count($pageIDs)) return array(false, false);
       $filter .= '&fq='.urlencode('id:'.implode(' OR id:', $pageIDs));
    }
    $hightlight = '&hightlightMultiTerm=true&hl.simple.post=<%2Fu>&hl.simple.pre=<u>&hl.fl=_text_&hl=on&q=_text_:'.urlencode($textSelector);
    $ch = curl_init("http://{$this->solr_host}:{$this->solr_port}/{$this->solr_path}/select/?fl=id&q=_text_%3A".urlencode($textSelector).'&'.$filter.$hightlight);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $this->message("Sending CURL request to ".curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), Notice::debug);
    $json_result = curl_exec ($ch);
    if ($json_result === false) {
      $this->error("Solr search failed. ".curl_error($ch));
      echo "Solr search failed. ".curl_error($ch);
      curl_close($ch);
      return array(false, false);
    }
    $this->message("Solr search returned [".curl_getinfo($ch, CURLINFO_RESPONSE_CODE)."] {$json_result}.", Notice::debug);
    $result = json_decode($json_result, true); // TODO check
    curl_close($ch);
    $page_ids = array();
    foreach($result['response']['docs'] as $res) $page_ids[] = $res['id'];
    $this->log->save('search', "Solr search Pages={$pageSelector} Text={$textSelector} Result: ".var_export($result, true));
    return array($page_ids, $result);
  }

  /**
   * Index a PDF document using an internal method
   * 
   * @param $pdfPage ProcessWire Page object (the page contains the PDF file field)
   * @param $task the task object or false if Tasker is not available
   * @param $taskData task data assoc array
   * @param $params runtime paramteres, e.g. timeout, dryrun, estimation and task object
   * @returns false on error, a result message on success
   * The method also alters elements of the $taskData array.
   */
  private function index_internal($pdfPage, $task, &$taskData, $params) {
    // create the pageindex field if it does not exists
    if (!$pdfPage->{$this->pdf_pageindex_field}) {
      // save() should create the missing repeater field
      $pdfPage->save();
      $pdfPage = $this->wire('pages')->getById($pdfPage->id, array(
        'getOne' => true, // return a Page instead of a PageArray
      ));
    } else if ($pdfPage->{$this->pdf_pageindex_field}->count()) {
      // remove the old index if it exists
      $pdfPage->{$this->pdf_pageindex_field}->removeAll();
    }

    $newItems = array();
    $workDir = $this->config->paths->tmp.'index_'.$pdfPage->id.'/';
    mkdir($workDir, 0755, true);

    // extract PDF pages
    $command = $this->pdfseparate . ' ' . $file->filename . ' ' . $workDir . 'page-%d.pdf 2>&1';
    $this->message("Extracting PDF pages using {$command}.", Notice::debug);
    if ($task) $tasker->saveProgress($task, $taskData, false, false);
    exec($command, $exec_output, $exec_status);
    if ($exec_status != 0) {
      $this->error("ERROR: Could not extract pages from '{$file->filename}'.");
      foreach ($exec_output as $exec_line) $this->error($exec_line);
      exec('/bin/rm -rf '.$workDir);
      return false;
    }
    unset($exec_output);

    $pagefiles = scandir($workDir);
    // sort the files using page numbers
    natsort($pagefiles);

    if ($task) {
      $taskData['max_records'] = count($pagefiles) - 2; // . and ..
      $this->message("Processing {$taskData['max_records']} PDF pages from {$file->filename}.", Notice::debug);
      $tasker->saveProgress($task, $taskData, false, false);
    }

    foreach($pagefiles as $pagefile) {
      if ($pagefile == '.' || $pagefile == '..') continue;
      list ($pageNum) = sscanf($pagefile, "page-%d.pdf");
      $this->message("Extracting texts from pagefile {$pagefile}.", Notice::debug);
      // TODO keep layout?
      exec($this->pdftotext . ' -enc UTF-8 -layout ' . $workDir . $pagefile . ' ' . $workDir . 'pagetext.txt 2>&1', $exec_output, $exec_status);
      // exec($this->pdftotext . ' -enc UTF-8 ' . $workDir . $pagefile . ' ' . $workDir . 'pagetext.txt', $exec_output, $exec_status);
      if ($exec_status != 0) {
        $this->error("ERROR: Could not extract text from '{$file->filename}' on page {$pageNum}.");
        foreach ($exec_output as $exec_line) $this->error($exec_line);
      }
      unset($exec_output);

      if (is_file($workDir . 'pagetext.txt')) {
        // create new repeater item
        try { // sometimes the DB goes away doing this
          $item = $pdfPage->{$this->pdf_pageindex_field}->getNewItem();
          $item->page_number = $pageNum;
          // read the text and remove multiple whitespaces
          $item->page_text = $ro = preg_replace('/\s+/', ' ', file_get_contents($workDir . 'pagetext.txt'));
          $item->save();
          $newItems[] = $item;
        } catch (Exception $e) {
          $this->error("ERROR: could not add text to the index on page {$pageNum}. ".$e->getMessage());
        }
        unlink($workDir . 'pagetext.txt');
      }
      unlink($workDir . $pagefile);
      $taskData['records_processed']++;
      // save task progress if a milestone was reached
      if ($task && $tasker->saveProgressAtMilestone($task, $taskData)) {
        // set the next milestone
        $taskData['milestone'] = $taskData['records_processed'] + 20;
      }
    }
    if (count($newItems)) {
      $pdfPage->{$this->pdf_pageindex_field}->add($newItems);
      $this->message("Processed ".count($newItems)." pages in {$pdfPage->title}.", Notice::debug);
    }

    $pdfPage->save();

    exec('/bin/rm -rf '.$workDir);

    $taskData['task_done'] = 1;
    $this->message($file->name.' has been processed.');

    return true;
  }


  /**
   * Index all PDF documents using Tasker.
   * Indexing is a heavy task so we process the files one by one
   *
   * @param bool $reindex true: clear all existing indices, false: create missing indices
   * @returns false on error (e.g. Tasker is not available), true if all tasks have been created
   */
  public function indexAll($reindex = false) {
    if (!$this->modules->isInstalled('Tasker')) {
      $this->message('ERROR: Tasker is required to reindex all PDF documents.');
      return false;
    }
    if ($reindex) {
      $this->message('Reindexing all PDF files...', Notice::debug);
      $selector = '';
    } else {
      $this->message('Indexing new PDF files...', Notice::debug);
      // count = 0 won't work
      $selector = '!'.$this->pdf_pageindex_field.".count>0";
    }
    $pdfPages = $this->findPages($selector);
    $this->message("Found {$pdfPages->count()} page(s) to index using {$selector}.");

    $prevTask = true; // start the first task
    foreach ($pdfPages as $pdfPage) {
      // the selector above does not work well
      // let's check the repeater field again:
      if (!$reindex && $pdfPage->{$this->pdf_pageindex_field} && $pdfPage->{$this->pdf_pageindex_field}->count() > 0) continue;
      // $this->message("<a href='/iti/admin/page/edit/?id={$pdfPage->id}'>{$pdfPage->title}</a> {$pdfPage->{$this->pdf_pageindex_field}->count()}.", Notice::allowMarkup);
      $prevTask = $this->createTasksOnPageSave($pdfPage, $prevTask);
      if ($prevTask === false) return false;
    }
    return true;
}


  /**
   * Search the PDF content of pages and return the number of matching pages
   * 
   * @param $pageSelector - PW selector to find Pages
   * @param $textSelector - a selector operator and a selector value to match pdf_pageindex_field repeater items
   * @return WireArray of Pages matching the selectors
   */
  public function countPages($pageSelector='', $textSelector='') {
    // Repeater i﻿tems are stored under﻿ the admi﻿n tre﻿e, ignore this fact while counting them
    return $this->pages->count('check_access=0, template=repeater_'.$this->pdf_pageindex_field);
    if ($pageSelector == '') $pageSelector = $this->pdf_file_field."!=''";
    else $pageSelector .= ','.$this->pdf_file_field."!=''";
    if ($textSelector=='') return $this->pages->count($pageSelector);
    return $this->pages->count($pageSelector . ',' . $this->pdf_pageindex_field . '.page_text' . $textSelector);
  }

  /**
   * Search PDF documents on selectable pages using a text selector and return an assoc array of page IDs and repeater items.
   * 
   * @param $pageSelector - PW selector to find Pages
   * @param $textSelector - a selector operator and a selector value to match pdf_pageindex_field repeater items
   * @return associative array of pageID -> array of page_numbers
   */
  public function findPageItems($pageSelector, $textSelector) {
    $res = array();
    $matchingPages = $this->findPages($pageSelector, $textSelector);
    foreach ($matchingPages as $p) {
      $pageRepeaters = $p->{$this->pdf_pageindex_field}->find('page_text'.$textSelector);
      $pageNums = array();
      foreach ($pageRepeaters as $item) {
        $pageNums[] = $item->page_number;
      }
      if (count($pageNums)) $res[$p->id] = $pageNums;
    }
    return $res;
  }

  /**
   * Search the PDF content of pages
   * 
   * @param $pageSelector - PW selector to find Pages
   * @param $textSelector - a selector operator and a selector value to match pdf_pageindex_field repeater items
   * @return WireArray of Pages matching the selectors
   */
  public function findPages($pageSelector='', $textSelector='') {
    if ($pageSelector == '') $pageSelector = $this->pdf_file_field."!=''";
    else $pageSelector .= ','.$this->pdf_file_field."!=''";
    if ($textSelector=='') return $this->pages->find($pageSelector);
    return $this->pages->find($pageSelector . ',' . $this->pdf_pageindex_field . '.page_text' . $textSelector);
  }

  /**
   * Search the PDF content of pages and return the first text excerpt that matches the query
   * 
   * @param $pdfPage - a PW Page that contains a pdf doc and a pageindex repeater
   * @param $textSelector - a selector operator and a selector value to match pdf_pageindex_field repeater items
   * @return WireArray of Pages matching the selectors
   */
  public function getExcerpts($pdfPage, $textSelector='') {
    if (!$pdfPage instanceof Page) {
      $this->error('ERROR: MarkupPdfPager::getExcerpts requires a page object');
      return '';
    }

    if (!$pdfPage->{$this->pdf_pageindex_field} || $pdfPage->{$this->pdf_pageindex_field}->count() < 1) {
      $this->error("ERROR: {$pdfPage->title} has an empty text index.");
      return '';
    }

    // find matching text repeater fields
    $pageTextFields = $pdfPage->{$this->pdf_pageindex_field}->find('page_text'.$textSelector);

    // build a regex matcher to find and replace the first match
    // check the selector operator
    if (strpos(' '.$textSelector, '*=') == 1) {
      // exact match required
      $findPattern = '\b('.trim($textSelector, '=~* ').')\b';
    } else {
      // any of the keywords (OR)
      $keywords = explode(' ', trim($textSelector, '=~* '));
      $findPattern = '\b('.implode('|', $keywords).')';
    }

    $keypos = PHP_INT_MAX;

    // find the first occurence of the search pattern
    do {
      $pageTextField = $pageTextFields->shift();
      if ($pageTextField === NULL) return $textSelector.' '.$findPattern;
      $pageText = $pageTextField->page_text;
      if (preg_match('/'.$findPattern.'/ui', $pageText, $matches, PREG_OFFSET_CAPTURE) == 1) {
        $keypos = $matches[1][1];
      }
    } while ($keypos == PHP_INT_MAX && $pageText !== NULL);
    
    if ($keypos === PHP_INT_MAX) return '';

    if ($keypos > 180) $keypos -= 180; else $keypos = 0;
    $match = mb_substr($pageText, $keypos, 300);
    $pos = strpos($match, ' ');
    if ($pos>0) $match = mb_substr($match, $pos);
    $pos = strrpos($match, ' ');
    if ($pos>0) $match = mb_substr($match, 0, $pos);
    
    return preg_replace("/".$findPattern."/ui", "<strong>$0</strong>", htmlspecialchars($match));
  }

/***********************************************************************
 * DISPLAY FUNCTIONS
 **********************************************************************/

  /**
   * Returns a HTML sniplet that can be used to insert a PDF page renderer to a page
   * 
   * @param $pdfPage ProcessWire Page object (the page contains the PDF file field)
   * @returns HTML sniplet to insert to a page
   * The method also alters elements of the $taskData array.
   */
  public function renderPdfPager($pdfPage, $searchURL) {
    $file=$pdfPage->{$this->pdf_file_field};
    if ($file == NULL) return NULL;

    return $this->renderResultMarkerTool() . "
<link type=\"text/css\" href=\"{$this->assetsURL}css/index.css\" rel=\"stylesheet\">
<link type=\"text/css\" href=\"{$this->assetsURL}js/pdf.js/web/text_layer_builder.css\" rel=\"stylesheet\">
<script src=\"{$this->assetsURL}js/pdf.js/build/pdf.js\"></script>

<script type=\"text/javascript\">
  var PDF_URL='{$file->url}';
  var DOC_ID='{$pdfPage->id}';
  var search_url='{$searchURL}';
  var first_page=".($pdfPage->first_page - 1).";
  var pageNum = 1;
</script>
<script type=\"text/javascript\" src=\"{$this->assetsURL}js/pdf_pager.js\"></script>

";
  }

  /**
   * Returns a HTML sniplet that can be used to insert the Mark.js tool into a page
   */
  public function renderResultMarkerTool() {
    return "
<link type=\"text/css\" href=\"{$this->assetsURL}css/mark.css\" rel=\"stylesheet\">
<script src=\"{$this->assetsURL}js/mark/jquery.mark.js\"></script>
";
  }
}
