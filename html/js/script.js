
novanet       = novanet || {};
novanet.now   = moment();
novanet.today = novanet.now.format("YYYYMMDD");
novanet.callNumberRegExp = /^([A-Za-z]{1,3})(?:\/|\s{0,3})([1-9]\d{0,4}(?:\s{0,3}\.\d{1,8})?)(?:(?:\/|\s{1,3})([1-9][-?0-9]{0,3}(?:[a-z]|st|ST|nd|ND|rd|RD|th|TH)?)(?:\/|\s{0,3})\.?\s{0,3}([A-Za-z]{1,2}\d(?:[0-9\.]{0,7}(?:[A-Z][a-z]{1,3}\d{0,2})?)?)|(?:\/|\s{0,3})\.?\s{0,3}([A-Za-z]{1,2}\d(?:[0-9\.]{0,7}(?:[A-Z][a-z]{1,3}\d{0,2})?)?)|(?:\/|\s{1,3})([1-9][-?0-9]{0,3}(?:[a-z]|st|ST|nd|ND|rd|RD|th|TH)?))(?:(?:\/|\s{0,3})([A-Za-z]{1,2}\d(?:[0-9\.]{0,7}(?:[A-Z][a-z]{1,3}\d{0,2})?)?))?(?:(?:\/|\s{1,3})(.*))?$/;

//add callnumber sort capability to DataTables
$.fn.dataTable.ext.type.detect.unshift(
  function(d){
    return d && (""+d).match(novanet.callNumberRegExp) ? 'callnumber' : null;
  }
);
$.fn.dataTable.ext.type.order['callnumber-asc'] = function(a,b){
  if (!a){ return (b ? -1 : 0); }
  if (!b){ return 1; }

  var amatch = a.match(novanet.callNumberRegExp),
      bmatch = b.match(novanet.callNumberRegExp),
      abs, bbs, asc, bsc, adc, bdc, ac1, bc1, ac2, bc2, aext, bext, cmp;

  if (!amatch) { return (bmatch ? 1 : a.localeCompare(b)); }
  if (!bmatch) { return -1; }

  abs = amatch[1].toUpperCase();
  bbs = bmatch[1].toUpperCase();
  cmp = abs.localeCompare(bbs);
  if (cmp) { return cmp;  };

  asc = parseFloat(amatch[2].replace(/\s+/g, ''));
  bsc = parseFloat(bmatch[2].replace(/\s+/g, ''));
  cmp = asc - bsc;
  if (cmp < -0.000000001) { return -1; }
  if (cmp >  0.000000001) { return  1;
  }

  adc = (amatch[3] || amatch[6] || '').replace(/\?|-/g, '0');
  bdc = (bmatch[3] || bmatch[6] || '').replace(/\?|-/g, '0');
  if (adc.length || bdc.length){
    cmp = adc.localeCompare(bdc);
    if (cmp) { return cmp; }
  }

  ac1 = (amatch[5] || amatch[4] || '');
  bc1 = (bmatch[5] || bmatch[4] || '');
  cmp = ac1.localeCompare(bc1);
  if (cmp) { return cmp; }

  ac2 = (amatch[7] || '');
  bc2 = (bmatch[7] || '');
  cmp = ac2.localeCompare(bc2);
  if (cmp) { return cmp; }

  aext = (amatch[8] || '');
  bext = (bmatch[8] || '');
  return aext.localeCompare(bext);
};
$.fn.dataTable.ext.type.order['callnumber-desc'] = function(a,b){
  return $.fn.dataTable.ext.type.order['callnumber-asc'](b,a);
};

//override default DataTables error behaviour
$.fn.dataTable.ext.errMode = 'none';
novanet.errorHandler = function(jqXHR, textStatus, errorThrown){
  var message  = errorThrown + "\n\n",
      response = JSON.parse(jqXHR.responseText);
  console.error(textStatus);
  console.error(errorThrown);
  console.error(response);
  novanet.fn.clearReport();
  novanet.page.$home.show();
  window.history.replaceState({},null,"/");
  alert(message + (response && response.error ? response.error : 'Unknown error.  If there is nothing logged in the console, then the error occured on the server.'));
};

