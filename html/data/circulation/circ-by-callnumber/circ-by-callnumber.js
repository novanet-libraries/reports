novanet.datatableInitObject = {
  order: [[9,"desc"]],
  deferRender: true,
  columns: [
    {
      data : "BARCODE",
      title: "Barcode",
      type: "string",
      className: "text-nowrap",
      render: novanet.fn.render.barcode(),
      excelType: "s"
    },{
      data : "INST_ID",
      title: "Inst ID",
      type: "string",
      visible: false,
      excelType: "s"
    },{
      data : "Z303_NAME",
      title: "Name",
      className: "text-nowrap"
    },{
      data : "Z303_HOME_LIBRARY",
      title: "Home Library",
      visible: false
    },{
      data : "Z305_BOR_STATUS",
      title: "Patron Status",
      visible: false
    },{
      data : "LOANS",
      title: "Loans",
      excelType: "n"
    },{
      data : "RENEWALS",
      title: "Renewals",
      excelType: "n"
    },{
      data : "HOLDS",
      title: "Holds",
      excelType: "n"
    },{
      data : "PHOTOCOPIES",
      title: "Photocopies",
      visible: false,
      excelType: "n"
    },{
      data : "BOOKINGS",
      title: "Bookings",
      visible: false,
      excelType: "n"
    },{
      data : "TOTAL",
      title: "Total",
      excelType: "n"
    }
  ]
};