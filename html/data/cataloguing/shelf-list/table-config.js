novanet.datatableInitObject = {
  //ordering: false,
  order: [[7,"asc"],[8,"asc"]],
  deferRender: true,
  columns: [
    {
      data: "TITLE",
      title: "Title"
    },{
      data: "AUTHOR",
      title: "Author",
      visible: false
    },{
      data: "IMPRINT",
      title: "Imprint",
      visible: false
    },{
      data: "YEAR",
      title: "Year",
      visible: false
    },{
      data: "ISN",
      title: "ISSN/ISBN",
      visible: false,
      render: novanet.fn.render.isn(),
      type: "string",
      excelType: "s"
    },{
      data: "BIB_NUMBER",
      title: "BIB number",
      type: "string",
      excelType: "s"
    },{
      data: "COLLECTION",
      title: "Collection",
      visible: false
    },{
      data: "CALLNUMBER",
      title: "Callnumber",
      className: "text-nowrap",
      type: "callnumber"
    },{
      data: "DESCRIPTION",
      title: "Item Description",
      type: "natural"
    },{
      data: "MATERIAL_TYPE",
      title: "Material",
      visible: false
    },{
      data: "BARCODE",
      title: "Barcode",
      type: "string",
      className: "text-nowrap",
      render: novanet.fn.render.barcode(),
      excelType: "s"
    },{
      data: "ITEM_STATUS",
      title: "Item Status",
      visible: false,
      render: novanet.fn.render.lookup("itemStatuses"),
      type: "string",
      excelType: "s"
    },{
      data: "PROCESS_STATUS",
      title: "Item Process Status",
      render: novanet.fn.render.lookup("itemStatuses")      
    },{
      data: "DUEDATE",
      title: "Due Date",
      className: "text-nowrap",
      render: novanet.fn.render.date("MMM Do"),
      excelType: "d",
      excelFmt: "mmm d"
    }/*,{
      data: "LAST_EDIT",
      title: "Last Edit",
      className: "text-nowrap",
      visible: false,
      render: novanet.fn.render.date("YYYY-MM-DD"),
      excelType: "d",
      excelFmt: "yyyy-mm-dd"
    }*/
  ]
};
