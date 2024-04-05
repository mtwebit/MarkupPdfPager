// If absolute URL from the remote server is provided, configure the CORS
// header on that server.
var container = document.getElementById("container");

// pdfjsLib = window['pdfjs-dist/build/pdf'];


// The workerSrc property shall be specified.
// PDFJS.workerSrc = './build/pdf.worker.js';
PDFJS.disableWorker = true;

var pdfDoc = null,
  div = null,
  pageRendering = false,
  searching = false,
  pageNumPending = null,
  scale = base_scale ,
  pdf_canvas = document.getElementById('pdf-canvas'),
  pdf_ctx = pdf_canvas.getContext('2d');
  pdf_text = "",
  regex = null;
  query_res = [],
  search_index = 0,
  searching = false,
  search_pattern = null,
  num_res = 0,
  text='';


// Get the PDF doc and call the renderer for the given page number
PDFJS.getDocument(PDF_URL).then(function(pdfDoc_) {
  pdfDoc = pdfDoc_;
  if (document.getElementById('page_count')) {
    document.getElementById('page_count').textContent = pdfDoc.numPages + page_offset;
  }

  // This will keep positions of child elements as per our needs
  container.setAttribute("style", "position: relative");

  // do an initial search if query was given
  if (document.getElementById('text') && document.getElementById('text').value !== "") {
    searchDocument();
  } else {
    // Initial/first page rendering
    renderPage(pageNum);  // render the PDF page
    pageRequest(pageNum); // update the page's text content
  }
});



/**
 * Get page info from document, resize canvas accordingly, and render page.
 * @param num Page number.
 */
