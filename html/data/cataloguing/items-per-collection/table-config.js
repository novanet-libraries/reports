novanet.datatableInitObject = {
  order: [[1,"desc"]],
  columns: [{
      data: "Z30_COLLECTION",
      title: "Collection",
      render: novanet.fn.render.lookup("collectionsFlat")
    },{
      data: "C",
      title: "Item Count",
      excelType: "n"
    }
  ]
};
