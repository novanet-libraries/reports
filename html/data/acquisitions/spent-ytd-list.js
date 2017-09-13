novanet.datatableInitObject = {
  columns: [
    {
      data: 'BUDGET_NUMBER',
      title: 'Budget Number',
      visible: false
    },{
      data: 'Z76_NAME',
      title: 'Budget Name',
      visible: false
    },{
      data: 'ORDER_NUMBER',
      title: 'Order Number',
      type: "string",
      excelType: "s"
    },{
      data: 'Z68_ORDERING_UNIT',
      title: 'Order Unit',
      visible: false
    },{
      data: 'VENDOR_CODE',
      title: 'Vendor Code'
    },{
      data: 'LOCALSUM',
      title: 'Amount',
      render: novanet.fn.render.number(2,'$'),
      excelType: "n",
      excelFmt: "$#,###,##0.00"
    },{
      data: 'Z68_ORDER_TYPE',
      title: 'Order Type'
    },{
      data: 'Z68_MATERIAL_TYPE',
      title: 'Material Type'
    },{
      data: 'UNITS_ORDERED',
      title: 'Units Ordered',
      excelType: "n"
    },{
      data: 'UNITS_INVOICED',
      title: 'Units Invoiced',
      excelType: "n"
    },{
      data: 'UNITS_ARRIVED',
      title: 'Units Arrived',
      excelType: "n"
    },{
      data: 'OBJECT_CODE',
      title: 'Object Code'
    },{
      data: 'Z77_P_DATE',
      title: 'Payment Date',
      render: novanet.fn.render.date('MMM D YYYY'),
      excelType: "d",
      excelFmt: "mmm d, yyyy"
    },{
      data: 'ADM_NUMBER',
      title: 'ADM Number',
      visible: false,
      type: "string",
      excelType: "s"
    },{
      data: 'BIB_NUMBER',
      title: 'BIB Number',
      visible: false,
      type: "string",
      excelType: "s"
    },{
      data: 'Z13_AUTHOR',
      title: 'Author'
    },{
      data: 'Z13_TITLE',
      title: 'Title'
    },{
      data: 'Z13_IMPRINT',
      title: 'Imprint',
      visible: false
    },{
      data: 'Z13_ISBN_ISSN',
      title: 'ISBN/ISSN',
      render: novanet.fn.render.isn(),
      excelType: "s"
    },{
      data: 'Z13_CALL_NO',
      title: 'Location',
      visible: false
    }    
  ]
};