function renderPage(num) {
  pageRendering = true;
  // Using promise to fetch the page
  pdfDoc.getPage(num).then(function(page) {
    var viewport = page.getViewport(scale);
    pdf_canvas.height = viewport.height;
    pdf_canvas.width = viewport.width;

    // Render PDF page into canvas context
    var renderContext = {
      canvasContext: pdf_ctx,
      viewport: viewport
    };
    page.render(renderContext).then(function() {
      pageRendering = false;
      if (pageNumPending !== null) {
        // New page rendering is pending
        renderPage(pageNumPending);
        pageNumPending = null;
      }
    }).then(function() {
      // Render the text content to a text layer
      var element = document.getElementById("text-layer");

      // remove previous text layer div (if it exists) and create a new one
      if (element !== null) {
        element.parentNode.removeChild(element);
      }

      var canvasOffset = $(pdf_canvas).offset();

      var textLayerDiv = document.createElement("div");
      textLayerDiv.setAttribute("id", "text-layer");
      textLayerDiv.setAttribute("class", "textLayer");
      // Fix the position of the text layer to the canvas
      // textLayerDiv.style.top = canvasOffset.top + 'px';
      textLayerDiv.style.left = canvasOffset.left + 'px';
      container.appendChild(textLayerDiv);

      // Render the text layer
      page.getTextContent().then(textContent => {
        PDFJS.renderTextLayer({
          textContent: textContent,
          container: textLayerDiv,
          viewport: viewport,
          textDivs: []
        });
      }).then(function() {
        if (search_pattern != null) {
            patterns = search_pattern.replace(/["',.*]/gm, '').replace(/~[0-9]*/gm,'').split(/[ +|,]/);
            for (i = 0; i < patterns.length; i++) highlight(patterns[i]);
            // TODO use Solr highlighter?
        }
      });

    });
  });
  
  // Update page counters
  if (document.getElementById('page_num')) document.getElementById('page_num').value = pageNum + page_offset;

  // Update scale
  if (document.getElementById('scale')) document.getElementById('scale').value = Math.floor(scale * 100 / base_scale);
}

/**
 * Highlight a search pattern text using https://markjs.io/
 */
function highlight(search_pattern) {
  // the PDFJS text layer removes white spaces, so we also detele them from the search pattern
  $("#text-layer").mark(search_pattern.replace(/\s/g, ''), {
    separateWordSearch: false,
//    separateWordSearch: true,
//    accuracy: "partially",
    accuracy: {
        "value": "exactly",
        "limiters": [",", ".", ":", ";", "-", "?", "!", "\"", "„", "'", "(", ")", "="]
    },
    diacritics: false,
    acrossElements: false   // this is usually better for short words
  });
}

/**
 * If another page rendering in progress, waits until the rendering is
 * finised. Otherwise, executes rendering immediately.
 */
function queueRenderPage(num) {
  if (pageRendering) {
    pageNumPending = num;
  } else {
    renderPage(num);
  }
}

/**
* Jump to page
*/

function jumpToPage() {
  var jumpToNum = document.getElementById('page_num').value - page_offset;
  if (!isNaN(jumpToNum) && !isNaN(parseInt(jumpToNum,10))) {
    var numToJump = parseInt(jumpToNum,10);
    if (numToJump < 1) {
      pageNum = 1;
    } else if(numToJump >= pdfDoc.numPages) {
      pageNum = pdfDoc.numPages;
    } else pageNum = numToJump;
  }
  queueRenderPage(pageNum);
  pageRequest(pageNum);
}

if (document.getElementById('page_num')) document.getElementById('page_num').onkeypress = function(e){
  var event = e || window.event;
  var charCode = event.which || event.keyCode;

  if ( charCode == '13' ) {
    jumpToPage();
  }
};

/**
 * Displays previous page.
 */
function onPrevPage() {
  if (pageNum <= 1) return;
  pageNum--;
  queueRenderPage(pageNum);
  pageRequest(pageNum);
}
if (document.getElementById('prev')) document.getElementById('prev').addEventListener('click', onPrevPage);

/**
 * Displays next page.
 */
function onNextPage() {
  if (pageNum >= pdfDoc.numPages) return;
  pageNum++;
  queueRenderPage(pageNum);
  pageRequest(pageNum);
}
if (document.getElementById('next')) document.getElementById('next').addEventListener('click', onNextPage);



/**
 * Viewer scaling
 */
function onScaleUp() {
  scale += 0.1 / base_scale;
  queueRenderPage(pageNum);
}
if (document.getElementById('scale_up')) document.getElementById('scale_up').addEventListener('click', onScaleUp);

function onScaleDown() {
  scale -= 0.1 / base_scale;
  queueRenderPage(pageNum);
}
if (document.getElementById('scale_down')) document.getElementById('scale_down').addEventListener('click', onScaleDown);
 
function onScaleSet() {
}
if (document.getElementById('scale')) document.getElementById('scale').onkeypress = function(e){
  var event = e || window.event;
  var charCode = event.which || event.keyCode;
  if ( charCode == '13' ) {
    scale = document.getElementById('scale').value / 100  * base_scale;
    queueRenderPage(pageNum);
  }
};




 
/**
 * Search document
 */
function searchDocument() {
  search_pattern = document.getElementById('text').value;
  if (search_pattern == "") {
		document.getElementById('query_result').textContent = "";
    return;
  }
  if (!searching) {
    searching = true;
    document.getElementById('query_result').innerHTML = "<span id=\"query_result\" class=\"fa fa-spinner fa-spin\" />";
    $.ajax({
      url: search_url + "?id=" + DOC_ID + "&text=" + search_pattern  + "&json=1",
      type:"GET",
      processData : false,
      contentType: false,
      success: function(msg) {
        result = JSON.parse(msg);
        document.getElementById('query_result').innerHTML = "";

        if (result.length != 0) {
          search_index = 0;
          if (result[0] == "err") {
            query_res = [];
            num_res = 0;
            document.getElementById('query_result').textContent = "Hiba!";
          } else {
            query_res = result;
            num_res = result.length;
            // update page text
            pageNum = query_res[search_index];
            pageRequest(pageNum);
            queueRenderPage(pageNum);
            document.getElementById('query_result').textContent = "Oldal: "+ (search_index+1).toString()+"/"+num_res.toString();
          }
        } else {
          search_index = 0;
          query_res = [];
          num_res = 0;
          document.getElementById('query_result').textContent = "Nincs találat.";
        }
        searching = false;
      },
      error: function (xhr, ajaxOptions, thrownError) {
        alert(xhr.status);
        alert(thrownError);
        searching = false;
      }
    });
  } else {
    document.getElementById('query_result').textContent = "Előző keresés még folyamatban.";
  }
}
if (document.getElementById('start_search')) document.getElementById('start_search').addEventListener('click', searchDocument);

/**
 * Displays previous query result.
 */
function onPrevResult() {
  search_pattern = document.getElementById('text').value;
  if (search_pattern.replace(/["',.*]/gm, '') == "") {
		document.getElementById('query_result').textContent = "";
    return;
  }
  if (num_res > 0)	{
    if(search_index > 0) {
      search_index--;
      pageNum = query_res[search_index];
      queueRenderPage(pageNum);
      pageRequest(pageNum);
      document.getElementById('query_result').textContent = "Oldal: "+ (search_index+1).toString()+"/"+num_res.toString();
    }
  } else {
    document.getElementById('query_result').textContent = "";
  }
}
if (document.getElementById('prev_result')) document.getElementById('prev_result').addEventListener('click', onPrevResult);

/**
 * Displays next query result.
 */
function onNextResult() {	
  search_pattern = document.getElementById('text').value;
  if (search_pattern.replace(/["',.*]/gm, '') == "") {
		document.getElementById('query_result').textContent = "";
    return;
  }
	if (num_res > 0) {
		if (search_index < num_res-1) {
			search_index++;
			pageNum = query_res[search_index];
			queueRenderPage(pageNum);
			pageRequest(pageNum);
			document.getElementById('query_result').textContent = "Oldal: "+ (search_index+1).toString()+"/"+num_res.toString();
		}
	}	else {
		document.getElementById('query_result').textContent = "";
  }
}
if (document.getElementById('next_result')) document.getElementById('next_result').addEventListener('click', onNextResult);


/**
* Clear the search form
*/
function clearSearch() {
  num_res = 0;
  document.getElementById("text").value = "";
  document.getElementById("query_result").textContent = "";
}

/**
* Gets page text content from server's text index
*/
function pageRequest(num){
  /* We're not updating the text content atm
	var request = new FormData();   
	request.append('book_id',pdf_id);
	request.append('page_num',num);
	$.ajax({
		url: dbpage,
		data: request,
		type:"POST",
		processData : false,
		contentType: false,
		success:function(msg){
			pdf_text = msg;
			// Update text
		}
	});
  */
}
