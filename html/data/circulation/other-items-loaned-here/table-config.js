novanet.datatableInitObject = {
  order: [[15,"asc"]],
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
      visible: false,
      type: "string",
      excelType: "s"
    },{
      data: "SUB_LIBRARY",
      title: "Owning Library",
      render: novanet.fn.render.lookup("sublibraries")
    },{
      data: "COLLECTION",
      title: "Collection",
      render: novanet.fn.render.lookup("collectionsFlat")
    },{
      data: "CALLNUMBER",
      title: "Callnumber",
      className: "text-nowrap",
      type: "callnumber"
    },{
      data: "DESCRIPTION",
      title: "Item Description",
      visible: false,
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
      visible: false,
      render: novanet.fn.render.lookup("itemStatuses"),
      className: "text-nowrap",
      type: "string",
      excelType: "s"
    },{
      data: "PROCESS_STATUS",
      title: "Item Process Status",
      visible: false,
      render: novanet.fn.render.lookup("itemProcessStatuses"),
      className: "text-nowrap"
    },{
      data: "MATERIAL_TYPE",
      title: "Material Type"
    },{
      data: "LOAN_LOCATION",
      title: "Loan Location",
      visible: false,
      type: "string",
      excelType: "s"
    },{
      data: "LOAN_DATE",
      title: "Loan Date",
      className: "text-nowrap",
      render: novanet.fn.render.date("MMM Do, YYYY"),
      excelType: "d",
      excelFmt: "mmm d, yyyy"
    }
  ]
};