novanet.getPageComponents  = function(){

  novanet.page = novanet.page || {};

  novanet.page.$title         = $("#report-title");
  novanet.page.$processing    = $("#report-processing");
  novanet.page.$params        = $("#report-parameters");
  novanet.page.$results       = $("#report-results");
  novanet.page.$table         = $("#datatable");
  novanet.page.$cacheNote     = $("#cache-statement");
  novanet.page.$navbar        = $("#navbar");
  novanet.page.$home          = $("#home");
};

novanet.getSupportData = function(){

  if (undefined == window.localStorage){
    alert("Can't initialize reports page in this web browser");
    return;  //we should try to fail more gracefully here, but localStorage is pretty standard now.
  }

  novanet.data = novanet.data || {};
  novanet.fn.showProgress();

  //old version of localStorage should be cleared
  if (localStorage.allBudgets){
    localStorage.clear();
  }

  var lastWrite, today = new Date();
  if (localStorage.lastWrite){
    lastWrite = Date.parse(localStorage.lastWrite);
    if (today - lastWrite < (1000*60*60*20)){
      //cached data is good enough.
      novanet.data = JSON.parse(localStorage.supportData);
      novanet.fn.hideProgress();
      novanet.page.$title.find("h2").empty(); //remove the 'Initializing...' title.
      return;
    }
  }

  $.getJSON('/data/support-data.php', function(data){
      localStorage.supportData = JSON.stringify(data);
      novanet.data = data;
  }).then(function(){
    localStorage.lastWrite = today; //today.toString(), actually
    novanet.fn.hideProgress();
    novanet.page.$title.find("h2").empty(); //remove the 'Initializing...' title.
  }).fail(function(){
    alert('Error fetching initialization data');
  });

};

//constant listenters for the datatable <table> element.
novanet.addDatatableListeners = function(){
  var $table = novanet.page.$table;

  novanet.page.$results.on("click", ".reload", function(evt){
    evt.preventDefault();
    var state = novanet.fn.parsePath(location.pathname);
    if (state && state.report){
      novanet.page.$results.hide();
      if ($.fn.dataTable.isDataTable($table)){
        $table.DataTable().destroy();
      }
      $table.empty();
      novanet.fn.loadResults(state.report, state.params, "refresh");
    }
  });

  $table.on('xhr.dt', function(e, settings, data, xhr){
    //once we get the data, update the cache statement,
    //and start the worker to generate the Excel file.

    var $t, d = moment(data.date, "YYYY-MM-DD HH:mm:ss"),
        $reload = $("<button>").addClass("reload btn btn-success").html("<i class='glyphicon glyphicon-repeat'></i> Reload with live data"),
        state = novanet.fn.parsePath();

    if (state && state.report && state.params){
      //we could pass "data" directly here as well, but that may slow the UI thread.
      //better to have worker fetch the data itself, I think
      novanet.excel.worker.postMessage([
        JSON.stringify(state.report),
        JSON.stringify(state.params)
      ]);
    }

    if (d.isValid()){
      $reload.prop("disabled", d.isAfter(moment().subtract(1, "hour")));
      $("#cache-statement").empty().append(
        "<span>Data last updated </span>",
        $("<time>").attr({
          datetime : d.toISOString(),
          title    : d.format("MMM Do YYYY, h:mma")
        }).html(d.fromNow()),
        $reload
      );
    }
    else{
      $("#cache-statement").html("No cache information");
    }
  }).on('processing.dt', function(e, settings, processing){
    if (processing){
      novanet.fn.showProgress();
    }
    else{
      novanet.fn.hideProgress();
    }
  }).on('init.dt', function(){
    novanet.page.$params.hide();
    novanet.page.$results.show();
  }).on('error.dt', function(e, settings, techNote, message){
    //log errors
    console.error(e);
    console.error(settings);
    console.error(techNote);
    console.error(message);
  });
};


