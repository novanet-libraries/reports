novanet.datatableInitObject = {
  order: [[0,"asc"],[1,"asc"]],
  columns: [
    {
      data : "PERIOD",
      title: "Period",
      render: novanet.fn.render.period(),
      type: "string",
      excelType: "s"
    },{
      data : "CNRANGE",
      title: "Call Number Range"
    },{
      data: "CNRANGE2",
      title: "Call Number Range",
      visible: false
    },{
      data : "LOAN",
      title: "Loans",
      excelType: "n"
    },{
      data : "RENEWAL",
      title: "Renewals",
      excelType: "n"
    },{
      data : "HOLD",
      title: "Holds",
      excelType: "n"
    },{
      data : "PHOTOCOPY",
      title: "Photocopies",
      excelType: "n"
    },{
      data : "RESHELF",
      title: "Reshelved",
      excelType: "n"
    },{
      data : "BOOKING",
      title: "Bookings",
      excelType: "n",
      visible: false
    }
  ]
};
