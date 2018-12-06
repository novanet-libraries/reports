
"use strict";

importScripts("/js/moment.js");
importScripts("/js/xlsx.core.min.js");
importScripts("/js/novanet.fn.js");

var report, params, /* input */
sheet,              /* output */
colInfo, data,      /* fetched during init */

/* constants */
preambleRowCount = 4,
headerStyle = {
  fill:{
    pattern: "none",
    fgColor: {rgb:"FFD3D3D3"}
  },
  font: {
    bold: true
  },
  border:{
    bottom:{
      style:"thin",
      color:{auto:1}
    }
  }
},

/* functions */
buildParamString = function(){
  var stringParts = [];

  Object.keys(params).forEach(function(key){
    if (Array.isArray(params[key])){
      params[key].forEach(function(val){
        stringParts.push( encodeURIComponent(key)+"="+encodeURIComponent(val) );
      });
    }
    else{
      stringParts.push( encodeURIComponent(key)+"="+encodeURIComponent(params[key]) );
    }
  });

  //this call must only ever use the cache (it must present the same data that's on the screen)
  stringParts.push("max-age=P100Y");

  return "?" + stringParts.join("&");
},
initWorksheet = function(numRows, numCols){
  var range = XLSX.utils.encode_range({
        //enough room for data plus headers and preamble. (+1 for header is offset by -1 for 0indexing vs 1indexing)
        s: { r: 0, c: 0 },
        e: { r: numRows + preambleRowCount, c: numCols }
      });
  sheet = {"!ref": range, "!cols": [] };
},
writePreamble = function(numCols){
  //merge the preamble cells and add some information
  sheet["!merges"] = [];
  for (var i=0; i<preambleRowCount; i++){
    sheet["!merges"].push({s:{r:i,c:0},e:{r:i,c:numCols-1}});
  }
  sheet['A1'] = {t: "s", v: report.name};
  sheet['A2'] = {t: "s", v: report.desc + " (as of " + moment(data.date).format("MMM Do, YYYY") + ")"};
  sheet['A3'] = {t: "s", v: "http://reports.novanet.ca" + novanet.fn.buildPath(report, params)};
  sheet['A4'] = {t: "z"};
},
writeHeaderCell = function(colNum, title){
  var cellName = XLSX.utils.encode_cell({r:preambleRowCount,c:colNum});
  sheet[cellName] = {
    t: "s",
    v: ""+title,
    s: headerStyle
  };
},
writeCellValue = function(cellName, cellValue, colSpec, colRender){
  if (undefined === cellValue || null === cellValue){
    //empty cell
    sheet[cellName] = {t:"z"};
  }
  else if (undefined !== colSpec.excelType){
    //if an Excel type was specified, use the raw data, and let Excel format it.
    if (colSpec.excelType == "d"){
      //manually convert "d" to "n" by calculating the Excel date number.
      sheet[cellName] = {
        t: "n",
        s: { numFmt:colSpec.excelFmt || "mmm d yyyy" },
        v: moment(cellValue, "YYYYMMDD").diff(moment("18991230", "YYYYMMDD"), "days") //gives the wrong number for dates before 1904.
      };
    }else{
      colSpec.numChars = Math.max(colSpec.numChars, cellValue.length);
      sheet[cellName] = {
        t: colSpec.excelType,
        s: { numFmt:colSpec.excelFmt },
        v: cellValue
      };
    }
  }
  else{
    //otherwise, use the displayed value on the screen as a string:
    //(N.B. This means if you want Excel to receive a number, you must specify the "n" type, even if you don't specify a format.)
    //(also, you can specify "s" as the excelType to disable DataTables rendering in the Excel sheet.)
    colSpec.numChars = Math.max(colSpec.numChars, cellValue.length);
    sheet[cellName] = {
      t: "s",
      v: "" + colRender(cellValue, "display")
    };
  }
},
writeColumn = function(colSpec, colNum, numRows, numCols){
  var rowNum, cellName, cellValue,
      cellLetter = XLSX.utils.encode_col(colNum),
      colRender  = colSpec.render || function(d,t){return d;};

  //keep track of max width written to the column (setting a hard-coded minimum of 12 for no particular reason).
  colSpec.numChars = Math.max(colSpec.title.length, 12);

  writeHeaderCell(colNum, colSpec.title);

  for ( rowNum=0; rowNum < numRows; rowNum++ ){
    cellName  = cellLetter + (rowNum + preambleRowCount + 2); // 2 = header row plus 0indexing vs 1indexing.
    cellValue = data.data[rowNum][colSpec.data];
    writeCellValue(cellName, cellValue, colSpec, colRender);
  }

  //set the column width based on numChars (may have changed during writeCellValue())
  sheet["!cols"][colNum] = {"wch": colSpec.numChars+2};

  postMessage(
    JSON.stringify({
      "percentComplete": (colNum+1/numCols*80)||2
    })
  );
},
processData = function(){
  var workbook = {SheetNames: [], Sheets: {}},
      numRows  = data.data.length,
      numCols  = colInfo.length;

  initWorksheet(numRows, numCols);
  writePreamble(numCols);

  postMessage(
    JSON.stringify({
      "percentComplete": 1
    })
  );

  //write the spreadsheet data column by column...
  colInfo.forEach(function(colSpec, colNum){
     writeColumn(colSpec, colNum, numRows, numCols);
  });

  //console.log(sheet);
  //we're done making the sheet; put it in a workbook and send that back.
  workbook.SheetNames.push(report.name);
  workbook.Sheets[report.name] = sheet;

  postMessage(
    JSON.stringify({
      "percentComplete": 100,
      "workbook": XLSX.write(workbook, {type:"binary"})
    })
  );
},
getData = function(){
  //We probably could pass this data in postMessage, rather than fetching it here.
  //When it is large though, that might slow the UI thread.
  var xmlhttp = new XMLHttpRequest();
  xmlhttp.open("GET", "/data" + report.base + report.filename + "/report.php" + buildParamString());
  xmlhttp.onreadystatechange = function(){
    if (xmlhttp.status == 200 && xmlhttp.readyState == 4){
      data = JSON.parse(xmlhttp.responseText);
      processData();
    }
  };
  xmlhttp.send();
},
start = function(){
  //I had thought we might be able to pass colInfo in postMessage() rather than having
  //to fetch and eval() it here, but it couldn't be serialized.
  var xmlhttp = new XMLHttpRequest();
  xmlhttp.open("GET", "/data" + report.base + report.filename + "/report-config.js");
  xmlhttp.onreadystatechange = function(){
    if (xmlhttp.status == 200 && xmlhttp.readyState == 4){
      eval(xmlhttp.responseText); //sets novanet.datatableInitObject
      colInfo = novanet.datatableInitObject.columns;
      getData();
    }
  };
  xmlhttp.send();
};


onmessage = function(evt){
  report  = JSON.parse(evt.data[0]);
  params  = JSON.parse(evt.data[1]);
  start();
};