//populate the navbar with a link to each report in "reports.json"
novanet.buildNavbar = function(allreports){
  var $topUl = novanet.page.$navbar.find("ul.navbar-nav:first-of-type");

  //re-organize the "flat" reports.json into a heirarchy:
  var buckets = {};
  $.each(allreports, function(name, report){
    var category = report.base.replace(/\//g, "");
    buckets[category] = buckets[category] || [];
    buckets[category].push(report);
  });

  //iterate over the hierarchy and put each report in the nav menu:
  $.each(Object.keys(buckets).sort(), function(idx, category){

    //make a report drop-down menu
    var $reportsUl = $("<ul>").addClass("dropdown-menu");
    $.each(buckets[category].sort(
        function(a,b){ return a.name.localeCompare(b.name); }
      ),
      function(idx, report){
        $reportsUl.append(
         "<li><a href='#' class='report' data-report='" + report.filename + "'>" + report.name + "</a></li>"
        );
      }
    );

    //append a label for the menu and the menu itself to the top <ul> in the navbar.
    $topUl.append(
      $("<li>").addClass("dropdown").append(
        $("<a>").addClass("dropdown-toggle text-capitalize").attr({
          "href"          :"#",
          "data-toggle"   : "dropdown",
          "role"          : "button",
          "aria-haspopup" : true,
          "aria-expanded" : false
        }).html(category + "<i class='caret'></i>"),
        $reportsUl
      )
    );
  });

  // When you select a report in the navbar, close the
  // collapsible part of the navbar and load the report.
  novanet.page.$navbar.on("click", ".report", function(evt){
    var $this = $(this),
        report = $this.attr("data-report");

    evt.preventDefault();

    novanet.fn.loadReport(report, null);
    novanet.fn.pushState(allreports[report], null);

    novanet.page.$navbar.find(".active").removeClass("active");
    $this.parents(".dropdown").addClass("active");
  });

};

$(window).on("popstate", function(evt){
  var state = evt.originalEvent.state;

  if (state && state.report){
    novanet.fn.loadReport(state.report, state.params);
  }
  else{
    novanet.fn.clearReport();
    novanet.page.$home.show();
    window.history.replaceState({},null,"/");
  }
});

$(document).ready(function(){
  novanet.getPageComponents();
  novanet.getSupportData();
  novanet.addDatatableListeners();

  setInterval(function(){
    //keep any <time> elements pretty and up-to-date.
    $("time").each(function(){
      var $this = $(this);
      $this.html(moment($this.attr("datetime")).fromNow());
    });
  }, 180000);

  //initialize the Excel WebWorker:
  novanet.excel = novanet.excel || {};
  novanet.excel.worker = new Worker("/xlsx.webworker.js");
  novanet.excel.worker.onmessage = function(evt){
    var msg = JSON.parse(evt.data);
    if (undefined !== msg.percentComplete){
      novanet.excel.percentComplete = msg.percentComplete;
    }
    if (undefined !== msg.workbook){
      novanet.excel.workbook = msg.workbook;
    }
  };


  $.getJSON("/reports.json").then(function(allreports){
    novanet.allreports = allreports; //keep this

    //build menu of all reports
    novanet.buildNavbar(allreports);

    //if there's an initial path, load that report
    var state = novanet.fn.parsePath(location.pathname);
    if (state && state.report){
      window.history.replaceState({report:state.report,params:state.params},null,location.pathname);
      novanet.fn.loadReport(state.report, state.params);
    }
  }).fail(function(a,b,c,d){
    console.error(a);
    console.error(b);
    console.error(c);
    console.error(d);
  });
});
