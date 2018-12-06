novanet.datatableInitObject = {
  order: [[4,"asc"]],
  columns: [
    {
      data: 'BUDGET_NUMBER',
      title: 'Budget Number'
    },{
      data: 'BUDGET_NAME',
      title: 'Budget Name'
    },{
      data: 'ISBN',
      title: 'ISBN/ISSN',
      render: novanet.fn.render.isn(),
      excelType: "s"
    },{
      data: 'ORDER_NUMBER',
      title: 'Order Number',
      type: "string",
      excelType: "s"
    },{
      data: 'ORDER_DATE',
      title: 'Order Date',
      render: novanet.fn.render.date('MMM D YYYY'),
      excelType: "d",
      excelFmt: "mmm d, yyyy"
    },{
      data: 'TOTAL_PRICE',
      title: 'Amount',
      render: novanet.fn.render.number(2,'$'),
      excelType: "n",
      excelFmt: "$#,###,##0.00"
    },{
      data: 'VENDOR_CODE',
      title: 'Vendor Code'
    }
  ]
};
