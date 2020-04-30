novanet.datatableInitObject = {
  order: [[1,"asc"]],
  columns: [
    {
      data : 'EMAIL',
      title: 'Email',
      className: 'text-nowrap'
    },{
      data : 'BOR_STATUS',
      title: 'Patron Status',
      render: novanet.fn.render.lookup("patronStatuses"),
      type: 'string',
      excelType: 's'
    }
  ]
};
