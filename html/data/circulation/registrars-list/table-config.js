novanet.datatableInitObject = {
  order: [[3,"desc"]],
  columns: [
    { data : 'HOME_LIBRARY',
      title: 'Home Library',
      orderable: false,
      visible: false
    },{
      data : 'EXPIRY_DATE',
      title: 'Expiry Date',
      type: 'num',
      className: 'text-nowrap',
      render : novanet.fn.render.date('MMM D, YYYY'),
      excelType: "d",
      excelFmt: "mmm d, yyyy"
    },{
      data : 'NAME',
      title: 'Name',
      className: 'text-nowrap'
    },{
      data : 'AMOUNT',
      title: 'Amount Owing',
      render: novanet.fn.render.number(2, "$"),
      excelType: "n",
      excelFmt: "$#,###,##0.00;[Red]$#,###,##0.00\" credit\""
    },{
      data : 'BARCODE',
      title: 'Barcode',
      type: 'string',
      className: 'text-nowrap',
      render: novanet.fn.render.barcode(),
      excelType: "s"
    },{
      data : 'INST_ID',
      title: 'Inst ID',
      type: 'string',
      excelType: "s"
    }
  ]
};
