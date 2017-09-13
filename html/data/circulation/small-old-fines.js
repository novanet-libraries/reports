novanet.datatableInitObject = {
  order: [[4,"asc"]],
  columns: [
    {
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
      visible: false,
      excelType: "s"
    },{
      data : 'Z303_HOME_LIBRARY',
      title: 'Home Library',
      visible: false
    },{
      data : 'Z303_NAME',
      title: 'Name',
      className: 'text-nowrap'
    },{
      data : 'AMOUNT',
      title: 'Amount Owing',
      render: novanet.fn.render.number(2, '$'),
      excelType: "n",
      excelFmt: "$0.00"
    }
  ]
};
