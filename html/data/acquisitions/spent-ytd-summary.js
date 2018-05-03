//console.error("deliberate syntax error to prevent people running reports.");
//alert("This report is not yet reliable.");

deliberateError();

novanet.datatableInitObject = {
  order: [[1,"asc"]],
  columns: [{
      data : 'BUDGET_NUMBER',
      title: 'Budget Number',
      visible: false
    },{
      data : 'BUDGET',
      title: 'Budget Name'
    },{
      data : 'ALLOCATED',
      title: 'Allocated',
      render: novanet.fn.render.number(2, '$'),
      excelType: "n",
      excelFmt: "$#,###,###,##0.00"
    },{
      data : 'MAX_OVER_SPEND',
      title: 'Max Over-spend',
      render: novanet.fn.render.number(2, '$'),
      excelType: "n",
      excelFmt: "$#,###,###,##0.00"
    },{
      data : 'SPENT',
      title: 'Spent YTD',
      render: novanet.fn.render.number(2, '$'),
      excelType: "n",
      excelFmt: "$#,###,###,##0.00"
    },{
      data : 'PERCENT_SPENT',
      title: 'Spent%',
      defaultContent: 'no allocation',
      render: novanet.fn.render.percent(),
      excelType: "n",
      excelFmt: "##0%"
    }
  ]
};

