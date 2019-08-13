novanet.datatableInitObject = {
  order: [[5,"asc"]],
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
      title: "Collection"
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
      data: "BARCODE",
      title: "Barcode",
      type: "string",
      className: "text-nowrap",
      render: novanet.fn.render.barcode(),
      excelType: "s"
    },{
      data: "ITEM_STATUS",
      title: "Item Status",
      type: "string",
      excelType: "s"
    },{
      data: "PROCESS_STATUS",
      title: "Item Process Status"
   },{
     data: "PROCESS_STATUS_DATE",
     title: "Process Status Date",
     render: novanet.fn.render.date("YYYY-MM-DD"),
     excelType: "d",
     excelFmt: "yyyy-mm-dd"
    }
  ]
};
