
var novanet = novanet || {};
novanet.fn = (function(){

  "use strict";

  //declare a whole bunch of utility functions and return some of them as an api.

  var setTitle = function(title, desc, note){
        // what about paramsToTitle(data)
        novanet.page.$title.find("h2").empty().html(title);
        novanet.page.$title.find(".desc").empty().html(desc);
        novanet.page.$title.find(".note").empty().html(note);

        document.title = title ? 'Novanet Reports - '+title : 'Novanet Reports';
      },
      showProgress = function(percent){
        //0 or less or NaN means "continuous", i.e. show it 100% full.
        //100 or more means "done", i.e. hide it.
        //any number in between means show that percent complete.
        percent = parseInt(percent, 10) || 0;
        //console.log(percent);
        if (percent <= 0){
          novanet.page.$processing.find(".progress-bar").css("width","100%").attr("aria-valuenow","100");
          novanet.page.$processing.show().attr("aria-hidden","false");
        }
        else if (percent >= 100){
          novanet.page.$processing.hide().attr("aria-hidden","true");
        }
        else{
          //console.log(novanet.page.$processing);
          novanet.page.$processing.find(".progress-bar").css("width",percent+"%").attr("aria-valuenow",percent);
          novanet.page.$processing.show().attr("aria-hidden","false");
        }
      },
      hideProgress = function(){
        showProgress(100);
      },
      haveAllRequiredParams = function(report, params){
        if (!report["req-params"] || report["req-params"].length === 0){
          return true;
        }
        if (!params){
          return false;
        }
        report["req-params"].forEach(function(paramName, idx){
          if (!params.hasOwnProperty(paramName)){
            return false;
          }
        });
        return true;
      },
      paramsToTitle = function(params){
        var pieces = [];
        if (!params){
          return "";
        }
        Object.keys(params).forEach(function(key){
          var val = Array.isArray(params[key]) ? params[key].join(" ") : ""+params[key];
          if (val && val.match(/^[A-Za-z0-9 _-]+$/)){
            pieces.push(val);
          }
        });
        return pieces.length ? " (" + pieces.join(", ") + ")" : "";
      },
      paramsToFilename = function(params){
        var pieces = [], name = "";
        if (params){
          Object.keys(params).forEach(function(key){
            var val = Array.isArray(params[key]) ? params[key].join(" ") : ""+params[key];
            if (val && val.match(/^[A-Za-z0-9 _-]+$/)){
              pieces.push(val.replace(/ /g, '-'));
            }
          });
        }
        name = pieces.length ? "-" + pieces.join("-") : "";
        return (name.length > 0 && name.length < 50) ? name : moment().format("YYYYMMDD-HHmmss");
      },
      customizePrintView = function(win){
        var $doc = $(win.document);

        $doc.find('h1').prepend(
          '<img src="https://reports.novanet.ca/images/novanet-icon-32x32.png" width="32" height="32" alt="icon">'
        );

        $doc.find('thead th').each(function(){
          var $th = $(this);
          if ($th.text().toLowerCase().indexOf("barcode") > -1){
            var idx = $th.index();
            $doc.find('tbody tr').each(function(){
              var $td = $(this).find('td:nth-child(' + (idx+1) + ')');
              if ($td.text().match(/^\*?\d+\*?$/)){
                $td.addClass('barcode');
              }
            });
          }
        });
      },
      downloadXLSX = function(binData, filename){
        var i, buffer  = new ArrayBuffer(binData.length),
            bufferView = new Uint8Array(buffer);

        for(i=0; i!=binData.length; ++i){
          bufferView[i] = binData.charCodeAt(i) & 0xFF;
        }
        $.fn.dataTable.fileSave(new Blob([buffer],{type:"application/octet-stream"}), filename);
      },
      defaultButtons = function(report, params){
        return [{
            //custom Excel button using js-xlsx, not the "Buttons" excel button
            text : "<i class='glyphicon glyphicon-save'></i> Excel",
            action : function(e, dt, node, config){
              e.preventDefault();
              var timer = setInterval(function(){
                showProgress(novanet.excel.percentComplete);
                if (novanet.excel.percentComplete >= 100 && novanet.excel.workbook){
                  downloadXLSX(novanet.excel.workbook, report.filename + paramsToFilename(params) + "-" + novanet.today + ".xlsx");
                  hideProgress();
                  clearInterval(timer);
                }
              }, 500);
            },
            className: "btn-success"
          },{
            extend    : "csv",
            text      : "<i class='glyphicon glyphicon-save'></i> CSV",
            filename  : report.filename + paramsToFilename(params) + "-" + novanet.today,
            className : "btn-info"
          },{
            extend    : "colvis",
            text      : "<i class='glyphicon glyphicon-eye-close'></i> Show/Hide Columns",
            titleAttr : "Visibility affects screen and print only. Downloaded files always contain all the data."
          },{
            extend        : "print",
            text          : "<i class='glyphicon glyphicon-print'></i> Print",
            title         : report.name + paramsToTitle(params),
            message       : report.desc + " (retreived " + novanet.now.format("MMM Do YYYY") + ")",
            autoPrint     : true,
            customize     : customizePrintView,
            exportOptions : {
              columns: ":visible"
            }
          }/*,{
            // custom button, not extending a built in one
            text : 'Show/Hide SQL',
            init : function(dt, $button, btnConfig){
              $button.attr({
                "data-toggle" : "collapse",
                "data-target" : "#sql"
              });
            }
          }*/
        ];
      },
      defaultAjaxObject = function(report, params, forceRefresh){
        params = $.extend({"max-age": report["max-age"]}, params);
        return {
          url   : "/data" + report.base + report.filename + "/report.php",
          data  : params,
          error : novanet.errorHandler,
          cache : !forceRefresh
        };
      },
      // DataTables rendering functions for various datatypes
      render = {
        barcode: function(){
          return function(data, type, row, meta){
            return (type == "display" && data && data.match(/^\d+$/)) ? "<span class='barcode'>*"+data+"*</span>" : data;
          };
        },
        date: function(displayFormat){
          return function(data, type, row, meta){
            if (data && type == "display"){
              return moment(data, "YYYYMMDD").format(displayFormat);
            }
            else if (data && type == "filter"){
              return moment(data, "YYYYMMDD").format("dddd MMMM Do YYYY");
            }
            else {
              return data;
            }
          };
        },
        isn: function(){
          return function(data, type, row, meta){
            if (data && type == "filter"){
              return data.replace(/-/g, "");
            }
            else if (data && type == "sort"){
              var s = data.replace(/-/g,"").replace(/^\s+/,"").replace(/^978/,"").replace(/^(\d{7}\d{2}?[0-9Xx]).*/,"$1");
              if (s.match(/^\d{7}\d{2}?[0-9Xx]$/)){
                return s;
              }
              else{
                return '99999999999999' + data;
              }
            }
            else{
              return data;
            }
          };
        },
        number: function(precision, prefix, suffix){
          return function(data, type, row, meta){
            var parts, d = parseFloat(data);
            if (data && !isNaN(d) && type == "display"){
              parts = d.toFixed(precision||0).match(/^(-)?(\d+)(\.\d*)?$/);
              if (parts){
                parts[2] = parts[2].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                return (parts[1]||"") + (prefix||"") + parts[2] + (parts[3]||"") + (suffix||"");
              }
            }
            return data;
          };
        },
        percent: function(){
          return function(data, type, row, meta){
            var d = Math.ceil(parseFloat(data)*100) || 0,
                alertType = d >= 100 ? "danger" : (d >= 80 ? "warning" : "success");

            if (data && type == "display"){
              //return a bootstrap progress-bar element, filled to d%
              return "<div class='progress'>" +
                       "<div class='progress-bar progress-bar-"+alertType+"' " +
                            "aria-valuenow='"+d+"' aria-valuemin='0' aria-valuemax='100' role='progressbar' " +
                            "style='min-width:2em;max-width:100%;width:"+d+"%'>" +
                         "<span>"+d+"%</span>" +
                     "</div></div>";
            }
            else if (data && (type == "sort" || type == "type")){
              return data;
            }
            else {
              return data ? d + "%" : null;
            }
          }
        },
        period: function(){
          return function(data, type, row, meta){
            var m, displayFmt, filterFmt;

            if (!data || !(type == "display" || type == "filter")){
              return data;
            }

            if (data.match(/^\d{8}$/)){
              m = moment(data, "YYYYMMDD");
              displayFmt = "MMM Do YYYY";
              filterFmt  = "dddd MMMM Do YYYY";
            }
            else if (data.match(/^\d{4}W\d{2}$/)){
              m = moment().isoWeekYear(data.substr(0,4)).isoWeek(data.substr(-2)).isoWeekday(1);
              displayFmt = "[Week of] MMM Do YYYY";
              filterFmt  = "[Week of] MMMM Do YYYY";
            }
            else if (data.match(/^\d{6}$/)){
              m = moment(data + "01", "YYYYMMDD").format("MMM YYYY");
              displayFmt = "MMM YYYY";
              filterFmt  = "MMMM YYYY";
            }
            else if (data.match(/^FY\d{4}$/)){
              m = moment().year(data.substr(-4)).month(3).day(1);
              displayFmt = "[Fiscal Year] YYYY[BUMP]";
              filterFmt  = "[FY]YYYY";
            }
            else if (data.match(/^AY\d{4}$/)){
              m = moment().year(data.substr(-4)).month(8).day(1);
              displayFmt = "[Academic Year] YYYY[BUMP]";
              filterFmt  = "[AY]YYYY";
            }
            else if (data.match(/^\d{4}$/)){
              m = moment().year(data).month(0).day(1);
              displayFmt = "YYYY";
              filterFmt  = "YYYY";
            }
            else {
              console.warn("Encountered invalid period: " + data);
              m = m.invalid();
            }

            if (type == "filter"){
              return m.format(filterFmt);
            }
            else{
              var output, match;
              output = m.format(displayFmt);
              match = output.match(/(\d\d)BUMP$/);
              if (match){
                output = output.replace("BUMP", "/" + (parseInt(match[1],10) + 1));
              }
              return output;
            }
          };
        }
      },
      //build/parse path:
      buildPath = function(report, params){
        var path = "/";
        if (report){
          path = report.base + report.filename + "/";
        }
        if (report && params){
          Object.keys(params).sort().forEach(function(key){
            //check that key is an expected parameter, otherwise, don't add it to the path.
            if ( (report["req-params"] && report["req-params"].indexOf(key) > -1) ||
                 (report["opt-params"] && report["opt-params"].indexOf(key) > -1) ){
              path += key + "/";
              if (Array.isArray(params[key])){
                path += params[key].sort().map(encodeURIComponent).join(",") + "/";
              }
              else{
                path += encodeURIComponent(params[key]) + "/";
              }
            }
          });
        }
        return path;
      },
      parsePath = function(pathString){
        pathString = pathString || location.pathname;
        var pathPieces, report = null, params = null, i, key, val;

        //split, then remove leading and trailing ""
        pathPieces = pathString.split("/");
        pathPieces.shift();
        if (pathPieces[pathPieces.length-1] == ""){
          pathPieces.pop();
        }

        //pathPieces[0] is base.
        //pathPieces[1] is report-name.
        //The rest are parameters.

        if (pathPieces[1] && novanet.allreports[pathPieces[1]]){
          report = novanet.allreports[pathPieces[1]];
        }
        if (report){
          for (i=2; i<pathPieces.length; i+=2){
            params = params || {};
            key = decodeURIComponent(pathPieces[i]);
            if (typeof pathPieces[i+1] === "undefined"){
              params[key] = null;
            }
            else{
              val = decodeURIComponent(pathPieces[i+1]);
              params[key] = (val.indexOf(",") == -1) ? val : val.split(",");
            }
          }
        }

        return {
          report: report,
          params: params
        };
      },
      loadReport = function(rpt, params){
        //shows the input parameters form, or loads the results directly if all required parameters are already known (e.g. from parsing the URL).
        var report = $.isPlainObject(rpt) ? rpt : novanet.allreports[rpt];

        clearReport();
        novanet.page.$home.hide();

        setTitle(report.name, report.desc, report.note);

        //fetch and highlight the SQL that generated this report
        //novanet.page.$sql.load("/data" + report.base + report.filename + ".sql", function(){
        //  hljs.highlightBlock($(this).get(0));
        //});

        //if all the params are here, just load the results
        if (haveAllRequiredParams(report, params)){
          loadResults(report, params);
        }
        else{
          //else show the form to input parameters (which in turn must call loadResults() onsubmit)
          novanet.page.$params.find(".panel-body").load(
            "/data" + report.base + report.filename + "/input-params.html",
            function(){
              novanet.page.$params.show();
            }
          );
        }
      },
      loadResults = function(report, params, forceRefresh){
        var defaultInitObj = {
          //this ajax request gets the data, the call to DataTable() actually triggers it.
          ajax: defaultAjaxObject(report, params, forceRefresh||false),
          buttons: defaultButtons(report, params),
          dom: "<'row'<'col-sm-3'<'#cache-statement'>><'col-sm-3'f><'col-sm-6'B>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-2'l><'col-sm-4'i><'col-sm-6'p>>"
        };

        //this ajax request gets configuration info for the DataTable
        //(which includes the ajax request that gets the data).
        $.ajax({
          url: "/data" + report.base + report.filename + "/report-config.js",
          dataType: "script",
          cache: true,
          success: function(){
            var fullInitObj = $.extend(defaultInitObj, novanet.datatableInitObject);
            novanet.page.$table.DataTable(fullInitObj);
          },
          error: function(a, b, c){
            console.error(a);
            console.error(b);
            console.error(c);
          }
        });
      },
      clearReport = function(){
        //hide UI:
        setTitle("", "", "");
        $("#current-report > section").hide();
        hideProgress();
        
        //reclaim memory (destroy "chosen" objects and "DataTable" objects)
        novanet.page.$params.find("select").chosen("destroy");
        novanet.page.$params.find(".panel-body").empty();

        if ($.fn.dataTable.isDataTable(novanet.page.$table)){
          novanet.page.$table.DataTable().destroy();
        }
        novanet.page.$table.empty();
      },
      //daterangepicker stuff
      prevYearStartingAt = function(startMonthNum){
        startMonthNum = startMonthNum > -1 && startMonthNum < 12 ? startMonthNum : 0;
        var e, s = moment().subtract(1, 'year');
        while (s.month() != startMonthNum){
          s.subtract(1, 'month');
        }
        s.startOf('month');

        return [s, moment(s).add(1, 'year').subtract(1, 'day')];
      },
      pushState = function(report, params){
        window.history.pushState({
            report: report,
            params: params
          },
          null,
          buildPath(report, params)
        );
      };

  return {
    pushState         : pushState,
    parsePath         : parsePath,
    buildPath         : buildPath,
    loadReport        : loadReport,
    loadResults       : loadResults,
    clearReport       : clearReport,
    showProgress      : showProgress,
    hideProgress      : hideProgress,
    render            : render,
    prevYearStartingAt: prevYearStartingAt
  };
})();
